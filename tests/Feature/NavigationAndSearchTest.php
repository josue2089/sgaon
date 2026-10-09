<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Representative;
use App\Models\Student;
use App\Models\User;
use App\Support\Navigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationAndSearchTest extends TestCase
{
    use RefreshDatabase;

    private Campus $campus;

    private Campus $otherCampus;

    protected function setUp(): void
    {
        parent::setUp();
        $this->campus = Campus::create(['name' => 'Picacho', 'code' => 'PIC', 'status' => 'active']);
        $this->otherCampus = Campus::create(['name' => 'Cascada', 'code' => 'CAS', 'status' => 'active']);
    }

    private function labels(?User $user): array
    {
        return array_column(Navigation::for($user, 'dashboard'), 'label');
    }

    public function test_admin_menu_has_daily_screens_one_click_away(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'campus_id' => $this->campus->id, 'is_master' => false]);

        $this->assertSame(['Inicio', 'Alumnos', 'Cursos', 'Asistencia', 'Finanzas', 'Recuperativas', 'Reportes', 'Configuración'], $this->labels($admin));

        $groups = collect(Navigation::for($admin, 'dashboard'))->keyBy('label');
        $reports = array_column($groups['Reportes']['children'], 'label');
        $this->assertContains('Resumen financiero', $reports);
        $this->assertNotContains('Proyección por sede', $reports, 'Solo master.');
        $this->assertSame(['Profesores', 'Feriados y días sin clase'], array_column($groups['Configuración']['children'], 'label'));
    }

    public function test_master_sees_full_configuration_and_teacher_sees_only_attendance(): void
    {
        $master = User::factory()->create(['role' => 'admin', 'campus_id' => $this->campus->id, 'is_master' => true]);
        $config = collect(Navigation::for($master, 'dashboard'))->firstWhere('label', 'Configuración');
        $this->assertContains('Usuarios admin', array_column($config['children'], 'label'));
        $this->assertContains('Tarifa mensual', array_column($config['children'], 'label'));

        $teacher = User::factory()->create(['role' => 'teacher', 'campus_id' => $this->campus->id]);
        $this->assertSame(['Inicio', 'Asistencia'], $this->labels($teacher));
    }

    public function test_header_renders_single_menu_without_mas_and_marks_active_section(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'campus_id' => $this->campus->id, 'is_master' => true]);

        $this->actingAs($admin)->get(route('finance.summary'))
            ->assertOk()
            ->assertDontSee('>Más<', false)
            ->assertSee('aria-label="Abrir menú"', false)
            ->assertSee('data-student-search', false)
            ->assertSee('class="section-tab is-active" aria-current="page">Resumen financiero</a>', false);

        $this->actingAs($admin)->get(route('students.historical.index'))
            ->assertOk()
            ->assertSee('aria-current="page">Históricos</a>', false);
    }

    public function test_student_search_finds_by_name_document_and_representative_within_campus(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'campus_id' => $this->campus->id, 'is_master' => false]);
        $sergio = Student::create(['campus_id' => $this->campus->id, 'first_name' => 'Sergio', 'last_name' => 'León', 'document_id' => '30111222', 'status' => 'active']);
        $ana = Student::create(['campus_id' => $this->campus->id, 'first_name' => 'Ana', 'last_name' => 'Pérez', 'status' => 'withdrawn']);
        $representative = Representative::create(['campus_id' => $this->campus->id, 'first_name' => 'Marta', 'last_name' => 'Rojas', 'status' => 'active']);
        $ana->representatives()->attach($representative->id);
        Student::create(['campus_id' => $this->otherCampus->id, 'first_name' => 'Sergio', 'last_name' => 'Otra Sede', 'status' => 'active']);

        $this->actingAs($admin)->getJson(route('students.search', ['q' => 'sergio']))
            ->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.url', route('students.show', $sergio));

        $this->actingAs($admin)->getJson(route('students.search', ['q' => '30111']))->assertJsonPath('results.0.name', 'Sergio León');

        $this->actingAs($admin)->getJson(route('students.search', ['q' => 'Marta']))
            ->assertJsonPath('results.0.name', 'Ana Pérez')
            ->assertJsonPath('results.0.detail', 'Picacho · Retirado');

        $this->actingAs($admin)->getJson(route('students.search', ['q' => 's']))->assertJsonCount(0, 'results');
    }

    public function test_teacher_cannot_use_student_search_and_wizard_is_retired(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'campus_id' => $this->campus->id]);
        $this->actingAs($teacher)->getJson(route('students.search', ['q' => 'ana']))->assertForbidden();

        $admin = User::factory()->create(['role' => 'admin', 'campus_id' => $this->campus->id, 'is_master' => true]);
        $this->actingAs($admin)->get('/operations/wizard')->assertRedirect('/courses');
    }
}
