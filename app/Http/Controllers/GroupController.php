<?php

namespace App\Http\Controllers;

use App\Models\Campus;
use App\Models\Course;
use App\Models\Group;
use App\Models\Teacher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GroupController extends Controller
{
    private function campusId(): ?int
    {
        return request()->user()?->campus_id;
    }

    public function index(): View
    {
        $query = Group::with(['campus', 'course', 'teacher'])->latest();
        if ($this->campusId()) {
            $query->where('campus_id', $this->campusId());
        }

        return view('groups.index', [
            'groups' => $query->paginate(20),
        ]);
    }

    public function create(): View
    {
        $campuses = Campus::orderBy('name');
        $courses = Course::orderBy('name');
        $teachers = Teacher::orderBy('first_name');
        if ($this->campusId()) {
            $campuses->where('id', $this->campusId());
            $courses->where('campus_id', $this->campusId());
            $teachers->where('campus_id', $this->campusId());
        }

        return view('groups.create', [
            'campuses' => $campuses->get(),
            'courses' => $courses->get(),
            'teachers' => $teachers->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'campus_id' => ['required', 'exists:campuses,id'],
            'course_id' => ['required', 'exists:courses,id'],
            'teacher_id' => ['nullable', 'exists:teachers,id'],
            'name' => ['required', 'string', 'max:150'],
            'period' => ['nullable', 'string', 'max:100'],
            'schedule' => ['nullable', 'string', 'max:150'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'status' => ['required', 'string'],
        ]);

        if ($this->campusId()) {
            $data['campus_id'] = $this->campusId();
        }

        Group::create($data);

        return redirect()->route('groups.index')->with('success', 'Grupo creado.');
    }

    public function edit(Group $group): View
    {
        $campuses = Campus::orderBy('name');
        $courses = Course::orderBy('name');
        $teachers = Teacher::orderBy('first_name');
        if ($this->campusId()) {
            $campuses->where('id', $this->campusId());
            $courses->where('campus_id', $this->campusId());
            $teachers->where('campus_id', $this->campusId());
        }

        return view('groups.edit', [
            'group' => $group,
            'campuses' => $campuses->get(),
            'courses' => $courses->get(),
            'teachers' => $teachers->get(),
        ]);
    }

    public function update(Request $request, Group $group): RedirectResponse
    {
        $data = $request->validate([
            'campus_id' => ['required', 'exists:campuses,id'],
            'course_id' => ['required', 'exists:courses,id'],
            'teacher_id' => ['nullable', 'exists:teachers,id'],
            'name' => ['required', 'string', 'max:150'],
            'period' => ['nullable', 'string', 'max:100'],
            'schedule' => ['nullable', 'string', 'max:150'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'status' => ['required', 'string'],
        ]);

        if ($this->campusId()) {
            $data['campus_id'] = $this->campusId();
        }

        $group->update($data);

        return redirect()->route('groups.index')->with('success', 'Grupo actualizado.');
    }

    public function destroy(Group $group): RedirectResponse
    {
        $group->delete();

        return redirect()->route('groups.index')->with('success', 'Grupo eliminado.');
    }
}
