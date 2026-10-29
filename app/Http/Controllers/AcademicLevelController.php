<?php

namespace App\Http\Controllers;

use App\Models\AcademicLevel;
use App\Models\Campus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AcademicLevelController extends Controller
{
    use \App\Http\Controllers\Concerns\ScopesCampusAccess;

    public function index(Request $request): View
    {
        $query = AcademicLevel::query()
            ->with('campus')
            ->tap(fn ($builder) => $this->applyCampusScope($builder, 'campus_id'))
            ->orderBy('sort_order')
            ->orderBy('name');

        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $query->where(function (Builder $builder) use ($q): void {
                $builder
                    ->where('name', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            });
        }

        return view('academic_levels.index', [
            'levels' => $query->paginate(20)->withQueryString(),
            'filters' => ['q' => $q],
        ]);
    }

    public function create(): View
    {
        return view('academic_levels.create', [
            'level' => new AcademicLevel(),
            'campuses' => Campus::query()
                ->tap(fn ($builder) => $this->applyCampusScope($builder, 'id'))
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        if (($writableCampusId = $this->writableCampusId($data['campus_id'] ?? null)) !== null) {
            $data['campus_id'] = $writableCampusId;
        }

        AcademicLevel::create($data);

        return redirect()->route('academic-levels.index')->with('success', 'Nivel creado.');
    }

    public function edit(AcademicLevel $academicLevel): View
    {
        return view('academic_levels.edit', [
            'level' => $academicLevel,
            'campuses' => Campus::query()
                ->tap(fn ($builder) => $this->applyCampusScope($builder, 'id'))
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function update(Request $request, AcademicLevel $academicLevel): RedirectResponse
    {
        $data = $this->validatedData($request, $academicLevel);
        if (($writableCampusId = $this->writableCampusId($data['campus_id'] ?? null)) !== null) {
            $data['campus_id'] = $writableCampusId;
        }

        $academicLevel->update($data);

        return redirect()->route('academic-levels.index')->with('success', 'Nivel actualizado.');
    }

    public function destroy(AcademicLevel $academicLevel): RedirectResponse
    {
        $academicLevel->delete();

        return redirect()->route('academic-levels.index')->with('success', 'Nivel eliminado.');
    }

    private function validatedData(Request $request, ?AcademicLevel $level = null): array
    {
        $campusId = $this->singleAllowedCampusId() ?: $request->integer('campus_id');

        return $request->validate([
            'campus_id' => ['required', 'exists:campuses,id'],
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('academic_levels', 'name')
                    ->ignore($level?->id)
                    ->where(fn ($query) => $query->where('campus_id', $campusId)),
            ],
            'code' => ['nullable', 'string', 'max:40'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'description' => ['nullable', 'string'],
        ]);
    }
}
