<?php

namespace App\Http\Controllers;

use App\Models\Campus;
use App\Models\Teacher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeacherController extends Controller
{
    private function campusId(): ?int
    {
        return request()->user()?->campus_id;
    }

    public function index(): View
    {
        $query = Teacher::with('campus')->latest();
        if ($this->campusId()) {
            $query->where('campus_id', $this->campusId());
        }

        return view('teachers.index', [
            'teachers' => $query->paginate(20),
        ]);
    }

    public function create(): View
    {
        $campuses = Campus::orderBy('name');
        if ($this->campusId()) {
            $campuses->where('id', $this->campusId());
        }

        return view('teachers.create', ['campuses' => $campuses->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'campus_id' => ['required', 'exists:campuses,id'],
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'document_id' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:40'],
            'status' => ['required', 'string'],
        ]);

        if ($this->campusId()) {
            $data['campus_id'] = $this->campusId();
        }

        Teacher::create($data);

        return redirect()->route('teachers.index')->with('success', 'Profesor creado.');
    }

    public function edit(Teacher $teacher): View
    {
        $campuses = Campus::orderBy('name');
        if ($this->campusId()) {
            $campuses->where('id', $this->campusId());
        }

        return view('teachers.edit', [
            'teacher' => $teacher,
            'campuses' => $campuses->get(),
        ]);
    }

    public function update(Request $request, Teacher $teacher): RedirectResponse
    {
        $data = $request->validate([
            'campus_id' => ['required', 'exists:campuses,id'],
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'document_id' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:40'],
            'status' => ['required', 'string'],
        ]);

        if ($this->campusId()) {
            $data['campus_id'] = $this->campusId();
        }

        $teacher->update($data);

        return redirect()->route('teachers.index')->with('success', 'Profesor actualizado.');
    }

    public function destroy(Teacher $teacher): RedirectResponse
    {
        $teacher->delete();

        return redirect()->route('teachers.index')->with('success', 'Profesor eliminado.');
    }
}
