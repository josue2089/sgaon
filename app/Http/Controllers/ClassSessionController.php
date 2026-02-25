<?php

namespace App\Http\Controllers;

use App\Models\ClassSession;
use App\Models\Group;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ClassSessionController extends Controller
{
    private function campusId(): ?int
    {
        return request()->user()?->campus_id;
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
            'starts_at' => ['nullable', 'date_format:H:i'],
            'ends_at' => ['nullable', 'date_format:H:i'],
            'topic' => ['nullable', 'string'],
        ]);

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

    public function update(Request $request, ClassSession $session): RedirectResponse
    {
        $data = $request->validate([
            'group_id' => ['required', 'exists:groups,id'],
            'session_date' => ['required', 'date'],
            'starts_at' => ['nullable', 'date_format:H:i'],
            'ends_at' => ['nullable', 'date_format:H:i'],
            'topic' => ['nullable', 'string'],
        ]);

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

        $session->update($data);

        return redirect()->route('sessions.index')->with('success', 'Sesión actualizada.');
    }

    public function destroy(ClassSession $session): RedirectResponse
    {
        $session->delete();

        return redirect()->route('sessions.index')->with('success', 'Sesión eliminada.');
    }
}
