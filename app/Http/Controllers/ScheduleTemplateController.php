<?php

namespace App\Http\Controllers;

use App\Models\Campus;
use App\Models\Course;
use App\Models\ScheduleTemplate;
use App\Support\CoursePlanner;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ScheduleTemplateController extends Controller
{
    private function campusId(): ?int
    {
        return \App\Support\CampusScope::campusIdFor(request()->user());
    }

    public function index(Request $request): View
    {
        $query = ScheduleTemplate::query()
            ->with('campus')
            ->when($this->campusId(), fn (Builder $builder) => $builder->where('campus_id', $this->campusId()))
            ->latest();

        $status = (string) $request->query('status', '');
        if ($status !== '') {
            $query->where('status', $status);
        }

        $day = (string) $request->query('day', '');
        if ($day !== '') {
            $query->whereJsonContains('days', $day);
        }

        return view('schedules.index', [
            'schedules' => $query->paginate(15)->withQueryString(),
            'filters' => [
                'status' => $status,
                'day' => $day,
            ],
            'dayOptions' => ScheduleTemplate::DAY_LABELS,
        ]);
    }

    public function create(): View
    {
        return view('schedules.create', [
            'schedule' => new ScheduleTemplate(),
            'campuses' => Campus::query()
                ->when($this->campusId(), fn (Builder $builder) => $builder->where('id', $this->campusId()))
                ->orderBy('name')
                ->get(),
            'statusOptions' => ['active', 'inactive'],
            'dayOptions' => ScheduleTemplate::DAY_LABELS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedScheduleData($request);

        ScheduleTemplate::create($data);

        return redirect()->route('schedules.index')->with('success', 'Horario creado.');
    }

    public function edit(ScheduleTemplate $schedule): View
    {
        return view('schedules.edit', [
            'schedule' => $schedule,
            'campuses' => Campus::query()
                ->when($this->campusId(), fn (Builder $builder) => $builder->where('id', $this->campusId()))
                ->orderBy('name')
                ->get(),
            'statusOptions' => ['active', 'inactive'],
            'dayOptions' => ScheduleTemplate::DAY_LABELS,
        ]);
    }

    public function update(Request $request, ScheduleTemplate $schedule): RedirectResponse
    {
        $before = [
            'days' => json_encode($schedule->days ?? []),
            'starts_at' => CoursePlanner::normalizeTime($schedule->starts_at),
            'ends_at' => CoursePlanner::normalizeTime($schedule->ends_at),
        ];

        $schedule->update($this->validatedScheduleData($request));
        $schedule->refresh();

        $after = [
            'days' => json_encode($schedule->days ?? []),
            'starts_at' => CoursePlanner::normalizeTime($schedule->starts_at),
            'ends_at' => CoursePlanner::normalizeTime($schedule->ends_at),
        ];

        if ($before !== $after) {
            $this->syncCoursesUsingTemplate($schedule);
        }

        return redirect()->route('schedules.index')->with('success', 'Horario actualizado.');
    }

    private function syncCoursesUsingTemplate(ScheduleTemplate $schedule): void
    {
        Course::query()
            ->where('schedule_template_id', $schedule->id)
            ->with(['teacher', 'period', 'scheduleTemplate', 'programLevel', 'managedGroup'])
            ->each(function (Course $course): void {
                try {
                    CoursePlanner::sync($course, true);
                } catch (ValidationException $exception) {
                    Log::warning('No se pudo replanificar el curso tras actualizar horario.', [
                        'course_id' => $course->id,
                        'errors' => $exception->errors(),
                    ]);
                }
            });
    }

    public function destroy(ScheduleTemplate $schedule): RedirectResponse
    {
        $schedule->delete();

        return redirect()->route('schedules.index')->with('success', 'Horario eliminado.');
    }

    private function normalizeDays(array $days): array
    {
        $order = array_keys(ScheduleTemplate::DAY_LABELS);

        return collect($days)
            ->unique()
            ->sortBy(fn (string $day) => array_search($day, $order, true))
            ->values()
            ->all();
    }

    private function validatedScheduleData(Request $request): array
    {
        $campusId = $this->campusId();

        $request->merge([
            'starts_at' => $this->normalizeTimeInput($request->input('starts_at')),
            'ends_at' => $this->normalizeTimeInput($request->input('ends_at')),
        ]);

        $data = $request->validate([
            'campus_id' => ['required', 'exists:campuses,id'],
            'days' => ['required', 'array', 'min:1'],
            'days.*' => ['required', Rule::in(array_keys(ScheduleTemplate::DAY_LABELS))],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        if ($campusId) {
            $data['campus_id'] = $campusId;
        }

        $data['days'] = $this->normalizeDays($data['days']);

        return $data;
    }

    private function normalizeTimeInput(?string $time): ?string
    {
        if ($time === null || $time === '') {
            return $time;
        }

        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) {
            return substr($time, 0, 5);
        }

        return $time;
    }
}
