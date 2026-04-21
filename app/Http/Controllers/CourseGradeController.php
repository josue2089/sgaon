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

        $enrollments = Enrollment::query()
            ->where('group_id', $group->id)
            ->where('status', 'active')
            ->get()
            ->keyBy('id');

        if ($enrollments->isEmpty()) {
            return back()->withErrors(['entries' => 'No hay alumnos activos en este curso.']);
        }

        $rules = [
            'evaluated_on' => ['required', 'date'],
            'title' => ['nullable', 'string', 'max:120'],
        ];

        foreach ($enrollments as $id => $enrollment) {
            foreach (GradeRubric::skillToColumnMap() as $column) {
                $rules["entries.{$id}.{$column}"] = ['required', Rule::in(GradeRubric::RATING_VALUES)];
            }
            $rules["entries.{$id}.observations"] = ['nullable', 'string', 'max:2000'];
        }

        $validated = $request->validate($rules);

        $entriesInput = $validated['entries'] ?? [];
        foreach ($enrollments->keys() as $eid) {
            if (! isset($entriesInput[$eid])) {
                return back()->withErrors(['entries' => 'Faltan calificaciones para uno o más alumnos.'])->withInput();
            }
        }

        $campusId = (int) $course->campus_id;
        $userId = $request->user()?->id;

        $createdSet = DB::transaction(function () use ($validated, $entriesInput, $enrollments, $course, $group, $campusId, $userId) {
            $set = GradeEvaluationSet::create([
                'campus_id' => $campusId,
                'course_id' => $course->id,
                'group_id' => $group->id,
                'evaluated_on' => $validated['evaluated_on'],
                'title' => $validated['title'] ?? null,
                'created_by' => $userId,
            ]);

            foreach ($enrollments as $enrollmentId => $enrollment) {
                $row = $entriesInput[$enrollmentId];
                GradeEntry::create([
                    'grade_evaluation_set_id' => $set->id,
                    'campus_id' => $campusId,
                    'enrollment_id' => (int) $enrollmentId,
                    'vocabulary_rating' => $row['vocabulary_rating'],
                    'listening_rating' => $row['listening_rating'],
                    'speaking_rating' => $row['speaking_rating'],
                    'writing_rating' => $row['writing_rating'],
                    'grammar_rating' => $row['grammar_rating'],
                    'observations' => $row['observations'] ?? null,
                ]);
            }

            return $set;
        });

        AuditTrail::log($request, 'grades.set.create', $createdSet, [
            'course_id' => $course->id,
            'evaluated_on' => $validated['evaluated_on'],
        ]);

        return redirect()
            ->route('grade-evaluation-sets.show', $createdSet)
            ->with('success', 'Evaluación registrada.');
    }

    public function show(Request $request, GradeEvaluationSet $gradeEvaluationSet): View
    {
        GradeAuthorization::ensureCanManageEvaluationSet($request->user(), $gradeEvaluationSet);

        $gradeEvaluationSet->load([
            'course.teacher',
            'creator',
            'entries.enrollment.student',
        ]);

        return view('grades.set-show', [
            'set' => $gradeEvaluationSet,
            'ratingLabels' => GradeRubric::RATING_LABELS_ES,
            'skillLabels' => GradeRubric::SKILL_LABELS_ES,
            'skillKeys' => GradeRubric::SKILL_KEYS,
        ]);
    }

    public function edit(Request $request, GradeEvaluationSet $gradeEvaluationSet): View
    {
        GradeAuthorization::ensureCanManageEvaluationSet($request->user(), $gradeEvaluationSet);

        $gradeEvaluationSet->load([
            'course',
            'entries.enrollment.student',
        ]);

        return view('grades.set-edit', [
            'set' => $gradeEvaluationSet,
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

        $gradeEvaluationSet->load(['course.managedGroup', 'entries']);

        $group = $gradeEvaluationSet->course?->managedGroup;
        $enrollmentIds = $gradeEvaluationSet->entries->pluck('enrollment_id')->all();

        $rules = [
            'evaluated_on' => ['required', 'date'],
            'title' => ['nullable', 'string', 'max:120'],
        ];

        foreach ($enrollmentIds as $eid) {
            foreach (GradeRubric::skillToColumnMap() as $column) {
                $rules["entries.{$eid}.{$column}"] = ['required', Rule::in(GradeRubric::RATING_VALUES)];
            }
            $rules["entries.{$eid}.observations"] = ['nullable', 'string', 'max:2000'];
        }

        $validated = $request->validate($rules);

        DB::transaction(function () use ($validated, $gradeEvaluationSet) {
            $gradeEvaluationSet->update([
                'evaluated_on' => $validated['evaluated_on'],
                'title' => $validated['title'] ?? null,
            ]);

            foreach ($validated['entries'] as $enrollmentId => $row) {
                GradeEntry::query()
                    ->where('grade_evaluation_set_id', $gradeEvaluationSet->id)
                    ->where('enrollment_id', (int) $enrollmentId)
                    ->update([
                        'vocabulary_rating' => $row['vocabulary_rating'],
                        'listening_rating' => $row['listening_rating'],
                        'speaking_rating' => $row['speaking_rating'],
                        'writing_rating' => $row['writing_rating'],
                        'grammar_rating' => $row['grammar_rating'],
                        'observations' => $row['observations'] ?? null,
                    ]);
            }
        });

        AuditTrail::log($request, 'grades.set.update', $gradeEvaluationSet, [
            'evaluated_on' => $validated['evaluated_on'],
        ]);

        return redirect()
            ->route('grade-evaluation-sets.show', $gradeEvaluationSet)
            ->with('success', 'Evaluación actualizada.');
    }
}
