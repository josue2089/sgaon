<?php

namespace App\Http\Controllers;

use App\Models\Program;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProgramController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');

        $query = Program::query()->withCount(['levels', 'courses'])->orderBy('name');

        if ($q !== '') {
            $query->where(function ($builder) use ($q): void {
                $builder->where('name', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%");
            });
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        return view('programs.index', [
            'programs' => $query->paginate(20)->withQueryString(),
            'filters' => compact('q', 'status'),
        ]);
    }

    public function create(): View
    {
        return view('programs.create', ['program' => new Program()]);
    }

    public function store(Request $request): RedirectResponse
    {
        Program::create($this->validatedData($request));

        return redirect()->route('programs.index')->with('success', 'Programa creado.');
    }

    public function show(Program $program): View
    {
        $program->load(['levels' => fn ($query) => $query->withCount(['lessons', 'courses'])->orderBy('sort_order')]);

        return view('programs.show', [
            'program' => $program,
        ]);
    }

    public function edit(Program $program): View
    {
        return view('programs.edit', ['program' => $program]);
    }

    public function update(Request $request, Program $program): RedirectResponse
    {
        $program->update($this->validatedData($request, $program));

        return redirect()->route('programs.show', $program)->with('success', 'Programa actualizado.');
    }

    public function destroy(Program $program): RedirectResponse
    {
        if ($program->levels()->exists() || $program->courses()->exists()) {
            return redirect()->route('programs.index')->withErrors(['program' => 'No se puede eliminar un programa con niveles o cursos asociados.']);
        }

        $program->delete();

        return redirect()->route('programs.index')->with('success', 'Programa eliminado.');
    }

    private function validatedData(Request $request, ?Program $program = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:40', Rule::unique('programs', 'code')->ignore($program?->id)],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'description' => ['nullable', 'string'],
        ]);
    }
}
