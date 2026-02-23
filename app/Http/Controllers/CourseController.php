<?php

namespace App\Http\Controllers;

use App\Models\AcademicLevel;
use App\Models\Campus;
use App\Models\Course;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CourseController extends Controller
{
    private function campusId(): ?int
    {
        return request()->user()?->campus_id;
    }

    public function index(): View
    {
        $query = Course::with(['campus', 'level'])->latest();
        if ($this->campusId()) {
            $query->where('campus_id', $this->campusId());
        }

        return view('courses.index', [
            'courses' => $query->paginate(20),
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
