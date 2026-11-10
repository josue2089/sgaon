<?php

namespace App\Http\Controllers;

use App\Models\MakeupBooking;
use App\Models\MakeupSession;
use App\Models\Teacher;
use App\Support\MakeupRecoveryEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Mis recuperativas" del profesor: sus sesiones de recuperativa (teacher_id) con los alumnos reservados,
 * para marcar asistió / no asistió. No ve las de otros profesores. #446
 */
class TeacherMakeupController extends Controller
{
    public function index(Request $request): View
    {
        $teacher = Teacher::forUser($request->user());
        $today = now()->startOfDay();

        $sessions = MakeupSession::query()
            ->with(['program', 'programLevel', 'bookings' => fn ($query) => $query
                ->whereIn('status', ['reserved', 'attended', 'missed'])
                ->with(['makeupRequest.student', 'makeupRequest.missedSession.group.course'])])
            ->where('teacher_id', $teacher?->id ?? -1)
            ->where('status', '!=', 'cancelled')
            ->whereBetween('session_date', [$today->copy()->subDays(30)->toDateString(), $today->copy()->addDays(30)->toDateString()])
            ->orderBy('session_date')
            ->orderBy('starts_at')
            ->get();

        return view('teacher.makeups', [
            'teacher' => $teacher,
            'upcoming' => $sessions->filter(fn (MakeupSession $session) => $session->session_date->gte($today))->values(),
            'past' => $sessions->filter(fn (MakeupSession $session) => $session->session_date->lt($today))->sortByDesc('session_date')->values(),
            'today' => $today,
        ]);
    }

    public function updateBooking(Request $request, MakeupBooking $booking): RedirectResponse
    {
        $teacher = Teacher::forUser($request->user());
        $booking->loadMissing('makeupSession');
        if (! $teacher || (int) $booking->makeupSession?->teacher_id !== (int) $teacher->id) {
            abort(403);
        }

        $data = $request->validate([
            'status' => ['required', 'in:attended,missed,reserved'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        // No se marca asistencia de una recuperativa que todavía no ocurrió.
        if ($data['status'] !== 'reserved' && $booking->makeupSession?->session_date?->isFuture()) {
            return back()->withErrors(['status' => 'La recuperativa todavía no ocurrió.']);
        }

        MakeupRecoveryEngine::updateBookingStatus($booking, $data['status'], $data['notes'] ?? $booking->notes);

        return back()->with('success', 'Asistencia de la recuperativa guardada.');
    }
}
