<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\GradeEntry;
use App\Models\GradeEvaluationSet;
use App\Support\AuditTrail;
use App\Support\GradeAuthorization;
use App\Support\GradeRubric;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CourseGradeController extends Controller
{
    public function index(Request $request, Course $course): View
    {
        GradeAuthorization::ensureCanManageCourse($request->user(), $course);

        $sets = GradeEvaluationSet::query()
            ->where('course_id', $course->id)
            ->with(['creator', 'entries'])
            ->withCount('entries')
            ->latest('evaluated_on')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('grades.course-index', [
            'course' => $course->load(['teacher', 'managedGroup']),
            'sets' => $sets,
        ]);
    }

    public function create(Request $request, Course $course): View|RedirectResponse
    {
        GradeAuthorization::ensureCanManageCourse($request->user(), $course);

        $course->load('managedGroup');
        $group = $course->managedGroup;
        if (! $group) {
            return redirect()
                ->route('courses.show', $course)
                ->withErrors(['grades' => 'Configura el grupo operativo del curso antes de cargar evaluaciones.']);
        }

        $enrollments = Enrollment::query()
            ->where('group_id', $group->id)
            ->where('status', 'active')
            ->with('student')
            ->orderBy('student_id')
            ->get();

        return view('grades.course-create', [
            'course' => $course->load(['teacher']),
            'enrollments' => $enrollments,
            'roster' => $enrollments->sortBy(fn (Enrollment $enrollment) => mb_strtolower($enrollment->student?->full_name ?? ''))
                ->mapWithKeys(fn (Enrollment $enrollment) => [$enrollment->id => ['enrollment' => $enrollment, 'entry' => null]]),
            'ratingOptions' => GradeRubric::RATING_VALUES,
            'ratingLabels' => GradeRubric::RATING_LABELS_ES,
            'skillKeys' => GradeRubric::SKILL_KEYS,
            'skillLabels' => GradeRubric::SKILL_LABELS_ES,
            'skillColumns' => GradeRubric::skillToColumnMap(),
        ]);
    }

    public function store(Request $request, Course $course): RedirectResponse
    {
        GradeAuthorization::ensureCanManageCourse($request->user(), $course);

        $course->load('managedGroup');
        $group = $course->managedGroup;
        if (! $group) {
            return back()->withErrors(['course' => 'Sin grupo operativo.']);
        }

        $enrollmentIds = Enrollment::query()
            ->where('group_id', $group->id)
            ->where('status', 'active')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($enrollmentIds === []) {
            return back()->withErrors(['entries' => 'No hay alumnos activos en este curso.']);
        }

        $validated = $request->validate($this->rules($enrollmentIds));
        $rows = $this->filledRows($validated['entries'] ?? [], $enrollmentIds);
        if ($rows === []) {
            return back()->withErrors(['entries' => 'Carga al menos una nota u observación antes de guardar.'])->withInput();
        }

        $publish = $request->boolean('publish');
        $campusId = (int) $course->campus_id;
        $userId = $request->user()?->id;

        $createdSet = DB::transaction(function () use ($validated, $rows, $course, $group, $campusId, $userId, $publish) {
            $set = GradeEvaluationSet::create([
                'campus_id' => $campusId,
                'course_id' => $course->id,
                'group_id' => $group->id,
                'evaluated_on' => $validated['evaluated_on'],
                'title' => $validated['title'] ?? null,
                'created_by' => $userId,
                'published_at' => $publish ? now() : null,
                'published_by' => $publish ? $userId : null,
            ]);

            foreach ($rows as $enrollmentId => $row) {
                GradeEntry::create(['grade_evaluation_set_id' => $set->id, 'campus_id' => $campusId, 'enrollment_id' => $enrollmentId] + $row);
            }

            return $set;
        });

        AuditTrail::log($request, 'grades.set.create', $createdSet, [
            'course_id' => $course->id,
            'evaluated_on' => $validated['evaluated_on'],
            'published' => $publish,
        ]);

        return redirect()
            ->route('grade-evaluation-sets.show', $createdSet)
            ->with('success', $publish ? 'Evaluación guardada y publicada a las familias.' : 'Borrador guardado. Las familias no lo ven hasta que lo publiques.');
    }

    public function show(Request $request, GradeEvaluationSet $gradeEvaluationSet): View
    {
        GradeAuthorization::ensureCanManageEvaluationSet($request->user(), $gradeEvaluationSet);

        $gradeEvaluationSet->load([
            'course.teacher',
            'creator',
            'publisher',
            'entries.enrollment.student',
        ]);

        $roster = $this->roster($gradeEvaluationSet);

        return view('grades.set-show', [
            'set' => $gradeEvaluationSet,
            'roster' => $roster,
            'completeCount' => $roster->filter(fn (array $item) => $item['entry']?->isComplete())->count(),
            'ratingLabels' => GradeRubric::RATING_LABELS_ES,
            'skillLabels' => GradeRubric::SKILL_LABELS_ES,
            'skillKeys' => GradeRubric::SKILL_KEYS,
        ]);
    }

    public function edit(Request $request, GradeEvaluationSet $gradeEvaluationSet): View
    {
        GradeAuthorization::ensureCanManageEvaluationSet($request->user(), $gradeEvaluationSet);

        $gradeEvaluationSet->load(['course', 'entries']);

        return view('grades.set-edit', [
            'set' => $gradeEvaluationSet,
            'roster' => $this->roster($gradeEvaluationSet),
            'ratingOptions' => GradeRubric::RATING_VALUES,
            'ratingLabels' => GradeRubric::RATING_LABELS_ES,
            'skillKeys' => GradeRubric::SKILL_KEYS,
            'skillLabels' => GradeRubric::SKILL_LABELS_ES,
            'skillColumns' => GradeRubric::skillToColumnMap(),
        ]);
    }

    public function update(Request $request, GradeEvaluationSet $gradeEvaluationSet): RedirectResponse
    {
        GradeAuthorization::ensureCanManageEvaluationSet($request->user(), $gradeEvaluationSet);

        $gradeEvaluationSet->load(['course', 'entries']);
        $roster = $this->roster($gradeEvaluationSet);
        $enrollmentIds = $roster->keys()->map(fn ($id) => (int) $id)->all();

        $validated = $request->validate($this->rules($enrollmentIds));
        $rows = $this->filledRows($validated['entries'] ?? [], $enrollmentIds);
        if ($rows === []) {
            return back()->withErrors(['entries' => 'La evaluación debe tener al menos una nota u observación.'])->withInput();
        }

        $publish = $request->boolean('publish') && ! $gradeEvaluationSet->isPublished();
        $userId = $request->user()?->id;

        DB::transaction(function () use ($validated, $rows, $enrollmentIds, $gradeEvaluationSet, $publish, $userId) {
            $gradeEvaluationSet->update(array_merge([
                'evaluated_on' => $validated['evaluated_on'],
                'title' => $validated['title'] ?? null,
            ], $publish ? ['published_at' => now(), 'published_by' => $userId] : []));

            foreach ($rows as $enrollmentId => $row) {
                GradeEntry::updateOrCreate(
                    ['grade_evaluation_set_id' => $gradeEvaluationSet->id, 'enrollment_id' => $enrollmentId],
                    ['campus_id' => $gradeEvaluationSet->campus_id] + $row,
                );
            }

            // Un alumno al que se le borraron todas las notas deja de tener fila en esta evaluación.
            GradeEntry::query()
                ->where('grade_evaluation_set_id', $gradeEvaluationSet->id)
                ->whereIn('enrollment_id', array_diff($enrollmentIds, array_keys($rows)))
                ->delete();
        });

        AuditTrail::log($request, 'grades.set.update', $gradeEvaluationSet, [
            'evaluated_on' => $validated['evaluated_on'],
            'published' => $publish,
        ]);

        return redirect()
            ->route('grade-evaluation-sets.show', $gradeEvaluationSet)
            ->with('success', $publish ? 'Evaluación guardada y publicada a las familias.' : 'Evaluación actualizada.');
    }

    /** #447: publica la evaluación; desde ese momento la ven los alumnos y sus representantes. */
    public function publish(Request $request, GradeEvaluationSet $gradeEvaluationSet): RedirectResponse
    {
        GradeAuthorization::ensureCanManageEvaluationSet($request->user(), $gradeEvaluationSet);

        if (! $gradeEvaluationSet->entries()->exists()) {
            return back()->withErrors(['publish' => 'No hay notas cargadas para publicar.']);
        }

        if (! $gradeEvaluationSet->isPublished()) {
            $gradeEvaluationSet->update(['published_at' => now(), 'published_by' => $request->user()?->id]);
            AuditTrail::log($request, 'grades.set.publish', $gradeEvaluationSet, ['course_id' => $gradeEvaluationSet->course_id]);
        }

        return back()->with('success', 'Evaluación publicada. Ya la ven los alumnos y sus representantes.');
    }

    /** Vuelve a borrador: las familias dejan de verla. */
    public function unpublish(Request $request, GradeEvaluationSet $gradeEvaluationSet): RedirectResponse
    {
        GradeAuthorization::ensureCanManageEvaluationSet($request->user(), $gradeEvaluationSet);

        if ($gradeEvaluationSet->isPublished()) {
            $gradeEvaluationSet->update(['published_at' => null, 'published_by' => null]);
            AuditTrail::log($request, 'grades.set.unpublish', $gradeEvaluationSet, ['course_id' => $gradeEvaluationSet->course_id]);
        }

        return back()->with('success', 'La evaluación volvió a borrador. Las familias ya no la ven.');
    }

    /**
     * Alumnos de la evaluación: los que ya tienen nota más los activos del grupo, para completar lo que faltó.
     *
     * @return Collection<int, array{enrollment: Enrollment, entry: ?GradeEntry}>
     */
    private function roster(GradeEvaluationSet $set): Collection
    {
        $entries = $set->entries->keyBy('enrollment_id');
        $groupId = $set->group_id ?? $set->course?->managed_group_id;

        $enrollments = Enrollment::query()
            ->with('student')
            ->where(function ($query) use ($entries, $groupId) {
                $query->whereIn('id', $entries->keys()->all() ?: [0]);
                if ($groupId) {
                    $query->orWhere(fn ($active) => $active->where('group_id', $groupId)->where('status', 'active'));
                }
            })
            ->get()
            ->sortBy(fn (Enrollment $enrollment) => mb_strtolower($enrollment->student?->full_name ?? ''));

        return $enrollments->mapWithKeys(fn (Enrollment $enrollment) => [
            $enrollment->id => ['enrollment' => $enrollment, 'entry' => $entries->get($enrollment->id)],
        ]);
    }

    /**
     * Cada rubro es opcional: se guarda lo que haya.
     *
     * @param  list<int>  $enrollmentIds
     */
    private function rules(array $enrollmentIds): array
    {
        $rules = [
            'evaluated_on' => ['required', 'date', 'sane_date'],
            'title' => ['nullable', 'string', 'max:120'],
            'entries' => ['array'],
        ];

        foreach ($enrollmentIds as $id) {
            foreach (GradeRubric::skillToColumnMap() as $column) {
                $rules["entries.{$id}.{$column}"] = ['nullable', Rule::in(GradeRubric::RATING_VALUES)];
            }
            $rules["entries.{$id}.observations"] = ['nullable', 'string', 'max:2000'];
        }

        return $rules;
    }

    /**
     * Filas con al menos un rubro u observación, solo de los alumnos permitidos.
     *
     * @param  list<int>  $enrollmentIds
     * @return array<int, array<string, ?string>>
     */
    private function filledRows(array $input, array $enrollmentIds): array
    {
        $rows = [];
        foreach ($enrollmentIds as $id) {
            $raw = $input[$id] ?? [];
            $row = [];
            foreach (GradeRubric::skillToColumnMap() as $column) {
                $row[$column] = filled($raw[$column] ?? null) ? (string) $raw[$column] : null;
            }
            $row['observations'] = filled($raw['observations'] ?? null) ? trim((string) $raw['observations']) : null;

            if (array_filter($row, fn ($value) => $value !== null) !== []) {
                $rows[$id] = $row;
            }
        }

        return $rows;
    }
}
