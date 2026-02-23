<?php

namespace App\Http\Controllers;

use App\Models\Campus;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentController extends Controller
{
    private function campusId(): ?int
    {
        return request()->user()?->campus_id;
    }

    public function index(): View
    {
        $query = Student::with('campus')->latest();
        if ($this->campusId()) {
            $query->where('campus_id', $this->campusId());
        }

        return view('students.index', [
            'students' => $query->paginate(20),
        ]);
    }

    public function create(): View
    {
        $campuses = Campus::orderBy('name');
        if ($this->campusId()) {
            $campuses->where('id', $this->campusId());
        }

        return view('students.create', ['campuses' => $campuses->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'campus_id' => ['required', 'exists:campuses,id'],
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'document_id' => ['nullable', 'string', 'max:80'],
            'birth_date' => ['nullable', 'date'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string'],
            'status' => ['required', 'string'],
            'enrollment_date' => ['nullable', 'date'],
        ]);

        if ($this->campusId()) {
            $data['campus_id'] = $this->campusId();
        }

        Student::create($data);

        return redirect()->route('students.index')->with('success', 'Alumno creado.');
    }

    public function edit(Student $student): View
    {
        $campuses = Campus::orderBy('name');
        if ($this->campusId()) {
            $campuses->where('id', $this->campusId());
        }

        return view('students.edit', [
            'student' => $student,
            'campuses' => $campuses->get(),
        ]);
    }

    public function update(Request $request, Student $student): RedirectResponse
    {
        $data = $request->validate([
            'campus_id' => ['required', 'exists:campuses,id'],
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'document_id' => ['nullable', 'string', 'max:80'],
            'birth_date' => ['nullable', 'date'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string'],
            'status' => ['required', 'string'],
            'enrollment_date' => ['nullable', 'date'],
        ]);

        if ($this->campusId()) {
            $data['campus_id'] = $this->campusId();
        }

        $student->update($data);

        return redirect()->route('students.index')->with('success', 'Alumno actualizado.');
    }

    public function destroy(Student $student): RedirectResponse
    {
        $student->delete();

        return redirect()->route('students.index')->with('success', 'Alumno eliminado.');
    }
}
