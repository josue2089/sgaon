<?php

namespace App\Http\Controllers;

use App\Models\Campus;
use App\Models\Course;
use App\Models\Group;
use App\Models\Period;
use App\Models\ScheduleTemplate;
use App\Models\Teacher;
use App\Models\AuditLog;
use App\Support\AuditTrail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GroupController extends Controller
{
    private function groupPeriods(): array
    {
        return Period::query()
            ->when($this->campusId(), fn (Builder $builder) => $builder->where('campus_id', $this->campusId()))
            ->where('status', 'active')
            ->orderBy('code')
            ->pluck('code')
            ->all();
    }

    private function groupSchedules(): array
    {
        return ScheduleTemplate::query()
            ->when($this->campusId(), fn (Builder $builder) => $builder->where('campus_id', $this->campusId()))
            ->where('status', 'active')
            ->get()
            ->pluck('display_label')
            ->all();
    }

    private function groupStatuses(): array
    {
        return config('academic.group_statuses', ['active', 'inactive']);
    }

    private function campusId(): ?int
    {
        return \App\Support\CampusScope::campusIdFor(request()->user());
    }

    public function index(Request $request): View|StreamedResponse
    {
        $query = Group::with(['campus', 'course', 'teacher'])->withCount('enrollments')->latest();
        if ($this->campusId()) {
            $query->where('campus_id', $this->campusId());
        }

        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $query->where(function (Builder $builder) use ($q) {
                $builder
                    ->where('groups.name', 'like', "%{$q}%")
                    ->orWhere('groups.period', 'like', "%{$q}%")
                    ->orWhere('groups.schedule', 'like', "%{$q}%")
                    ->orWhereHas('course', fn (Builder $courseBuilder) => $courseBuilder->where('name', 'like', "%{$q}%"))
                    ->orWhereHas('teacher', fn (Builder $teacherBuilder) => $teacherBuilder
                        ->whereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$q}%"]));
            });
        }

        $courseId = (string) $request->query('course_id', '');
        if ($courseId !== '') {
            $query->where('course_id', $courseId);
        }

        $teacherId = (string) $request->query('teacher_id', '');
        if ($teacherId !== '') {
            if ($teacherId === 'unassigned') {
                $query->whereNull('teacher_id');
            } else {
                $query->where('teacher_id', $teacherId);
            }
        }

        $status = (string) $request->query('status', '');
        if ($status !== '') {
            $query->where('status', $status);
        }

        $courses = Course::query()
            ->when($this->campusId(), fn (Builder $builder) => $builder->where('campus_id', $this->campusId()))
            ->orderBy('name')
            ->get(['id', 'name']);

        $teachers = Teacher::query()
            ->when($this->campusId(), fn (Builder $builder) => $builder->where('campus_id', $this->campusId()))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name']);

        if ($request->query('export') === 'csv') {
            return response()->streamDownload(function () use ($query): void {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['group', 'course', 'teacher', 'status', 'period', 'schedule', 'capacity', 'enrollments']);
                foreach ($query->get() as $group) {
                    fputcsv($out, [
                        $group->name,
                        $group->course->name ?? '',
                        $group->teacher->full_name ?? '',
                        $group->status,
                        $group->period ?? '',
                        $group->schedule ?? '',
                        $group->capacity ?? '',
                        $group->enrollments_count ?? 0,
                    ]);
                }
                fclose($out);
            }, 'groups_report.csv', ['Content-Type' => 'text/csv']);
        }

        return view('groups.index', [
            'groups' => $query->paginate(20)->withQueryString(),
            'courses' => $courses,
            'teachers' => $teachers,
            'filters' => [
                'q' => $q,
                'course_id' => $courseId,
                'teacher_id' => $teacherId,
                'status' => $status,
            ],
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
            'periodOptions' => $this->groupPeriods(),
            'scheduleOptions' => $this->groupSchedules(),
            'statusOptions' => $this->groupStatuses(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'campus_id' => ['required', 'exists:campuses,id'],
            'course_id' => ['required', 'exists:courses,id'],
            'teacher_id' => ['nullable', 'exists:teachers,id'],
            'name' => ['required', 'string', 'max:150'],
            'period' => ['nullable', Rule::in($this->groupPeriods())],
            'schedule' => ['nullable', Rule::in($this->groupSchedules())],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'status' => ['required', Rule::in($this->groupStatuses())],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        if ($this->campusId()) {
            $data['campus_id'] = $this->campusId();
        }

        $group = Group::create($data);
        AuditTrail::log($request, 'group.create', $group, $data);

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
            'periodOptions' => $this->groupPeriods(),
            'scheduleOptions' => $this->groupSchedules(),
            'statusOptions' => $this->groupStatuses(),
            'auditLogs' => AuditLog::query()
                ->with('user')
                ->where('auditable_type', Group::class)
                ->where('auditable_id', $group->id)
                ->latest()
                ->limit(12)
                ->get(),
        ]);
    }

    public function update(Request $request, Group $group): RedirectResponse
    {
        $data = $request->validate([
            'campus_id' => ['required', 'exists:campuses,id'],
            'course_id' => ['required', 'exists:courses,id'],
            'teacher_id' => ['nullable', 'exists:teachers,id'],
            'name' => ['required', 'string', 'max:150'],
            'period' => ['nullable', Rule::in(array_values(array_unique(array_merge($this->groupPeriods(), array_filter([$group->period])))))],
            'schedule' => ['nullable', Rule::in(array_values(array_unique(array_merge($this->groupSchedules(), array_filter([$group->schedule])))))],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'status' => ['required', Rule::in(array_values(array_unique(array_merge($this->groupStatuses(), array_filter([$group->status])))))],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        if ($this->campusId()) {
            $data['campus_id'] = $this->campusId();
        }

        $group->update($data);
        AuditTrail::log($request, 'group.update', $group, $data);

        return redirect()->route('groups.index')->with('success', 'Grupo actualizado.');
    }

    public function destroy(Request $request, Group $group): RedirectResponse
    {
        AuditTrail::log($request, 'group.delete', $group, [
            'name' => $group->name,
            'course_id' => $group->course_id,
            'teacher_id' => $group->teacher_id,
        ]);
        $group->delete();

        return redirect()->route('groups.index')->with('success', 'Grupo eliminado.');
    }
}
