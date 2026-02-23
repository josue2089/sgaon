<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\AuditLog;
use App\Models\Representative;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_cannot_access_admin_finance(): void
    {
        $user = User::factory()->create(['role' => 'student']);

        $response = $this->actingAs($user)->get('/finance');

        $response->assertForbidden();
    }

    public function test_admin_can_access_finance(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)->get('/finance');

        $response->assertOk();
    }

    public function test_student_dashboard_redirects_to_student_portal(): void
    {
        $campus = Campus::create(['name' => 'Picacho', 'code' => 'PIC', 'status' => 'active']);
        $user = User::factory()->create(['role' => 'student', 'email' => 's@test.dev', 'campus_id' => $campus->id]);
        Student::create([
            'campus_id' => $campus->id,
            'user_id' => $user->id,
            'first_name' => 'Test',
            'last_name' => 'Student',
            'email' => 's@test.dev',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertRedirect('/portal/student');
    }

    public function test_representative_portal_is_accessible_with_profile(): void
    {
        $campus = Campus::create(['name' => 'Picacho', 'code' => 'PIC2', 'status' => 'active']);
        $user = User::factory()->create(['role' => 'representative', 'email' => 'r@test.dev', 'campus_id' => $campus->id]);

        Representative::create([
            'campus_id' => $campus->id,
            'user_id' => $user->id,
            'first_name' => 'Rep',
            'last_name' => 'Demo',
            'email' => 'r@test.dev',
        ]);

        $response = $this->actingAs($user)->get('/portal/representative');

        $response->assertOk();
    }

    public function test_audit_log_is_created_on_finance_charge_creation(): void
    {
        $campus = Campus::create(['name' => 'Picacho', 'code' => 'PIC3', 'status' => 'active']);
        $admin = User::factory()->create(['role' => 'admin', 'campus_id' => $campus->id]);
        $student = Student::create([
            'campus_id' => $campus->id,
            'first_name' => 'Finance',
            'last_name' => 'Student',
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin)->post('/finance/charges', [
            'student_id' => $student->id,
            'concept' => 'Mensualidad',
            'amount' => 100,
            'due_date' => '2026-01-01',
            'status' => 'pending',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertTrue(AuditLog::query()->where('action', 'finance.charge.create')->exists());
    }
}
