<?php

namespace App\Support;

use App\Models\Charge;
use App\Models\ClassSession;
use App\Models\MakeupRequest;
use App\Models\MakeupSession;
use App\Models\Student;
use Illuminate\Support\Collection;

/**
 * Datos de un alumno que comparten el portal del alumno y el del representante (por cada hijo). #448
 */
class PortalStudentData
{
    public const OPEN_MAKEUP_STATUSES = ['pending_payment', 'pending_validation', 'approved_for_booking', 'booked'];

    /** @return array<string, mixed> */
    public static function for(Student $student): array
    {
        $attendance = $student->enrollments()
            ->with('group')
            ->withCount([
                'attendanceRecords as present_count' => fn ($q) => $q->where('status', 'present'),
                'attendanceRecords as absent_count' => fn ($q) => $q->where('status', 'absent'),
                'attendanceRecords as late_count' => fn ($q) => $q->where('status', 'late'),
                'attendanceRecords as justified_count' => fn ($q) => $q->where('status', 'justified'),
            ])->get();

        $charges = $student->charges()->with(['paymentRequests', 'makeupRequest'])->latest('due_date')->get();
        $makeupRequests = $student->makeupRequests()
            ->with([
                'missedSession.group.course.program',
                'missedSession.group.course.programLevel',
                'enrollment.group.course.program',
                'enrollment.group.course.programLevel',
                'attachments',
                'booking.makeupSession.teacher',
                'charge',
            ])
            ->latest()
            ->get();

        return [
            'attendance' => $attendance,
            'charges' => $charges,
            'balance' => FinanceReconcile::outstandingByCurrency($charges),
            'pendingCharges' => $charges->filter(fn (Charge $charge) => in_array($charge->status, ['pending', 'partial', 'overdue'], true))->values(),
            'payments' => $student->payments()->with('receipt')->whereNull('voided_at')->latest('paid_at')->get(),
            'chargePaymentRequests' => $student->chargePaymentRequests()->with('charge')->latest()->get(),
            'makeupRequests' => $makeupRequests,
            'openMakeupCount' => $makeupRequests->whereIn('status', self::OPEN_MAKEUP_STATUSES)->count(),
            'eligibleMakeupSessions' => self::eligibleMakeupSessions($student, $makeupRequests),
            'agenda' => self::agenda($student, $makeupRequests),
        ];
    }

    private static function eligibleMakeupSessions(Student $student, Collection $makeupRequests): Collection
    {
        $bookable = $makeupRequests->where('status', MakeupRequest::STATUS_APPROVED_FOR_BOOKING);
        if ($bookable->isEmpty()) {
            return collect();
        }

        $programLevelIds = $bookable
            ->map(fn (MakeupRequest $makeupRequest) => $makeupRequest->enrollment?->group?->course?->program_level_id)
            ->filter()
            ->unique()
            ->values();

        return MakeupSession::query()
            ->with(['teacher', 'program', 'programLevel'])
            ->withCount('activeBookings')
            ->where('campus_id', $student->campus_id)
            ->whereIn('status', ['open', 'full'])
            ->whereDate('session_date', '>=', now()->toDateString())
            ->when($programLevelIds->isNotEmpty(), fn ($query) => $query->whereIn('program_level_id', $programLevelIds))
            ->orderBy('session_date')
            ->orderBy('starts_at')
            ->get()
            ->filter(fn (MakeupSession $session) => $session->available_slots > 0)
            ->values();
    }

    private static function agenda(Student $student, Collection $makeupRequests): Collection
    {
        $groupIds = $student->enrollments()->where('status', 'active')->pluck('group_id')->filter()->values();

        $regular = $groupIds->isEmpty() ? collect() : ClassSession::query()
            ->with('group.course.teacher')
            ->whereIn('group_id', $groupIds)
            ->whereDate('session_date', '>=', now()->toDateString())
            ->orderBy('session_date')
            ->orderBy('starts_at')
            ->limit(20)
            ->get()
            ->map(fn ($session) => [
                'kind' => 'regular',
                'date' => $session->session_date,
                'starts_at' => $session->starts_at,
                'ends_at' => $session->ends_at,
                'label' => $session->group?->course?->name ?? 'Clase regular',
                'teacher' => $session->group?->course?->teacher?->full_name ?? 'N/D',
            ]);

        $makeups = $makeupRequests
            ->filter(fn (MakeupRequest $makeupRequest) => $makeupRequest->booking && in_array($makeupRequest->booking->status, ['reserved', 'attended'], true))
            ->map(function (MakeupRequest $makeupRequest) {
                $session = $makeupRequest->booking?->makeupSession;

                return [
                    'kind' => 'makeup',
                    'date' => $session?->session_date,
                    'starts_at' => $session?->starts_at,
                    'ends_at' => $session?->ends_at,
                    'label' => 'Recuperativa · '.($makeupRequest->enrollment?->group?->course?->name ?? 'N/D'),
                    'teacher' => $session?->teacher?->full_name ?? 'N/D',
                ];
            });

        return $regular->concat($makeups)
            ->filter(fn (array $item) => ! empty($item['date']))
            ->sortBy(fn (array $item) => optional($item['date'])->timestamp ?? 0)
            ->values();
    }
}
