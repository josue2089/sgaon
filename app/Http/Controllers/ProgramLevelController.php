<?php

namespace App\Http\Controllers;

use App\Models\Program;
use App\Models\ProgramLevel;
use App\Models\ProgramLevelLesson;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProgramLevelController extends Controller
{
    public function create(Program $program): View
    {
        return view('program_levels.create', [
            'program' => $program,
            'level' => new ProgramLevel(['program_id' => $program->id, 'program_total' => $program->levels()->count() ?: 1, 'academic_hours' => 40, 'reminder_days_before' => 5]),
        ]);
    }

    public function store(Request $request, Program $program): RedirectResponse
    {
        $level = $program->levels()->create($this->validatedData($request, $program));

        return redirect()->route('program-levels.show', $level)->with('success', 'Nivel del programa creado.');
    }

    public function show(ProgramLevel $programLevel): View
    {
        $programLevel->load(['program', 'lessons' => fn ($query) => $query->orderBy('sort_order')]);

        return view('program_levels.show', [
            'level' => $programLevel,
            'program' => $programLevel->program,
        ]);
    }

    public function edit(ProgramLevel $programLevel): View
    {
        $programLevel->load('program');

        return view('program_levels.edit', [
            'level' => $programLevel,
            'program' => $programLevel->program,
        ]);
    }

    public function update(Request $request, ProgramLevel $programLevel): RedirectResponse
    {
        $programLevel->update($this->validatedData($request, $programLevel->program, $programLevel));

        return redirect()->route('program-levels.show', $programLevel)->with('success', 'Nivel actualizado.');
    }

    public function destroy(ProgramLevel $programLevel): RedirectResponse
    {
        $program = $programLevel->program;
        if ($programLevel->courses()->exists()) {
            return redirect()->route('programs.show', $program)->withErrors(['program_level' => 'No se puede eliminar un nivel con cursos asociados.']);
        }

        $programLevel->delete();

        return redirect()->route('programs.show', $program)->with('success', 'Nivel eliminado.');
    }

    public function duplicate(ProgramLevel $programLevel): RedirectResponse
    {
        $copy = $programLevel->replicate(['code']);
        $copy->code = $programLevel->code.'-COPY-'.now()->format('His');
        $copy->name = $programLevel->name.' (copia)';
        $copy->save();

        $programLevel->lessons()->orderBy('sort_order')->get()->each(function (ProgramLevelLesson $lesson) use ($copy): void {
            $copy->lessons()->create([
                'class_number' => $lesson->class_number,
                'unit' => $lesson->unit,
                'content' => $lesson->content,
                'notes' => $lesson->notes,
                'sort_order' => $lesson->sort_order,
            ]);
        });

        return redirect()->route('program-levels.show', $copy)->with('success', 'Nivel duplicado.');
    }

    private function validatedData(Request $request, Program $program, ?ProgramLevel $level = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:40', Rule::unique('program_levels', 'code')->ignore($level?->id)],
            'sort_order' => ['required', 'integer', 'min:1', 'max:99'],
            'program_total' => ['required', 'integer', 'min:1', 'max:99'],
            'academic_hours' => ['required', 'integer', 'min:1', 'max:500'],
            'reminder_days_before' => ['required', 'integer', 'min:0', 'max:90'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'description' => ['nullable', 'string'],
        ]) + ['program_id' => $program->id];
    }
}
