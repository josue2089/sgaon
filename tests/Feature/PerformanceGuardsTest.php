<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Charge;
use App\Models\Student;
use App\Models\User;
use App\Support\CampusScope;
use Database\Seeders\UatFixtureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** #454: consultas por página, sedes memorizadas por petición y CSV por bloques. */
class PerformanceGuardsTest extends TestCase
{
    use RefreshDatabase;

    private function queriesFor(User $user, string $url): int
    {
        $this->actingAs($user);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get($url)->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    public function test_dashboard_stays_under_query_budget(): void
    {
        $this->seed(UatFixtureSeeder::class);
        $admin = User::where('email', 'admina@uat.test')->firstOrFail();

        // Antes de #454: 55 consultas; ahora ~30 la primera vez y ~16 con la caché corta de bandeja y alertas.
        $this->assertLessThanOrEqual(36, $this->queriesFor($admin, route('dashboard')));
        $this->assertLessThanOrEqual(20, $this->queriesFor($admin, route('dashboard')));
    }

    public function test_campus_ids_are_resolved_once_per_request_and_forgotten_after(): void
    {
        $picacho = Campus::create(['name' => 'Picacho', 'code' => 'PIC', 'status' => 'active']);
        $cascada = Campus::create(['name' => 'La Cascada', 'code' => 'CAS', 'status' => 'active']);
        $admin = User::factory()->create(['role' => 'admin', 'campus_id' => $picacho->id, 'is_master' => false]);
        Student::create(['campus_id' => $cascada->id, 'first_name' => 'Eva', 'last_name' => 'Cascada', 'status' => 'active']);

        $this->actingAs($admin)->get(route('students.index'))->assertOk()->assertDontSee('Eva Cascada');

        // Le dan acceso a La Cascada: la petición siguiente ya lo ve (el memo no sobrevive a la petición).
        $admin->campuses()->sync([$picacho->id, $cascada->id]);
        $admin->unsetRelation('campuses');
        $this->actingAs($admin)->get(route('students.index'))->assertOk()->assertSee('Eva Cascada');

        DB::flushQueryLog();
        DB::enableQueryLog();
        CampusScope::allowedCampusIds($admin);
        CampusScope::allowedCampusIds($admin);
        $pivotQueries = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'campus_user'))->count();
        DB::disableQueryLog();
        $this->assertSame(1, $pivotQueries);
        CampusScope::flush();
    }

    public function test_csv_exports_cross_lazy_chunks(): void
    {
        $campus = Campus::create(['name' => 'Picacho', 'code' => 'PIC', 'status' => 'active']);
        $master = User::factory()->create(['role' => 'admin', 'campus_id' => $campus->id, 'is_master' => true]);
        $student = Student::create(['campus_id' => $campus->id, 'first_name' => 'Ana', 'last_name' => 'Test', 'status' => 'active']);
        $now = now();
        DB::table('charges')->insert(collect(range(1, 1203))->map(fn ($i) => [
            'campus_id' => $campus->id, 'student_id' => $student->id, 'concept' => 'Cargo '.$i, 'amount' => 10,
            'currency' => 'USD', 'due_date' => '2026-10-05', 'status' => 'overdue', 'created_at' => $now, 'updated_at' => $now,
        ])->all());
        $this->assertSame(1203, Charge::count());

        $csv = $this->actingAs($master)->get(route('reports.payments', ['export' => 'csv']))->assertOk()->streamedContent();
        $this->assertSame(1203 + 1, substr_count(trim($csv), "\n") + 1);
        preg_match_all('/Cargo \d+/', $csv, $concepts);
        $this->assertCount(1203, array_unique($concepts[0]), 'Sin filas repetidas ni saltadas entre bloques.');

        $mora = $this->actingAs($master)->get(route('finance.index', ['export' => 'mora_csv']))->assertOk()->streamedContent();
        $this->assertSame(1203 + 1, substr_count(trim($mora), "\n") + 1);
        preg_match_all('/Cargo \d+/', $mora, $concepts);
        $this->assertCount(1203, array_unique($concepts[0]));
    }
}
