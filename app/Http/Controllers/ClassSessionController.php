<?php

namespace App\Http\Controllers;

use App\Models\ClassSession;
use App\Models\Group;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClassSessionController extends Controller
{
    private function campusId(): ?int
    {
        return request()->user()?->campus_id;
    }

    public function index(): View
    {
        $query = ClassSession::with('group.course')->latest('session_date');
        if ($this->campusId()) {
            $query->where('campus_id', $this->campusId());
        }

        return view('sessions.index', [
            'sessions' => $query->paginate(20),
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
