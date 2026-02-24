<?php

namespace App\Http\Controllers;

use App\Models\Campus;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeacherController extends Controller
{
    private function campusId(): ?int
    {
        return request()->user()?->campus_id;
    }

    public function index(Request $request): View
    {
        $query = Teacher::query()->with('campus')->latest();
        $baseStatsQuery = Teacher::query();
        if ($this->campusId()) {
            $query->where('campus_id', $this->campusId());
            $baseStatsQuery->where('campus_id', $this->campusId());
        }

        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $query->where(function (Builder $builder) use ($q) {
                $builder
                    ->whereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$q}%"])
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%");
            });
        }

        $status = (string) $request->query('status', '');
        if ($status !== '') {
            $query->where('status', $status);
        }

        $specialty = trim((string) $request->query('specialty', ''));
        if ($specialty !== '') {
            $query->whereHas('groups.course', fn (Builder $builder) => $builder->where('name', $specialty));
        }

        $teachers = $query->paginate(20)->withQueryString();
        $teacherIds = $teachers->getCollection()->pluck('id')->values();

        $studentsByTeacher = collect();
        if ($teacherIds->isNotEmpty()) {
            $studentsByTeacher = DB::table('groups')
                ->join('enrollments', 'enrollments.group_id', '=', 'groups.id')
                ->whereIn('groups.teacher_id', $teacherIds)
                ->selectRaw('groups.teacher_id, COUNT(DISTINCT enrollments.student_id) as students_count')
                ->groupBy('groups.teacher_id')
                ->pluck('students_count', 'groups.teacher_id');
        }

        $studentsTotal = DB::table('groups')
            ->join('enrollments', 'enrollments.group_id', '=', 'groups.id')
            ->when($this->campusId(), fn ($builder) => $builder->where('groups.campus_id', $this->campusId()))
            ->selectRaw('COUNT(DISTINCT enrollments.student_id) as students_count')
            ->value('students_count');

        $specialties = DB::table('groups')
            ->join('courses', 'courses.id', '=', 'groups.course_id')
            ->when($this->campusId(), fn ($builder) => $builder->where('groups.campus_id', $this->campusId()))
            ->distinct()
            ->orderBy('courses.name')
            ->pluck('courses.name');

        return view('teachers.index', [
            'teachers' => $teachers,
            'studentsByTeacher' => $studentsByTeacher,
            'specialties' => $specialties,
            'summary' => [
                'total' => (clone $baseStatsQuery)->count(),
                'active' => (clone $baseStatsQuery)->where('status', 'active')->count(),
                'students_total' => (int) $studentsTotal,
            ],
            'filters' => [
                'q' => $q,
                'status' => $status,
                'specialty' => $specialty,
            ],
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
