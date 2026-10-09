<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Charge;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class UiComponentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_void_actions_use_confirm_dialog_with_reason_instead_of_prompt(): void
    {
        $campus = Campus::create(['name' => 'Picacho', 'code' => 'PIC', 'status' => 'active']);
        $admin = User::factory()->create(['role' => 'admin', 'campus_id' => $campus->id, 'is_master' => true]);
        $student = Student::create(['campus_id' => $campus->id, 'first_name' => 'Ana', 'last_name' => 'Test', 'status' => 'active']);
        Charge::create(['campus_id' => $campus->id, 'student_id' => $student->id, 'concept' => 'Mensualidad', 'charge_type' => 'other', 'amount' => 10, 'currency' => 'USD', 'due_date' => now()->addDays(5), 'status' => 'pending']);

        $this->actingAs($admin)->get(route('students.show', $student))
            ->assertOk()
            ->assertSee('data-confirm-reason="Motivo de la anulación"', false)
            ->assertSee('data-confirm-dialog', false)
            ->assertSee('fonts.googleapis.com/css2?family=Inter', false)
            ->assertDontSee('prompt(', false)
            ->assertDontSee('Solo disponible para administrador master');
    }

    public function test_validation_errors_are_exposed_for_inline_display(): void
    {
        $campus = Campus::create(['name' => 'Picacho', 'code' => 'PIC', 'status' => 'active']);
        $admin = User::factory()->create(['role' => 'admin', 'campus_id' => $campus->id, 'is_master' => true]);
        $student = Student::create(['campus_id' => $campus->id, 'first_name' => 'Ana', 'last_name' => 'Test', 'status' => 'active']);

        $this->actingAs($admin)
            ->from(route('students.show', $student))
            ->followingRedirects()
            ->post(route('students.charges.store', $student), ['concept' => 'X', 'amount' => 10, 'due_date' => '0026-01-01', 'status' => 'pending'])
            ->assertSee('id="form-errors"', false)
            ->assertSee('"due_date":', false)
            ->assertSee('Revisa el a', false);
    }

    public function test_form_field_component_marks_required_and_shows_error(): void
    {
        $errors = (new ViewErrorBag)->put('default', new MessageBag(['email' => ['El email no es válido.']]));

        view()->share('errors', $errors);
        $html = Blade::render('<x-form.field label="Email" name="email" required hint="Para el recibo"><input id="email" name="email"></x-form.field>');

        $this->assertStringContainsString('field-required', $html);
        $this->assertStringContainsString('form-field--invalid', $html);
        $this->assertStringContainsString('El email no es válido.', $html);
        $this->assertStringContainsString('Para el recibo', $html);
    }
}
