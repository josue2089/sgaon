<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\User;
use Database\Seeders\UatFixtureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceReportExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(UatFixtureSeeder::class);
    }

    public function test_attendance_csv_export_includes_teacher_notes(): void
    {
        $admin = User::where('email', 'admina@uat.test')->firstOrFail();
        $enrollment = Enrollment::whereHas('student', fn ($q) => $q->where('email', 'studenta@uat.test'))->firstOrFail();

        $session = ClassSession::query()->create([
            'campus_id' => $enrollment->campus_id,
            'group_id' => $enrollment->group_id,
            'session_date' => now()->subDay()->toDateString(),
        ]);

        AttendanceRecord::query()->create([
            'class_session_id' => $session->id,
            'enrollment_id' => $enrollment->id,
            'status' => 'late',
            'notes' => 'Llegó 10 minutos tarde por tráfico',
        ]);

        $response = $this->actingAs($admin)->get('/reports/attendance?export=csv');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));

        $csv = $response->streamedContent();
        $this->assertStringContainsString('notes', $csv);
        $this->assertStringContainsString('Llegó 10 minutos tarde por tráfico', $csv);
    }
}
