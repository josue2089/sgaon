<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\AttendanceRecord;
use App\Models\Charge;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\UatFixtureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UatChecklistTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(UatFixtureSeeder::class);
    }

    public function test_uat_campus_isolation_blocks_cross_campus_student_edit(): void
    {
        $adminA = User::where('email', 'admina@uat.test')->firstOrFail();
        $studentB = Student::where('email', 'studentb@uat.test')->firstOrFail();

        $response = $this->actingAs($adminA)->get(route('students.edit', $studentB));

        $response->assertForbidden();
    }

    public function test_uat_teacher_can_only_record_attendance_for_assigned_sessions(): void
    {
        $teacherUser = User::where('email', 'teachera@uat.test')->firstOrFail();
        $enrollmentA = Enrollment::whereHas('student', fn ($q) => $q->where('email', 'studenta@uat.test'))->firstOrFail();

        $allowedSession = ClassSession::create([
            'campus_id' => $enrollmentA->campus_id,
            'group_id' => $enrollmentA->group_id,
            'session_date' => now()->toDateString(),
        ]);

        $otherCampusEnrollment = Enrollment::whereHas('student', fn ($q) => $q->where('email', 'studentb@uat.test'))->firstOrFail();
        $forbiddenSession = ClassSession::create([
            'campus_id' => $otherCampusEnrollment->campus_id,
            'group_id' => $otherCampusEnrollment->group_id,
            'session_date' => now()->toDateString(),
        ]);

        $ok = $this->actingAs($teacherUser)->post(route('attendance.store'), [
            'class_session_id' => $allowedSession->id,
            'records' => [[
                'enrollment_id' => $enrollmentA->id,
                'status' => 'present',
                'notes' => null,
            ]],
        ]);
        $ok->assertRedirect();

        $forbidden = $this->actingAs($teacherUser)->post(route('attendance.store'), [
            'class_session_id' => $forbiddenSession->id,
            'records' => [[
                'enrollment_id' => $otherCampusEnrollment->id,
                'status' => 'present',
                'notes' => null,
            ]],
        ]);
        $forbidden->assertForbidden();
    }

    public function test_uat_representative_portal_only_sees_linked_students(): void
    {
        $repUser = User::where('email', 'repa@uat.test')->firstOrFail();

        $response = $this->actingAs($repUser)->get(route('portal.representative'));

        $response->assertOk();
        $response->assertSee('Student A');
        $response->assertDontSee('Student B');
    }

    public function test_uat_reports_export_and_alert_generation(): void
    {
        $adminA = User::where('email', 'admina@uat.test')->firstOrFail();
        $studentA = Student::where('email', 'studenta@uat.test')->firstOrFail();
        $enrollmentA = Enrollment::where('student_id', $studentA->id)->firstOrFail();

        foreach ([5, 10, 15] as $daysAgo) {
            $session = ClassSession::create([
                'campus_id' => $studentA->campus_id,
                'group_id' => $enrollmentA->group_id,
                'session_date' => now()->subDays($daysAgo)->toDateString(),
            ]);

            AttendanceRecord::create([
                'class_session_id' => $session->id,
                'enrollment_id' => $enrollmentA->id,
                'status' => 'absent',
            ]);
        }

        Charge::create([
            'campus_id' => $studentA->campus_id,
            'student_id' => $studentA->id,
            'concept' => 'Mensualidad Vencida',
            'amount' => 150,
            'due_date' => now()->subDays(10)->toDateString(),
            'status' => 'pending',
        ]);

        $exportAttendance = $this->actingAs($adminA)->get('/reports/attendance?export=csv');
        $exportAttendance->assertOk();
        $this->assertStringContainsString('text/csv', (string) $exportAttendance->headers->get('content-type'));

        $exportPayments = $this->actingAs($adminA)->get('/reports/payments?export=csv');
        $exportPayments->assertOk();
        $this->assertStringContainsString('text/csv', (string) $exportPayments->headers->get('content-type'));

        $this->artisan('generate:alerts')->assertExitCode(0);

        $this->assertTrue(Alert::where('student_id', $studentA->id)->where('type', 'finance')->exists());
    }
}
