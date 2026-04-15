<?php

namespace App\Http\Controllers;

use App\Models\ProgramLevel;
use App\Models\ProgramLevelLesson;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProgramLevelLessonController extends Controller
{
    public function create(ProgramLevel $programLevel): View
    {
        $programLevel->load('program');

        return view('program_level_lessons.create', [
            'program' => $programLevel->program,
            'level' => $programLevel,
            'lesson' => new ProgramLevelLesson(['class_number' => ($programLevel->lessons()->max('class_number') ?? 0) + 1, 'sort_order' => ($programLevel->lessons()->max('sort_order') ?? 0) + 1]),
        ]);
    }

    public function store(Request $request, ProgramLevel $programLevel): RedirectResponse
    {
        $programLevel->lessons()->create($this->validatedData($request, $programLevel));

        return redirect()->route('program-levels.show', $programLevel)->with('success', 'Clase base creada.');
    }

    public function edit(ProgramLevelLesson $programLevelLesson): View
    {
        $programLevelLesson->load('programLevel.program');

        return view('program_level_lessons.edit', [
            'program' => $programLevelLesson->programLevel->program,
            'level' => $programLevelLesson->programLevel,
            'lesson' => $programLevelLesson,
        ]);
    }

    public function update(Request $request, ProgramLevelLesson $programLevelLesson): RedirectResponse
    {
        $programLevelLesson->update($this->validatedData($request, $programLevelLesson->programLevel, $programLevelLesson));

        return redirect()->route('program-levels.show', $programLevelLesson->programLevel)->with('success', 'Clase base actualizada.');
    }

    public function destroy(ProgramLevelLesson $programLevelLesson): RedirectResponse
    {
        $level = $programLevelLesson->programLevel;
        $programLevelLesson->delete();

        return redirect()->route('program-levels.show', $level)->with('success', 'Clase base eliminada.');
    }

    private function validatedData(Request $request, ProgramLevel $level, ?ProgramLevelLesson $lesson = null): array
    {
        return $request->validate([
            'class_number' => ['required', 'integer', 'min:1', 'max:999', Rule::unique('program_level_lessons', 'class_number')->where(fn ($query) => $query->where('program_level_id', $level->id))->ignore($lesson?->id)],
            'unit' => ['nullable', 'string', 'max:120'],
            'content' => ['required', 'string'],
            'notes' => ['nullable', 'string'],
            'sort_order' => ['required', 'integer', 'min:1', 'max:999'],
        ]);
    }
}
