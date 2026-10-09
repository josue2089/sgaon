<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Charge;
use App\Models\Student;
use App\Models\User;
use App\Support\StatusLabel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatusLabelTest extends TestCase
{
    use RefreshDatabase;

    public function test_labels_and_tones(): void
    {
        $this->assertSame('Vencido', StatusLabel::label('overdue'));
        $this->assertSame('Abonado', StatusLabel::label('partial'));
        $this->assertSame('Activa', StatusLabel::label('active', 'enrollment'));
        $this->assertSame('Activo', StatusLabel::label('active'));
        $this->assertSame('Pendiente pago', StatusLabel::label('pending_payment', 'makeup'));
        $this->assertSame('Por validar', StatusLabel::label('pending_validation'));
        $this->assertSame('—', StatusLabel::label(null));
        $this->assertSame('danger', StatusLabel::tone('overdue'));
        $this->assertSame('ok', StatusLabel::tone('paid'));
        $this->assertSame('warn', StatusLabel::tone('pending'));
        $this->assertSame('Profesor', StatusLabel::role('teacher'));
    }

    public function test_main_screens_show_spanish_statuses(): void
    {
        $campus = Campus::create(['name' => 'Picacho', 'code' => 'PIC', 'status' => 'active']);
        $admin = User::factory()->create(['role' => 'admin', 'campus_id' => $campus->id, 'is_master' => true]);
        $student = Student::create(['campus_id' => $campus->id, 'first_name' => 'Ana', 'last_name' => 'Test', 'status' => 'active']);
        Charge::create(['campus_id' => $campus->id, 'student_id' => $student->id, 'concept' => 'Mensualidad', 'charge_type' => 'other', 'amount' => 10, 'currency' => 'USD', 'due_date' => now()->subDays(10), 'status' => 'overdue']);

        $this->actingAs($admin)->get(route('finance.index'))
            ->assertOk()
            ->assertSee('<span class="badge-pill badge-danger">Vencido</span>', false)
            ->assertSee('<th>Estado</th>', false)
            ->assertDontSee('>overdue<', false);

        $this->actingAs($admin)->get(route('students.show', $student))
            ->assertOk()
            ->assertSee('Vencido')
            ->assertDontSee('>Overdue<', false)
            ->assertDontSee('>Active<', false);

        $this->actingAs($admin)->get(route('students.create'))
            ->assertOk()
            ->assertSee('<label>Estado</label>', false)
            ->assertSee('>Graduado</option>', false)
            ->assertDontSee('>withdrawn</option>', false);

        $this->actingAs($admin)->get(route('students.index'))
            ->assertOk()
            ->assertSee('<span class="badge-pill badge-ok">Activo</span>', false);
    }
}
