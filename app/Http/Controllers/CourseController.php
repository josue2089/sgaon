<?php

namespace App\Http\Controllers;

use App\Models\AcademicLevel;
use App\Models\Campus;
use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CourseController extends Controller
{
    private function campusId(): ?int
    {
        return request()->user()?->campus_id;
    }

    public function index(Request $request): View
    {
        $query = Course::query()->with(['campus', 'level'])->latest();
        if ($this->campusId()) {
            $query->where('campus_id', $this->campusId());
        }

        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $query->where(function (Builder $builder) use ($q) {
                $builder->where('name', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%");
            });
        }

        $statusFilter = (string) $request->query('status', '');
        if ($statusFilter !== '') {
            $query->where('status', $statusFilter);
        }

        $levelFilter = (string) $request->query('level', '');
        if ($levelFilter !== '') {
            $query->whereHas('level', function (Builder $builder) use ($levelFilter) {
                $builder->where('code', $levelFilter)
                    ->orWhere('name', $levelFilter);
            });
        }

        $courses = $query->paginate(20)->withQueryString();

        $coursesCount = Course::query()
            ->when($this->campusId(), fn (Builder $builder) => $builder->where('campus_id', $this->campusId()))
            ->count();
        $studentsQuery = Enrollment::query()
            ->join('groups', 'groups.id', '=', 'enrollments.group_id')
            ->join('courses', 'courses.id', '=', 'groups.course_id');
        if ($this->campusId()) {
            $studentsQuery->where('courses.campus_id', $this->campusId());
        }
        $totalStudents = (clone $studentsQuery)->count();
        $occupancy = null;

        $levelsQuery = AcademicLevel::query()
            ->select('academic_levels.id', 'academic_levels.name', 'academic_levels.code')
            ->selectRaw('COUNT(courses.id) as courses_count')
            ->leftJoin('courses', 'courses.academic_level_id', '=', 'academic_levels.id')
            ->groupBy('academic_levels.id', 'academic_levels.name', 'academic_levels.code')
            ->orderBy('academic_levels.sort_order');
        if ($this->campusId()) {
            $levelsQuery->where('academic_levels.campus_id', $this->campusId());
        }

        return view('courses.index', [
            'courses' => $courses,
            'stats' => [
                'courses' => $coursesCount,
                'students' => $totalStudents,
                'occupancy' => $occupancy,
            ],
            'levelStats' => $levelsQuery->get(),
            'filters' => [
                'q' => $q,
                'status' => $statusFilter,
                'level' => $levelFilter,
            ],
            'levels' => AcademicLevel::query()
                ->when($this->campusId(), fn (Builder $builder) => $builder->where('campus_id', $this->campusId()))
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['code', 'name']),
        ]);
    }

    public function create(): View
    {
        $campuses = Campus::orderBy('name');
        $levels = AcademicLevel::orderBy('name');
        if ($this->campusId()) {
            $campuses->where('id', $this->campusId());
            $levels->where('campus_id', $this->campusId());
        }

        return view('courses.create', [
            'campuses' => $campuses->get(),
            'levels' => $levels->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'campus_id' => ['required', 'exists:campuses,id'],
            'academic_level_id' => ['required', 'exists:academic_levels,id'],
            'name' => ['required', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:60'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'string'],
        ]);

        if ($this->campusId()) {
            $data['campus_id'] = $this->campusId();
        }

        Course::create($data);

        return redirect()->route('courses.index')->with('success', 'Curso creado.');
    }

    public function edit(Course $course): View
    {
        $campuses = Campus::orderBy('name');
        $levels = AcademicLevel::orderBy('name');
        if ($this->campusId()) {
            $campuses->where('id', $this->campusId());
            $levels->where('campus_id', $this->campusId());
        }

        return view('courses.edit', [
            'course' => $course,
            'campuses' => $campuses->get(),
            'levels' => $levels->get(),
        ]);
    }

    public function update(Request $request, Course $course): RedirectResponse
    {
        $data = $request->validate([
            'campus_id' => ['required', 'exists:campuses,id'],
            'academic_level_id' => ['required', 'exists:academic_levels,id'],
            'name' => ['required', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:60'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'string'],
        ]);

        if ($this->campusId()) {
            $data['campus_id'] = $this->campusId();
        }

        $course->update($data);

        return redirect()->route('courses.index')->with('success', 'Curso actualizado.');
    }

    public function destroy(Course $course): RedirectResponse
    {
        $course->delete();

        return redirect()->route('courses.index')->with('success', 'Curso eliminado.');
    }
}
