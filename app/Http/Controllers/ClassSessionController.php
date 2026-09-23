<?php

namespace App\Http\Controllers;

use App\Models\ClassSession;
use App\Models\Course;
use App\Models\Group;
use App\Services\SessionRescheduler;
use App\Support\AuditTrail;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ClassSessionController extends Controller
{
    private const TIME_RULE = 'regex:/^\d{2}:\d{2}(:\d{2})?$/';

    private function campusId(): ?int
    {
        return \App\Support\CampusScope::campusIdFor(request()->user());
    }

    public function index(Request $request): View
    {
        $query = ClassSession::with('group.course')->latest('session_date');
        if ($this->campusId()) {
            $query->where('campus_id', $this->campusId());
        }

        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $query->where(function (Builder $builder) use ($q) {
                $builder
                    ->where('topic', 'like', "%{$q}%")
                    ->orWhereHas('group', fn (Builder $groupBuilder) => $groupBuilder
                        ->where('name', 'like', "%{$q}%")
                        ->orWhereHas('course', fn (Builder $courseBuilder) => $courseBuilder->where('name', 'like', "%{$q}%")));
            });
        }

        $groupId = (string) $request->query('group_id', '');
        if ($groupId !== '') {
            $query->where('group_id', $groupId);
        }

        $date = (string) $request->query('date', '');
        if ($date !== '') {
            $query->whereDate('session_date', $date);
        }

        $groups = Group::query()
            ->with('course')
            ->when($this->campusId(), fn (Builder $builder) => $builder->where('campus_id', $this->campusId()))
            ->orderBy('name')
            ->get(['id', 'name', 'course_id']);

        return view('sessions.index', [
            'sessions' => $query->paginate(20)->withQueryString(),
            'groups' => $groups,
            'filters' => [
                'q' => $q,
                'group_id' => $groupId,
                'date' => $date,
            ],
        ]);
    }

    public function create(): View
    {
        $groups = Group::with('course')->orderBy('name');
        if ($this->campusId()) {
            $groups->where('campus_id', $this->campusId());
        }

        return view('sessions.create', [
            'groups' => $groups->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'group_id' => ['required', 'exists:groups,id'],
            'session_date' => ['required', 'date'],
            'starts_at' => ['nullable', self::TIME_RULE],
            'ends_at' => ['nullable', self::TIME_RULE],
            'topic' => ['nullable', 'string'],
            'program_status' => ['nullable', 'in:on_track,delayed'],
            'program_notes' => ['nullable', 'string'],
        ]);
        $data = $this->normalizeTimes($data);

        $group = Group::findOrFail($data['group_id']);
        if ($this->campusId() && (int) $group->campus_id !== (int) $this->campusId()) {
            abort(403);
        }
        if ($group->status !== 'active') {
            throw ValidationException::withMessages([
                'group_id' => 'No se pueden crear sesiones para grupos inactivos.',
            ]);
        }
        if ($group->start_date && $data['session_date'] < $group->start_date->toDateString()) {
            throw ValidationException::withMessages([
                'session_date' => 'La sesión no puede ser antes de la fecha de inicio del grupo.',
            ]);
        }
        if ($group->end_date && $data['session_date'] > $group->end_date->toDateString()) {
            throw ValidationException::withMessages([
                'session_date' => 'La sesión no puede ser después de la fecha de fin del grupo.',
            ]);
        }
        $data['campus_id'] = $this->campusId() ?: $group->campus_id;

        ClassSession::create($data);

        return redirect()->route('sessions.index')->with('success', 'Sesión creada.');
    }

    public function edit(ClassSession $session): View
    {
        $groups = Group::with('course')->orderBy('name');
        if ($this->campusId()) {
            $groups->where('campus_id', $this->campusId());
        }

        return view('sessions.edit', [
            'session' => $session,
            'groups' => $groups->get(),
        ]);
    }

    public function update(Request $request, ClassSession $session, SessionRescheduler $rescheduler): RedirectResponse
    {
        $data = $request->validate([
            'group_id' => ['required', 'exists:groups,id'],
            'session_date' => ['required', 'date'],
            'starts_at' => ['nullable', self::TIME_RULE],
            'ends_at' => ['nullable', self::TIME_RULE],
            'topic' => ['nullable', 'string'],
            'program_status' => ['nullable', 'in:on_track,delayed'],
            'program_notes' => ['nullable', 'string'],
        ]);
        $data = $this->normalizeTimes($data);

        $group = Group::findOrFail($data['group_id']);
        if ($this->campusId() && (int) $group->campus_id !== (int) $this->campusId()) {
            abort(403);
        }
        if ($group->status !== 'active') {
            throw ValidationException::withMessages([
                'group_id' => 'No se pueden crear sesiones para grupos inactivos.',
            ]);
        }

        $sessionDate = Carbon::parse($data['session_date'])->startOfDay();
        $rescheduler->assertDateAllowed($group, $sessionDate);

        $data['campus_id'] = $this->campusId() ?: $group->campus_id;

        $previousGroupId = $session->group_id;
        $session->fill($data);
        $rescheduler->markMoved($session, $sessionDate);
        $session->save();
        AuditTrail::log($request, 'session.update', $session, $data);

        $rescheduler->syncEndDates($group);
        if ($previousGroupId && (int) $previousGroupId !== (int) $group->id) {
            $previousGroup = Group::find($previousGroupId);
            if ($previousGroup) {
                $rescheduler->syncEndDates($previousGroup);
            }
        }

        if ($request->input('redirect_to') === 'course') {
            $course = Course::query()->where('managed_group_id', $group->id)->first() ?? $group->course;
            if ($course) {
                return redirect()->route('courses.show', $course)->with('success', 'Sesión actualizada.');
            }
        }

        return redirect()->route('sessions.index')->with('success', 'Sesión actualizada.');
    }

    /**
     * Los inputs de hora pueden enviar segundos (14:20:00) cuando el valor viene de la BD.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeTimes(array $data): array
    {
        foreach (['starts_at', 'ends_at'] as $field) {
            if (! empty($data[$field])) {
                $data[$field] = substr((string) $data[$field], 0, 5);
            }
        }

        return $data;
    }

    public function destroy(ClassSession $session): RedirectResponse
    {
        $session->delete();

        return redirect()->route('sessions.index')->with('success', 'Sesión eliminada.');
    }
}
