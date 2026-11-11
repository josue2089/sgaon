<?php

namespace App\Http\Controllers;

use App\Models\ProgramLevel;
use App\Models\ProgramLevelLesson;
use App\Support\AuditTrail;
use App\Support\LessonPlanCsv;
use App\Support\LessonTemplates;
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

    /** Importación del plan de clases por CSV: primero vista previa, luego confirmación. #470 */
    public function importPreview(Request $request, ProgramLevel $programLevel): View|RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:1024', 'mimes:csv,txt']]);
        $parsed = LessonPlanCsv::parse((string) file_get_contents($request->file('file')->getRealPath()));
        if ($parsed['rows'] === [] && $parsed['errors'] === []) {
            return back()->withErrors(['file' => 'El archivo no tiene filas.']);
        }

        $request->session()->put($this->importKey($programLevel), $parsed['rows']);
        $existing = $programLevel->lessons()->pluck('class_number')->all();

        return view('program_level_lessons.import-preview', [
            'program' => $programLevel->program,
            'level' => $programLevel,
            'rows' => $parsed['rows'],
            'rowErrors' => $parsed['errors'],
            'existing' => $existing,
        ]);
    }

    public function importStore(Request $request, ProgramLevel $programLevel): RedirectResponse
    {
        $rows = $request->session()->pull($this->importKey($programLevel));
        if (! is_array($rows) || $rows === []) {
            return redirect()->route('program-levels.show', $programLevel)->withErrors(['file' => 'La vista previa venció. Vuelve a subir el archivo.']);
        }

        foreach ($rows as $row) {
            $programLevel->lessons()->updateOrCreate(
                ['class_number' => $row['class_number']],
                $row + ['sort_order' => $row['class_number'], 'from_template' => false],
            );
        }
        AuditTrail::log($request, 'program_lessons.imported', $programLevel, ['rows' => count($rows)]);

        return redirect()->route('program-levels.show', $programLevel)->with('success', 'Plan de clases importado: '.count($rows).' clase(s).');
    }

    private function importKey(ProgramLevel $level): string
    {
        return 'lesson_import.'.$level->id;
    }

    private function validatedData(Request $request, ProgramLevel $level, ?ProgramLevelLesson $lesson = null): array
    {
        return $request->validate([
            'class_number' => ['required', 'integer', 'min:1', 'max:999', Rule::unique('program_level_lessons', 'class_number')->where(fn ($query) => $query->where('program_level_id', $level->id))->ignore($lesson?->id)],
            'unit' => ['nullable', 'string', 'max:120'],
            'content' => ['required', 'string'],
            'notes' => ['nullable', 'string'],
            'sort_order' => ['required', 'integer', 'min:1', 'max:999'],
            'lesson_type' => ['nullable', Rule::in(array_keys(LessonTemplates::TYPES))],
            'activity_tags' => ['nullable', 'array'],
            'activity_tags.*' => [Rule::in(array_keys(LessonTemplates::TAGS))],
        ]) + ['activity_tags' => null, 'from_template' => false];
    }
}
