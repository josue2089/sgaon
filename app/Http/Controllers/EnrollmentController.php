<?php

namespace App\Http\Controllers;

use App\Models\Enrollment;
use App\Models\Group;
use App\Models\Student;
use App\Support\RenewalEnrollmentEligibility;
use App\Support\AuditTrail;
use App\Support\StudentSearch;
use App\Services\EnrollmentBillingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class EnrollmentController extends Controller
{
    use \App\Http\Controllers\Concerns\ScopesCampusAccess;

    public function index(Request $request): View
    {
        $query = Enrollment::with(['student', 'group.course'])->latest();
        if ($this->campusScoped()) {
            $this->applyCampusScope($query, 'campus_id');
        }

        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $query->where(function (Builder $builder) use ($q) {
                $builder
                    ->whereHas('student', fn (Builder $studentBuilder) => StudentSearch::applyTerm($studentBuilder, $q))
                    ->orWhereHas('group', fn (Builder $groupBuilder) => $groupBuilder
                        ->where('name', 'like', "%{$q}%")
                        ->orWhereHas('course', fn (Builder $courseBuilder) => $courseBuilder->where('name', 'like', "%{$q}%")));
            });
        }

        $groupId = (string) $request->query('group_id', '');
        if ($groupId !== '') {
            $query->where('group_id', $groupId);
        }

        if ($request->query('source') === 'portal') {
            $query->whereNotNull('renewed_from_portal_at');
        }

        $status = (string) $request->query('status', '');
        if ($status !== '') {
            $query->where('status', $status);
        }

        $groups = Group::query()
            ->with('course')
            ->tap(fn ($builder) => $this->applyCampusScope($builder, 'campus_id'))
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
        if ($this->campusScoped()) {
            $this->applyCampusScope($students, 'campus_id');
            $this->applyCampusScope($groups, 'campus_id');
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
            'enrolled_at' => ['nullable', 'date', 'sane_date'],
            'status' => ['required', 'string'],
            'progress' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        $student = Student::findOrFail($data['student_id']);
        $group = Group::findOrFail($data['group_id']);
        if (! $this->canAccessCampus((int) $student->campus_id)) {
            abort(403);
        }
        if (! $this->canAccessCampus((int) $group->campus_id)) {
            abort(403);
        }
        if ($group->status !== 'active') {
            throw ValidationException::withMessages([
                'group_id' => 'No se puede inscribir en un grupo inactivo.',
            ]);
        }
        RenewalEnrollmentEligibility::assertCanEnrollInGroup($student, $group);
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
        $data['campus_id'] = $this->singleAllowedCampusId() ?: $student->campus_id;

        $enrollment = Enrollment::updateOrCreate(
            ['student_id' => $data['student_id'], 'group_id' => $data['group_id']],
            $data,
        );
        AuditTrail::log($request, 'enrollment.upsert', $enrollment, $data);
        app(EnrollmentBillingService::class)->createTuitionCharge($enrollment, $request);

        if ($request->input('return_to') === 'student') {
            return redirect()
                ->to(route('students.show', $student).'#academico')
                ->with('success', 'Inscrito en '.($group->course?->name ?? $group->name).'.');
        }

        return redirect()->route('enrollments.index')->with('success', 'Inscripción guardada.');
    }

    public function edit(Enrollment $enrollment): View
    {
        return view('enrollments.edit', ['enrollment' => $enrollment->load(['student', 'group.course'])]);
    }

    public function update(Request $request, Enrollment $enrollment): RedirectResponse
    {
        // Alumno y curso no se editan: un cambio pasa por "Cambiar de curso", que maneja los cargos. #441
        $statuses = array_values(array_unique(['active', 'inactive', 'completed', $enrollment->status]));
        $data = $request->validate([
            'enrolled_at' => ['nullable', 'date', 'sane_date'],
            'status' => ['required', Rule::in($statuses)],
            'progress' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        $enrollment->update($data);
        AuditTrail::log($request, 'enrollment.update', $enrollment, $data);

        return redirect()->route('enrollments.index')->with('success', 'Inscripción actualizada.');
    }

    public function destroy(Enrollment $enrollment): RedirectResponse
    {
        if (! $this->canAccessCampus((int) $enrollment->campus_id)) {
            abort(403);
        }

        $payload = ['student_id' => $enrollment->student_id, 'group_id' => $enrollment->group_id];
        if (! $enrollment->deleteOrWithdraw('Retirado desde inscripciones')) {
            AuditTrail::log(request(), 'enrollment.withdraw', $enrollment, $payload);

            return redirect()->route('enrollments.index')
                ->with('success', 'La inscripción tiene asistencia o cargos: se marcó como retirada para preservar su historial.');
        }
        AuditTrail::log(request(), 'enrollment.delete', null, $payload);

        return redirect()->route('enrollments.index')->with('success', 'Inscripción eliminada.');
    }
}
