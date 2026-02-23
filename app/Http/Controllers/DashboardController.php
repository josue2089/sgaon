<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Charge;
use App\Models\Course;
use App\Models\Group;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View|RedirectResponse
    {
        $user = request()->user();
        if ($user?->role === 'student') {
            return redirect()->route('portal.student');
        }
        if ($user?->role === 'representative') {
            return redirect()->route('portal.representative');
        }

        $campusId = $user?->campus_id;
        $studentsQuery = Student::query();
        $teachersQuery = Teacher::query();
        $coursesQuery = Course::query();
        $groupsQuery = Group::query();
        $chargesQuery = Charge::where('status', '!=', 'paid');
        $alertsQuery = Alert::where('status', 'open');

        if ($campusId) {
            $studentsQuery->where('campus_id', $campusId);
            $teachersQuery->where('campus_id', $campusId);
            $coursesQuery->where('campus_id', $campusId);
            $groupsQuery->where('campus_id', $campusId);
            $chargesQuery->where('campus_id', $campusId);
            $alertsQuery->where('campus_id', $campusId);
        }

        return view('dashboard', [
            'studentsCount' => $studentsQuery->count(),
            'teachersCount' => $teachersQuery->count(),
            'coursesCount' => $coursesQuery->count(),
            'groupsCount' => $groupsQuery->count(),
            'pendingCharges' => $chargesQuery->sum('amount'),
            'openAlerts' => $alertsQuery->latest()->take(8)->get(),
        ]);
    }
}
