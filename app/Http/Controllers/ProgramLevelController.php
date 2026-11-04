<?php

namespace App\Http\Controllers;

use App\Models\Program;
use App\Models\ProgramLevel;
use App\Models\ProgramLevelLesson;
use App\Support\CefrScale;
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
            'nextLevelOptions' => $this->nextLevelOptions(),
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
            'nextLevelOptions' => $this->nextLevelOptions($programLevel),
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

    /** Niveles de todos los programas para elegir el "siguiente nivel" entre programas. */
    private function nextLevelOptions(?ProgramLevel $except = null)
    {
        return ProgramLevel::query()
            ->with('program:id,name')
            ->when($except, fn ($query) => $query->whereKeyNot($except->id))
            ->orderBy('program_id')
            ->orderBy('sort_order')
            ->get(['id', 'program_id', 'name']);
    }

    private function validatedData(Request $request, Program $program, ?ProgramLevel $level = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:40', Rule::unique('program_levels', 'code')->ignore($level?->id)],
            'sort_order' => ['required', 'integer', 'min:1', 'max:99'],
            'program_total' => ['required', 'integer', 'min:1', 'max:99'],
            'academic_hours' => ['required', 'integer', 'min:1', 'max:500'],
            'reminder_days_before' => ['required', 'integer', 'min:0', 'max:90'],
            'base_price_eur' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'description' => ['nullable', 'string'],
            'level_number' => ['nullable', 'integer', 'min:1', 'max:20'],
            'part' => ['nullable', Rule::in(['A', 'B'])],
            'cefr_label' => ['nullable', 'string', 'max:40'],
            'gse_min' => ['nullable', 'integer', 'min:10', 'max:90'],
            'gse_max' => ['nullable', 'integer', 'min:10', 'max:90', 'gte:gse_min'],
            'book' => ['nullable', 'string', 'max:120'],
            'next_program_level_id' => ['nullable', 'integer', Rule::exists('program_levels', 'id'), Rule::notIn(array_filter([$level?->id]))],
        ]) + ['program_id' => $program->id];

        // Si se carga el CEFR sin GSE, se sugiere el rango oficial de Pearson. #469
        if (! empty($data['cefr_label']) && empty($data['gse_min']) && empty($data['gse_max'])
            && ($range = CefrScale::gseRange($data['cefr_label']))) {
            [$data['gse_min'], $data['gse_max']] = $range;
        }

        return $data;
    }
}
