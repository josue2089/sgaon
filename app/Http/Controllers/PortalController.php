<?php

namespace App\Http\Controllers;

use App\Models\Representative;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PortalController extends Controller
{
    public function student(Request $request): View
    {
        $student = $this->resolveStudent($request);

        abort_if(! $student, 404, 'Perfil de alumno no vinculado.');

        $enrollments = $student->enrollments()->with('group.course')->latest('enrolled_at')->get();
        $attendance = $student->enrollments()
            ->withCount([
                'attendanceRecords as present_count' => fn ($q) => $q->where('status', 'present'),
                'attendanceRecords as absent_count' => fn ($q) => $q->where('status', 'absent'),
                'attendanceRecords as late_count' => fn ($q) => $q->where('status', 'late'),
                'attendanceRecords as justified_count' => fn ($q) => $q->where('status', 'justified'),
            ])->get();

        $charges = $student->charges()->latest('due_date')->get();
        $payments = $student->payments()->latest('paid_at')->get();

        return view('portal.student', compact('student', 'enrollments', 'attendance', 'charges', 'payments'));
    }

    public function representative(Request $request): View
    {
        $representative = $this->resolveRepresentative($request);
        abort_if(! $representative, 404, 'Perfil de representante no vinculado.');

        $students = $representative->students()
            ->with([
                'enrollments.group.course',
                'charges',
                'payments',
            ])
            ->get();

        return view('portal.representative', compact('representative', 'students'));
    }

    private function resolveStudent(Request $request): ?Student
    {
        $user = $request->user();
        if (! $user) {
            return null;
        }

        return Student::where('user_id', $user->id)
            ->orWhere(fn ($q) => $q->whereNull('user_id')->where('email', $user->email))
            ->first();
    }

    private function resolveRepresentative(Request $request): ?Representative
    {
        $user = $request->user();
        if (! $user) {
            return null;
        }

        return Representative::where('user_id', $user->id)
            ->orWhere(fn ($q) => $q->whereNull('user_id')->where('email', $user->email))
            ->first();
    }
}
