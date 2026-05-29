<?php

namespace Tests\Feature;

use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\User;
use Database\Seeders\UatFixtureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceFutureSessionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(UatFixtureSeeder::class);
    }

    public function test_future_session_attendance_form_is_read_only(): void
    {
        [$admin, $session] = $this->sessionFixture(now()->addWeek()->toDateString());

        $response = $this->actingAs($admin)->get(route('attendance.index', ['class_session_id' => $session->id]));

        $response->assertOk();
        $response->assertSee('La marcación estará disponible');
        $response->assertDontSee('>Guardar asistencia</button>', false);
    }

    public function test_current_session_attendance_form_is_editable(): void
    {
        [$admin, $session] = $this->sessionFixture(now()->toDateString());

        $response = $this->actingAs($admin)->get(route('attendance.index', ['class_session_id' => $session->id]));

        $response->assertOk();
        $response->assertSee('Guardar asistencia');
    }

    public function test_cannot_store_attendance_for_future_session(): void
    {
        [$admin, $session, $enrollment] = $this->sessionFixture(now()->addDays(3)->toDateString());

        $response = $this->actingAs($admin)->post(route('attendance.store'), [
            'class_session_id' => $session->id,
            'records' => [[
                'enrollment_id' => $enrollment->id,
                'status' => 'present',
                'notes' => null,
            ]],
        ]);

        $response->assertRedirect(route('attendance.index', ['class_session_id' => $session->id]));
        $response->assertSessionHasErrors('class_session_id');
        $this->assertDatabaseMissing('attendance_records', [
            'class_session_id' => $session->id,
            'enrollment_id' => $enrollment->id,
        ]);
    }

    public function test_can_store_attendance_for_today_session(): void
    {
        [$admin, $session, $enrollment] = $this->sessionFixture(now()->toDateString());

        $response = $this->actingAs($admin)->post(route('attendance.store'), [
            'class_session_id' => $session->id,
            'records' => [[
                'enrollment_id' => $enrollment->id,
                'status' => 'present',
                'notes' => null,
            ]],
        ]);

        $response->assertRedirect(route('attendance.index', ['class_session_id' => $session->id]));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('attendance_records', [
            'class_session_id' => $session->id,
            'enrollment_id' => $enrollment->id,
            'status' => 'present',
        ]);
    }

    private function sessionFixture(string $sessionDate): array
    {
        $admin = User::where('email', 'admina@uat.test')->firstOrFail();
        $enrollment = Enrollment::whereHas('student', fn ($q) => $q->where('email', 'studenta@uat.test'))->firstOrFail();

        $session = ClassSession::query()->create([
            'campus_id' => $enrollment->campus_id,
            'group_id' => $enrollment->group_id,
            'session_date' => $sessionDate,
        ]);

        return [$admin, $session, $enrollment];
    }
}
