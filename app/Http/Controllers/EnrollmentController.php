<?php

namespace App\Http\Controllers;

use App\Models\Enrollment;
use App\Models\Group;
use App\Models\Student;
use App\Support\AuditTrail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EnrollmentController extends Controller
{
    private function campusId(): ?int
    {
        return request()->user()?->campus_id;
    }

    public function index(): View
    {
        $query = Enrollment::with(['student', 'group.course'])->latest();
        if ($this->campusId()) {
            $query->where('campus_id', $this->campusId());
        }

        return view('enrollments.index', [
            'enrollments' => $query->paginate(20),
        ]);
    }

    public function create(): View
    {
        $students = Student::orderBy('first_name');
        $groups = Group::with('course')->orderBy('name');
        if ($this->campusId()) {
            $students->where('campus_id', $this->campusId());
            $groups->where('campus_id', $this->campusId());
        }

        return view('enrollments.create', [
            'students' => $students->get(),
            'groups' => $groups->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'group_id' => ['required', 'exists:groups,id'],
            'enrolled_at' => ['nullable', 'date'],
            'status' => ['required', 'string'],
            'progress' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        $student = Student::findOrFail($data['student_id']);
        if ($this->campusId() && (int) $student->campus_id !== (int) $this->campusId()) {
            abort(403);
        }
        $data['campus_id'] = $this->campusId() ?: $student->campus_id;

        $enrollment = Enrollment::updateOrCreate(
            ['student_id' => $data['student_id'], 'group_id' => $data['group_id']],
            $data,
        );
        AuditTrail::log($request, 'enrollment.upsert', $enrollment, $data);

        return redirect()->route('enrollments.index')->with('success', 'Inscripción guardada.');
    }

    public function edit(Enrollment $enrollment): View
    {
        $students = Student::orderBy('first_name');
        $groups = Group::with('course')->orderBy('name');
        if ($this->campusId()) {
            $students->where('campus_id', $this->campusId());
            $groups->where('campus_id', $this->campusId());
        }

        return view('enrollments.edit', [
            'enrollment' => $enrollment,
            'students' => $students->get(),
            'groups' => $groups->get(),
        ]);
    }

    public function update(Request $request, Enrollment $enrollment): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'group_id' => ['required', 'exists:groups,id'],
            'enrolled_at' => ['nullable', 'date'],
            'status' => ['required', 'string'],
            'progress' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        $student = Student::findOrFail($data['student_id']);
        if ($this->campusId() && (int) $student->campus_id !== (int) $this->campusId()) {
            abort(403);
        }
        $data['campus_id'] = $this->campusId() ?: $student->campus_id;

        $enrollment->update($data);
        AuditTrail::log($request, 'enrollment.update', $enrollment, $data);

        return redirect()->route('enrollments.index')->with('success', 'Inscripción actualizada.');
    }

    public function destroy(Enrollment $enrollment): RedirectResponse
    {
        $payload = ['student_id' => $enrollment->student_id, 'group_id' => $enrollment->group_id];
        $enrollment->delete();
        AuditTrail::log(request(), 'enrollment.delete', null, $payload);

        return redirect()->route('enrollments.index')->with('success', 'Inscripción eliminada.');
    }
}
