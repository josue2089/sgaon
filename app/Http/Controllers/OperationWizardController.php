<?php

namespace App\Http\Controllers;

use App\Models\AcademicLevel;
use App\Models\Campus;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\Period;
use App\Models\ScheduleTemplate;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OperationWizardController extends Controller
{
    private function groupPeriods(): array
    {
        return Period::query()
            ->when($this->campusId(), fn (Builder $builder) => $builder->where('campus_id', $this->campusId()))
            ->where('status', 'active')
            ->orderBy('code')
            ->pluck('code')
            ->all();
    }

    private function groupSchedules(): array
    {
        return ScheduleTemplate::query()
            ->when($this->campusId(), fn (Builder $builder) => $builder->where('campus_id', $this->campusId()))
            ->where('status', 'active')
            ->get()
            ->pluck('display_label')
            ->all();
    }

    private function groupStatuses(): array
    {
        return config('academic.group_statuses', ['active', 'inactive']);
    }

    private function campusId(): ?int
    {
        return request()->user()?->isMasterAdmin() ? null : request()->user()?->campus_id;
    }

    public function index(Request $request): View
    {
        $campusId = $this->campusId();
        $courseId = $request->integer('course_id') ?: null;
        $groupId = $request->integer('group_id') ?: null;
        $sessionId = $request->integer('session_id') ?: null;

        $course = Course::query()
            ->when($campusId, fn (Builder $builder) => $builder->where('campus_id', $campusId))
            ->find($courseId);

        $group = Group::query()
            ->with(['course', 'teacher'])
            ->when($campusId, fn (Builder $builder) => $builder->where('campus_id', $campusId))
            ->when($course?->id, fn (Builder $builder) => $builder->where('course_id', $course->id))
            ->find($groupId);

        $session = ClassSession::query()
            ->with('group.course')
            ->when($campusId, fn (Builder $builder) => $builder->where('campus_id', $campusId))
            ->when($group?->id, fn (Builder $builder) => $builder->where('group_id', $group->id))
            ->find($sessionId);

        $studentsQuery = Student::query()->orderBy('first_name')->orderBy('last_name');
        if ($campusId) {
            $studentsQuery->where('campus_id', $campusId);
        }

        $enrolledStudentIds = collect();
        if ($group?->id) {
            $enrolledStudentIds = Enrollment::query()
                ->where('group_id', $group->id)
                ->pluck('student_id');
        }

        return view('operations.wizard', [
            'campuses' => Campus::query()
                ->when($campusId, fn (Builder $builder) => $builder->where('id', $campusId))
                ->orderBy('name')
                ->get(),
            'levels' => AcademicLevel::query()
                ->when($campusId, fn (Builder $builder) => $builder->where('campus_id', $campusId))
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'teachers' => Teacher::query()
                ->when($campusId, fn (Builder $builder) => $builder->where('campus_id', $campusId))
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get(),
            'students' => $studentsQuery->get(),
            'courses' => Course::query()
                ->when($campusId, fn (Builder $builder) => $builder->where('campus_id', $campusId))
                ->latest()
                ->take(25)
                ->get(),
            'groups' => Group::query()
                ->with('course')
                ->when($campusId, fn (Builder $builder) => $builder->where('campus_id', $campusId))
                ->when($course?->id, fn (Builder $builder) => $builder->where('course_id', $course->id))
                ->latest()
                ->take(25)
                ->get(),
            'sessions' => ClassSession::query()
                ->with('group.course')
                ->when($campusId, fn (Builder $builder) => $builder->where('campus_id', $campusId))
                ->when($group?->id, fn (Builder $builder) => $builder->where('group_id', $group->id))
                ->latest('session_date')
                ->take(25)
                ->get(),
            'selected' => [
                'course' => $course,
                'group' => $group,
                'session' => $session,
                'enrolled_student_ids' => $enrolledStudentIds,
            ],
            'periodOptions' => $this->groupPeriods(),
            'scheduleOptions' => $this->groupSchedules(),
            'statusOptions' => $this->groupStatuses(),
        ]);
    }

    public function storeCourse(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'campus_id' => ['required', 'exists:campuses,id'],
            'academic_level_id' => ['required', 'exists:academic_levels,id'],
            'name' => ['required', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:60'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        if ($this->campusId()) {
            $data['campus_id'] = $this->campusId();
        }

        $course = Course::create($data);

        return redirect()
            ->route('operations.wizard', ['course_id' => $course->id])
            ->with('success', 'Paso 1 completado: curso creado.');
    }

    public function storeGroup(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'course_id' => ['required', 'exists:courses,id'],
            'teacher_id' => ['nullable', 'exists:teachers,id'],
            'name' => ['required', 'string', 'max:150'],
            'period' => ['nullable', Rule::in($this->groupPeriods())],
            'schedule' => ['nullable', Rule::in($this->groupSchedules())],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'status' => ['required', Rule::in($this->groupStatuses())],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $course = Course::query()
            ->when($this->campusId(), fn (Builder $builder) => $builder->where('campus_id', $this->campusId()))
            ->findOrFail($data['course_id']);

        $data['campus_id'] = $this->campusId() ?: $course->campus_id;
        $group = Group::create($data);

        return redirect()
            ->route('operations.wizard', ['course_id' => $course->id, 'group_id' => $group->id])
            ->with('success', 'Paso 2 completado: grupo creado.');
    }

    public function storeSession(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'group_id' => ['required', 'exists:groups,id'],
            'session_date' => ['required', 'date'],
            'starts_at' => ['nullable', 'date_format:H:i'],
            'ends_at' => ['nullable', 'date_format:H:i'],
            'topic' => ['nullable', 'string'],
        ]);

        $group = Group::query()
            ->with('course')
            ->when($this->campusId(), fn (Builder $builder) => $builder->where('campus_id', $this->campusId()))
            ->findOrFail($data['group_id']);

        if ($group->status !== 'active') {
            throw ValidationException::withMessages([
                'group_id' => 'No se pueden crear sesiones para grupos inactivos.',
            ]);
        }
        if ($group->start_date && $data['session_date'] < $group->start_date->toDateString()) {
            throw ValidationException::withMessages([
                'session_date' => 'La sesión no puede ser antes de la fecha de inicio del grupo.',
            ]);
        }
        if ($group->end_date && $data['session_date'] > $group->end_date->toDateString()) {
            throw ValidationException::withMessages([
                'session_date' => 'La sesión no puede ser después de la fecha de fin del grupo.',
            ]);
        }

        $data['campus_id'] = $this->campusId() ?: $group->campus_id;
        $session = ClassSession::create($data);

        return redirect()
            ->route('operations.wizard', [
                'course_id' => $group->course_id,
                'group_id' => $group->id,
                'session_id' => $session->id,
            ])
            ->with('success', 'Paso 3 completado: sesión creada.');
    }

    public function storeEnrollment(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'group_id' => ['required', 'exists:groups,id'],
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['required', 'exists:students,id'],
            'enrolled_at' => ['nullable', 'date'],
            'status' => ['required', 'in:active,inactive,completed,withdrawn'],
            'progress' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        $group = Group::query()
            ->with('course')
            ->when($this->campusId(), fn (Builder $builder) => $builder->where('campus_id', $this->campusId()))
            ->findOrFail($data['group_id']);

        if ($group->status !== 'active') {
            throw ValidationException::withMessages([
                'group_id' => 'No se puede inscribir en un grupo inactivo.',
            ]);
        }

        $selectedStudentIds = collect($data['student_ids'])->unique()->values();
        $existingStudentIds = Enrollment::query()
            ->where('group_id', $group->id)
            ->whereIn('student_id', $selectedStudentIds)
            ->pluck('student_id');
        $newStudentsCount = $selectedStudentIds->diff($existingStudentIds)->count();
        if ($group->capacity) {
            $currentSeats = Enrollment::query()->where('group_id', $group->id)->count();
            if (($currentSeats + $newStudentsCount) > (int) $group->capacity) {
                throw ValidationException::withMessages([
                    'student_ids' => 'La selección supera la capacidad disponible del grupo.',
                ]);
            }
        }

        $createdOrUpdated = 0;
        foreach ($selectedStudentIds as $studentId) {
            $student = Student::query()
                ->when($this->campusId(), fn (Builder $builder) => $builder->where('campus_id', $this->campusId()))
                ->findOrFail($studentId);

            Enrollment::updateOrCreate(
                ['student_id' => $student->id, 'group_id' => $group->id],
                [
                    'campus_id' => $this->campusId() ?: $student->campus_id,
                    'enrolled_at' => $data['enrolled_at'] ?? null,
                    'status' => $data['status'],
                    'progress' => $data['progress'] ?? 0,
                    'notes' => $data['notes'] ?? null,
                ]
            );
            $createdOrUpdated++;
        }

        return redirect()
            ->route('operations.wizard', ['course_id' => $group->course_id, 'group_id' => $group->id])
            ->with('success', "Paso 4 completado: {$createdOrUpdated} inscripción(es) guardada(s).");
    }
}
