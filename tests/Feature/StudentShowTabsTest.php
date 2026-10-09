<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Charge;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentShowTabsTest extends TestCase
{
    use RefreshDatabase;

    private Campus $campus;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->campus = Campus::create(['name' => 'Picacho', 'code' => 'PIC', 'status' => 'active']);
        $this->student = Student::create(['campus_id' => $this->campus->id, 'first_name' => 'Ana', 'last_name' => 'Pérez', 'status' => 'active']);
        Charge::create(['campus_id' => $this->campus->id, 'student_id' => $this->student->id, 'concept' => 'Mensualidad octubre', 'charge_type' => 'tuition', 'amount' => 40, 'currency' => 'USD', 'due_date' => now()->addDays(5), 'status' => 'pending']);
    }

    public function test_student_page_has_tabs_panels_and_action_bar(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'campus_id' => $this->campus->id, 'is_master' => false]);

        $response = $this->actingAs($admin)->get(route('students.show', $this->student))->assertOk();

        foreach (['resumen', 'academico', 'finanzas', 'recuperativas', 'documentos', 'historial'] as $tab) {
            $response->assertSee('data-tab-target="'.$tab.'"', false);
            $response->assertSee('data-tab-panel="'.$tab.'"', false);
        }

        $response
            ->assertSee('class="portal-tab-panel student-panel is-active" id="panel-resumen"', false)
            ->assertSee(route('enrollments.create', ['student_id' => $this->student->id]), false)
            ->assertSee('data-modal-open="student-payment-modal"', false)
            ->assertSee('data-modal-open="student-charge-modal"', false)
            ->assertSee('id="student-finance"', false)
            ->assertSee('Mensualidad octubre')
            ->assertSee('Ficha PDF');
    }

    public function test_charge_form_errors_reopen_its_modal(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'campus_id' => $this->campus->id, 'is_master' => false]);

        $this->actingAs($admin)
            ->from(route('students.show', $this->student))
            ->followingRedirects()
            ->post(route('students.charges.store', $this->student), ['concept' => 'Material', 'status' => 'pending'])
            ->assertOk()
            ->assertSee('id="student-charge-modal" class="ui-modal ui-modal--md" data-open-on-load="1"', false);
    }

    public function test_enroll_action_preselects_the_student(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'campus_id' => $this->campus->id, 'is_master' => false]);

        $this->actingAs($admin)->get(route('enrollments.create', ['student_id' => $this->student->id]))
            ->assertOk()
            ->assertSee('<option value="'.$this->student->id.'" selected', false);
    }
}
