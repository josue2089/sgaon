<?php

namespace App\Http\Controllers;

use App\Models\AcademicLevel;
use App\Models\AttendanceRecord;
use App\Models\Campus;
use App\Models\Charge;
use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentController extends Controller
{
    private function campusId(): ?int
    {
        return request()->user()?->campus_id;
    }

    public function index(Request $request): View
    {
        $query = Student::query()
            ->with(['campus', 'enrollments.group.course.level'])
            ->latest();

        $baseStatsQuery = Student::query();
        if ($this->campusId()) {
            $query->where('campus_id', $this->campusId());
            $baseStatsQuery->where('campus_id', $this->campusId());
        }

        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $query->where(function (Builder $builder) use ($q) {
                $builder
                    ->whereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$q}%"])
                    ->orWhere('email', 'like', "%{$q}%");
            });
        }

        $status = (string) $request->query('status', '');
        if ($status !== '') {
            $query->where('status', $status);
        }

        $levelFilter = (string) $request->query('level', '');
        if ($levelFilter !== '') {
            $query->whereHas('enrollments.group.course.level', function (Builder $builder) use ($levelFilter) {
                $builder
                    ->where('code', $levelFilter)
                    ->orWhere('name', $levelFilter);
            });
        }

        $paymentStatus = (string) $request->query('payment_status', '');
        if ($paymentStatus === 'overdue') {
            $query->whereHas('charges', fn (Builder $builder) => $builder->where('status', 'overdue'));
        } elseif ($paymentStatus === 'pending') {
            $query->whereHas('charges', fn (Builder $builder) => $builder->whereIn('status', ['pending', 'partial']));
        } elseif ($paymentStatus === 'paid') {
            $query
                ->whereHas('charges')
                ->whereDoesntHave('charges', fn (Builder $builder) => $builder->whereIn('status', ['pending', 'partial', 'overdue']));
        } elseif ($paymentStatus === 'no_charges') {
            $query->whereDoesntHave('charges');
        }

        $students = $query->paginate(20)->withQueryString();

        $studentIds = $students->getCollection()->pluck('id')->values();
        $attendanceByStudent = collect();
        if ($studentIds->isNotEmpty()) {
            $attendanceByStudent = AttendanceRecord::query()
                ->join('enrollments', 'enrollments.id', '=', 'attendance_records.enrollment_id')
                ->whereIn('enrollments.student_id', $studentIds)
                ->selectRaw(
                    "enrollments.student_id, ROUND((SUM(CASE WHEN attendance_records.status = 'present' THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0)) * 100) as attendance_rate"
                )
                ->groupBy('enrollments.student_id')
                ->pluck('attendance_rate', 'enrollments.student_id');
        }

        $chargesByStudent = collect();
        if ($studentIds->isNotEmpty()) {
            $chargesByStudent = Charge::query()
                ->whereIn('student_id', $studentIds)
                ->select('student_id', 'status')
                ->get()
                ->groupBy('student_id');
        }

        $paymentStatusByStudent = $students->getCollection()->mapWithKeys(function (Student $student) use ($chargesByStudent) {
            $statuses = collect($chargesByStudent->get($student->id, []))->pluck('status');
            if ($statuses->isEmpty()) {
                return [$student->id => 'no_charges'];
            }
            if ($statuses->contains('overdue')) {
                return [$student->id => 'overdue'];
            }
            if ($statuses->contains(fn ($status) => in_array($status, ['pending', 'partial'], true))) {
                return [$student->id => 'pending'];
            }

            return [$student->id => 'paid'];
        });

        $attendanceRate = Enrollment::query()
            ->when($this->campusId(), fn (Builder $builder) => $builder->where('campus_id', $this->campusId()))
            ->where('enrollments.status', 'active')
            ->join('attendance_records', 'attendance_records.enrollment_id', '=', 'enrollments.id')
            ->selectRaw(
                "ROUND((SUM(CASE WHEN attendance_records.status = 'present' THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0)) * 100) as attendance_rate"
            )
            ->value('attendance_rate');

        $levels = AcademicLevel::query()
            ->when($this->campusId(), fn (Builder $builder) => $builder->where('campus_id', $this->campusId()))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['code', 'name']);

        return view('students.index', [
            'students' => $students,
            'attendanceByStudent' => $attendanceByStudent,
            'paymentStatusByStudent' => $paymentStatusByStudent,
            'summary' => [
                'total' => (clone $baseStatsQuery)->count(),
                'active' => (clone $baseStatsQuery)->where('status', 'active')->count(),
                'inactive' => (clone $baseStatsQuery)->where('status', '!=', 'active')->count(),
                'attendance_rate' => is_null($attendanceRate) ? null : (int) $attendanceRate,
            ],
            'filters' => [
                'q' => $q,
                'level' => $levelFilter,
                'status' => $status,
                'payment_status' => $paymentStatus,
            ],
            'levels' => $levels,
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
