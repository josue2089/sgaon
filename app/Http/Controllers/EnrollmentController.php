<?php

namespace App\Http\Controllers;

use App\Models\Enrollment;
use App\Models\Group;
use App\Models\Student;
use App\Support\RenewalEnrollmentEligibility;
use App\Support\AuditTrail;
use App\Services\EnrollmentBillingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class EnrollmentController extends Controller
{
    private function campusId(): ?int
    {
        return \App\Support\CampusScope::campusIdFor(request()->user());
    }

    public function index(Request $request): View
    {
        $query = Enrollment::with(['student', 'group.course'])->latest();
        if ($this->campusId()) {
            $query->where('campus_id', $this->campusId());
        }

        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $query->where(function (Builder $builder) use ($q) {
                $builder
                    ->whereHas('student', fn (Builder $studentBuilder) => $studentBuilder
                        ->whereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$q}%"])
                        ->orWhere('email', 'like', "%{$q}%"))
                    ->orWhereHas('group', fn (Builder $groupBuilder) => $groupBuilder
                        ->where('name', 'like', "%{$q}%")
                        ->orWhereHas('course', fn (Builder $courseBuilder) => $courseBuilder->where('name', 'like', "%{$q}%")));
            });
        }

        $groupId = (string) $request->query('group_id', '');
        if ($groupId !== '') {
            $query->where('group_id', $groupId);
        }

        $status = (string) $request->query('status', '');
        if ($status !== '') {
            $query->where('status', $status);
        }

        $groups = Group::query()
            ->with('course')
            ->when($this->campusId(), fn (Builder $builder) => $builder->where('campus_id', $this->campusId()))
            ->orderBy('name')
            ->get(['id', 'name', 'course_id']);

        return view('enrollments.index', [
            'enrollments' => $query->paginate(20)->withQueryString(),
            'groups' => $groups,
            'filters' => [
                'q' => $q,
                'group_id' => $groupId,
                'status' => $status,
            ],
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
        $group = Group::findOrFail($data['group_id']);
        if ($this->campusId() && (int) $student->campus_id !== (int) $this->campusId()) {
            abort(403);
        }
        if ($this->campusId() && (int) $group->campus_id !== (int) $this->campusId()) {
            abort(403);
        }
        if ($group->status !== 'active') {
            throw ValidationException::withMessages([
                'group_id' => 'No se puede inscribir en un grupo inactivo.',
            ]);
        }
        $this->assertStudentCanEnrollByGrade($student, $group);
        $alreadyInGroup = Enrollment::query()
            ->where('group_id', $group->id)
            ->where('student_id', $student->id)
            ->exists();
        if (! $alreadyInGroup && $group->capacity) {
            $currentSeats = Enrollment::query()->where('group_id', $group->id)->count();
            if ($currentSeats >= (int) $group->capacity) {
                throw ValidationException::withMessages([
                    'group_id' => 'El grupo alcanzó su capacidad máxima.',
                ]);
            }
        }
        $data['campus_id'] = $this->campusId() ?: $student->campus_id;

        $enrollment = Enrollment::updateOrCreate(
            ['student_id' => $data['student_id'], 'group_id' => $data['group_id']],
            $data,
        );
        AuditTrail::log($request, 'enrollment.upsert', $enrollment, $data);
        app(EnrollmentBillingService::class)->createTuitionCharge($enrollment, $request);

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
        $group = Group::findOrFail($data['group_id']);
        if ($this->campusId() && (int) $student->campus_id !== (int) $this->campusId()) {
            abort(403);
        }
        if ($this->campusId() && (int) $group->campus_id !== (int) $this->campusId()) {
            abort(403);
        }
        if ($group->status !== 'active') {
            throw ValidationException::withMessages([
                'group_id' => 'No se puede inscribir en un grupo inactivo.',
            ]);
        }
        $this->assertStudentCanEnrollByGrade($student, $group);
        $duplicate = Enrollment::query()
            ->where('group_id', $group->id)
            ->where('student_id', $student->id)
            ->where('id', '!=', $enrollment->id)
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages([
                'group_id' => 'Ese alumno ya está inscrito en el grupo seleccionado.',
            ]);
        }

        if ($group->capacity) {
            $currentSeats = Enrollment::query()
                ->where('group_id', $group->id)
                ->when((int) $enrollment->group_id === (int) $group->id, fn (Builder $builder) => $builder->where('id', '!=', $enrollment->id))
                ->count();
            if ($currentSeats >= (int) $group->capacity) {
                throw ValidationException::withMessages([
                    'group_id' => 'El grupo alcanzó su capacidad máxima.',
                ]);
            }
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

    private function assertStudentCanEnrollByGrade(Student $student, Group $targetGroup): void
    {
        $targetCourse = $targetGroup->course()->with(['programLevel', 'courseLevel'])->first();
        if (! $targetCourse) {
            return;
        }

        $candidatePreviousEnrollment = Enrollment::query()
            ->where('student_id', $student->id)
            ->with(['group.course.programLevel', 'group.course.courseLevel'])
            ->orderByDesc('enrolled_at')
            ->orderByDesc('id')
            ->get()
            ->first(function (Enrollment $enrollment) use ($targetCourse): bool {
                $sourceCourse = $enrollment->group?->course;
                if (! $sourceCourse || (int) $sourceCourse->id === (int) $targetCourse->id) {
                    return false;
                }

                if ($sourceCourse->programLevel && $targetCourse->program_level_id) {
                    return (int) ($sourceCourse->programLevel?->nextLevel()?->id ?? 0) === (int) $targetCourse->program_level_id;
                }

                if ($sourceCourse->courseLevel && $targetCourse->course_level_id) {
                    return (int) ($sourceCourse->courseLevel?->nextLevel()?->id ?? 0) === (int) $targetCourse->course_level_id;
                }

                return false;
            });

        if (! $candidatePreviousEnrollment || ! $candidatePreviousEnrollment->group?->course) {
            return;
        }

        $eligibility = RenewalEnrollmentEligibility::evaluateForCourse($student, $candidatePreviousEnrollment->group->course);
        if (! $eligibility['eligible']) {
            throw ValidationException::withMessages([
                'student_id' => 'El alumno no puede inscribirse al siguiente curso porque su evaluación final está en Need Support.',
            ]);
        }
    }
}
