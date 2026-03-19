<?php

namespace App\Http\Controllers;

use App\Models\CourseLevel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CourseLevelController extends Controller
{
    public function index(Request $request): View
    {
        $query = CourseLevel::query()->orderBy('scale_position')->orderBy('name');

        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $query->where(function ($builder) use ($q): void {
                $builder
                    ->where('name', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%")
                    ->orWhere('stage', 'like', "%{$q}%")
                    ->orWhere('cefr_reference', 'like', "%{$q}%");
            });
        }

        $status = (string) $request->query('status', '');
        if ($status !== '') {
            $query->where('status', $status);
        }

        return view('course_levels.index', [
            'levels' => $query->paginate(20)->withQueryString(),
            'filters' => [
                'q' => $q,
                'status' => $status,
            ],
            'statusOptions' => ['active', 'inactive'],
            'stageOptions' => ['Primary', 'High School', 'Pre-Primary'],
        ]);
    }

    public function create(): View
    {
        return view('course_levels.create', [
            'level' => new CourseLevel(),
            'statusOptions' => ['active', 'inactive'],
            'stageOptions' => ['Primary', 'High School', 'Pre-Primary'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        CourseLevel::create($data);

        return redirect()->route('course-levels.index')->with('success', 'Escala creada.');
    }

    public function edit(CourseLevel $courseLevel): View
    {
        return view('course_levels.edit', [
            'level' => $courseLevel,
            'statusOptions' => ['active', 'inactive'],
            'stageOptions' => ['Primary', 'High School', 'Pre-Primary'],
        ]);
    }

    public function update(Request $request, CourseLevel $courseLevel): RedirectResponse
    {
        $data = $this->validatedData($request, $courseLevel);
        $courseLevel->update($data);

        return redirect()->route('course-levels.index')->with('success', 'Escala actualizada.');
    }

    public function destroy(CourseLevel $courseLevel): RedirectResponse
    {
        $courseLevel->delete();

        return redirect()->route('course-levels.index')->with('success', 'Escala eliminada.');
    }

    private function validatedData(Request $request, ?CourseLevel $level = null): array
    {
        return $request->validate([
            'stage' => ['required', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:30', Rule::unique('course_levels', 'code')->ignore($level?->id)],
            'scale_position' => ['required', 'integer', 'min:1', 'max:99'],
            'scale_total' => ['required', 'integer', 'min:1', 'max:99'],
            'cefr_reference' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'reminder_days_before' => ['required', 'integer', 'min:0', 'max:90'],
        ]);
    }
}
