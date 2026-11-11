<?php

namespace Tests\Feature;

use App\Models\AcademicLevel;
use App\Models\Campus;
use App\Models\Charge;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\Payment;
use App\Models\Program;
use App\Models\ProgramLevel;
use App\Models\Representative;
use App\Models\Student;
use App\Models\User;
use App\Support\SolvencyReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** #514: solvencia por sede y tipo de programa. */
class SolvencyReportTest extends TestCase
{
    use RefreshDatabase;

    private Campus $picacho;

    private Campus $cascada;

    private Program $extracurricular;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-11 10:00:00');
        $this->picacho = Campus::create(['name' => 'Picacho', 'code' => 'PIC', 'status' => 'active']);
        $this->cascada = Campus::create(['name' => 'La Cascada', 'code' => 'CAS', 'status' => 'active']);
        $this->extracurricular = Program::create(['name' => 'Ajedrez', 'code' => 'AJD', 'status' => 'active', 'is_extracurricular' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function enrolled(Campus $campus, string $name, ?Program $program = null, string $status = 'active'): array
    {
        $level = ProgramLevel::query()->firstOrFail();
        $course = Course::create([
            'campus_id' => $campus->id,
            'academic_level_id' => AcademicLevel::firstOrCreate(['campus_id' => $campus->id, 'name' => 'HS'])->id,
            'program_id' => $program?->id ?? $level->program_id,
            'program_level_id' => $program ? null : $level->id,
            'name' => ($program ? 'Ajedrez ' : 'HS5A ').$name,
            'start_date' => '2026-09-01',
            'status' => 'active',
        ]);
        $group = Group::create(['campus_id' => $campus->id, 'course_id' => $course->id, 'name' => 'G-'.$name, 'status' => 'active', 'capacity' => 30]);
        $student = Student::firstOrCreate(['campus_id' => $campus->id, 'first_name' => $name, 'last_name' => 'Test'], ['status' => 'active']);
        Enrollment::create(['campus_id' => $campus->id, 'student_id' => $student->id, 'group_id' => $group->id, 'enrolled_at' => '2026-09-01', 'status' => $status, 'progress' => 0]);

        return [$student, $course];
    }

    private function charge(Student $student, Course $course, float $amount, string $due, string $currency = 'USD', array $extra = []): Charge
    {
        return Charge::create($extra + [
            'campus_id' => $student->campus_id, 'student_id' => $student->id, 'course_id' => $course->id,
            'concept' => 'Mensualidad '.$due, 'charge_type' => 'tuition', 'amount' => $amount, 'currency' => $currency,
            'due_date' => $due, 'status' => Carbon::parse($due)->isPast() ? 'overdue' : 'pending',
        ]);
    }

    private function seedScenario(): array
    {
        // Picacho regular: Ana debe 2 meses (uno con abono parcial), Beto al día, Caro pagó todo, Dani tiene un cargo anulado.
        [$ana, $anaCourse] = $this->enrolled($this->picacho, 'Ana');
        $this->charge($ana, $anaCourse, 40, '2026-09-05');
        $partial = $this->charge($ana, $anaCourse, 40, '2026-11-05', 'USD', ['status' => 'partial']);
        Payment::create(['campus_id' => $this->picacho->id, 'student_id' => $ana->id, 'charge_id' => $partial->id, 'amount' => 15, 'currency' => 'USD', 'paid_at' => '2026-11-06', 'status' => 'completed']);
        $this->enrolled($this->picacho, 'Beto');
        [$caro, $caroCourse] = $this->enrolled($this->picacho, 'Caro');
        $paid = $this->charge($caro, $caroCourse, 40, '2026-11-05');
        Payment::create(['campus_id' => $this->picacho->id, 'student_id' => $caro->id, 'charge_id' => $paid->id, 'amount' => 40, 'currency' => 'USD', 'paid_at' => '2026-11-04', 'status' => 'completed']);
        [$dani, $daniCourse] = $this->enrolled($this->picacho, 'Dani');
        $this->charge($dani, $daniCourse, 40, '2026-10-05', 'USD', ['voided_at' => now()]);
        // Cargo que todavía no vence: no es deuda vencida.
        $this->charge($dani, $daniCourse, 40, '2026-11-20');

        // Picacho extracurricular: Ana también está en Ajedrez y debe 80 EUR.
        [, $chess] = $this->enrolled($this->picacho, 'Ana', $this->extracurricular);
        $this->charge($ana, $chess, 80, '2026-11-05', 'EUR');

        // La Cascada: Eva se retiró pero sigue debiendo.
        [$eva, $evaCourse] = $this->enrolled($this->cascada, 'Eva', null, 'withdrawn');
        $this->charge($eva, $evaCourse, 40, '2026-08-05');
        $this->enrolled($this->cascada, 'Fer');

        return compact('ana', 'eva');
    }

    public function test_counts_people_by_campus_and_program_type(): void
    {
        $this->seedScenario();
        $admin = User::factory()->create(['role' => 'admin', 'campus_id' => $this->picacho->id, 'is_master' => true]);
        $report = SolvencyReport::build($admin);
        $rows = $report['rows']->keyBy(fn ($row) => $row['campus_name'].'|'.$row['category']);

        $regular = $rows['Picacho|regular'];
        $this->assertSame(4, $regular['active']);
        $this->assertSame(3, $regular['up_to_date'], 'Beto, Caro (pagó) y Dani (anulado y cargo no vencido).');
        $this->assertSame(1, $regular['owing'], 'Ana cuenta una sola vez aunque deba dos cargos.');
        $this->assertSame(1, $regular['over_30']);
        $this->assertSame(['USD' => 65.0], $regular['amounts'], '40 + (40 - 15 abonados).');

        $chess = $rows['Picacho|extracurricular'];
        $this->assertSame([1, 0, 1], [$chess['active'], $chess['up_to_date'], $chess['owing']]);
        $this->assertSame(['EUR' => 80.0], $chess['amounts']);

        $cascada = $rows['La Cascada|regular'];
        $this->assertSame([1, 1, 1, 1], [$cascada['active'], $cascada['up_to_date'], $cascada['owing'], $cascada['owing_inactive']]);

        $this->assertSame(3, $report['totals']['owing']);
        $this->assertSame(['EUR' => 80.0, 'USD' => 105.0], $report['totals']['amounts']);
    }

    public function test_month_cut_only_looks_at_charges_due_that_month(): void
    {
        $this->seedScenario();
        $admin = User::factory()->create(['role' => 'admin', 'campus_id' => $this->picacho->id, 'is_master' => true]);
        $rows = SolvencyReport::build($admin, null, '2026-11')['rows']->keyBy(fn ($row) => $row['campus_name'].'|'.$row['category']);

        // Noviembre: Ana debe el resto de su cuota y Dani la del 20/11; la deuda de septiembre de Ana no entra.
        $this->assertSame(2, $rows['Picacho|regular']['owing']);
        $this->assertSame(['USD' => 65.0], $rows['Picacho|regular']['amounts']);
        $this->assertFalse($rows->has('La Cascada|regular') && $rows['La Cascada|regular']['owing'] > 0, 'Eva debe agosto, no noviembre.');
    }

    public function test_page_list_and_exports_respect_campus_access(): void
    {
        $this->seedScenario();
        $picachoAdmin = User::factory()->create(['role' => 'admin', 'campus_id' => $this->picacho->id, 'is_master' => false]);

        $this->actingAs($picachoAdmin)->get(route('reports.solvency'))
            ->assertOk()
            ->assertSee('Solvencia por sede')
            ->assertSee('Picacho')
            ->assertSee('Ana Test')
            ->assertDontSee('Eva Test')
            ->assertDontSee('La Cascada');

        // Un admin de Picacho no ve La Cascada aunque la pida.
        $this->actingAs($picachoAdmin)->get(route('reports.solvency', ['campus_id' => $this->cascada->id]))
            ->assertOk()->assertDontSee('Eva Test');

        $this->actingAs($picachoAdmin)->get(route('reports.solvency', ['list_campus_id' => $this->picacho->id, 'category' => 'regular', 'bucket' => 'up_to_date']))
            ->assertOk()->assertSee('Beto Test')->assertSee('Caro Test');

        $summary = $this->actingAs($picachoAdmin)->get(route('reports.solvency', ['export' => 'summary_csv']));
        $summary->assertOk();
        $csv = $summary->streamedContent();
        $this->assertStringContainsString('Picacho;Regular;4;3;1;0;1;65,00;0,00', $csv);
        $this->assertStringContainsString('Picacho;Extracurricular;1;0;1;0;0;0,00;80,00', $csv);

        $list = $this->actingAs($picachoAdmin)->get(route('reports.solvency', ['export' => 'list_csv', 'bucket' => 'owing']))->streamedContent();
        $this->assertStringContainsString('Ana Test', $list);
        $this->assertStringNotContainsString('Beto Test', $list);

        $teacher = User::factory()->create(['role' => 'teacher', 'campus_id' => $this->picacho->id]);
        $this->actingAs($teacher)->get(route('reports.solvency'))->assertForbidden();
    }

    public function test_representative_phone_shows_in_list(): void
    {
        ['ana' => $ana] = $this->seedScenario();
        Representative::create(['campus_id' => $this->picacho->id, 'first_name' => 'Marta', 'last_name' => 'Test', 'mobile_phone' => '0414-5550000'])->students()->attach($ana->id);
        $master = User::factory()->create(['role' => 'admin', 'campus_id' => $this->picacho->id, 'is_master' => true]);

        $this->actingAs($master)->get(route('reports.solvency'))
            ->assertOk()->assertSee('Marta Test')->assertSee('0414-5550000')->assertSee('Eva Test')->assertSee('Sin inscripción activa');
    }
}
