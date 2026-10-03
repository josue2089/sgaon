<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Student;
use App\Support\AuditTrail;
use App\Support\GradeAuthorization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Observaciones / recomendaciones del docente para los alumnos de un curso extracurricular.
 */
class CourseObservationController extends Controller
{
    public function edit(Request $request, Course $course): View
    {
        GradeAuthorization::ensureCanManageCourse($request->user(), $course);

        return view('courses.observations', [
            'course' => $course->load('teacher'),
            'students' => $this->students($course),
        ]);
    }

    public function update(Request $request, Course $course): RedirectResponse
    {
        GradeAuthorization::ensureCanManageCourse($request->user(), $course);

        $data = $request->validate([
            'observations' => ['array'],
            'observations.*' => ['nullable', 'string', 'max:5000'],
        ]);

        $students = $this->students($course)->keyBy('id');

        foreach ($data['observations'] ?? [] as $studentId => $text) {
            $student = $students->get((int) $studentId);
            if (! $student || $student->teacher_observations === $text) {
                continue;
            }

            $student->update(['teacher_observations' => $text]);
            AuditTrail::log($request, 'student.teacher_observations.update', $student, [
                'course_id' => $course->id,
                'teacher_observations' => $text,
            ]);
        }

        return redirect()->route('courses.observations.edit', $course)->with('success', 'Observaciones guardadas.');
    }

    /**
     * @return Collection<int, Student>
     */
    private function students(Course $course): Collection
    {
        return Student::query()
            ->whereHas('enrollments', fn ($query) => $query
                ->where('status', 'active')
                ->whereHas('group', fn ($group) => $group->where('course_id', $course->id)))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();
    }
}
