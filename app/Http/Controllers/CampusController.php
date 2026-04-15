<?php

namespace App\Http\Controllers;

use App\Models\AcademicLevel;
use App\Models\Alert;
use App\Models\Campus;
use App\Models\Charge;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\Holiday;
use App\Models\Payment;
use App\Models\Period;
use App\Models\Receipt;
use App\Models\ReportExport;
use App\Models\Representative;
use App\Models\ScheduleTemplate;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Models\ClassSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CampusController extends Controller
{
    public function index(Request $request): View
    {
        $query = Campus::query()->latest();

        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $query->where(function ($builder) use ($q): void {
                $builder
                    ->where('name', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%")
                    ->orWhere('city', 'like', "%{$q}%")
                    ->orWhere('state', 'like', "%{$q}%");
            });
        }

        $status = (string) $request->query('status', '');
        if ($status !== '') {
            $query->where('status', $status);
        }

        return view('campuses.index', [
            'campuses' => $query->paginate(15)->withQueryString(),
            'filters' => [
                'q' => $q,
                'status' => $status,
            ],
        ]);
    }

    public function create(): View
    {
        return view('campuses.create', [
            'campus' => new Campus(),
            'statusOptions' => ['active', 'inactive'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        Campus::create($data);

        return redirect()->route('campuses.index')->with('success', 'Campus creado.');
    }

    public function edit(Campus $campus): View
    {
        return view('campuses.edit', [
            'campus' => $campus,
            'statusOptions' => ['active', 'inactive'],
        ]);
    }

    public function update(Request $request, Campus $campus): RedirectResponse
    {
        $data = $this->validatedData($request, $campus);
        $campus->update($data);

        return redirect()->route('campuses.index')->with('success', 'Campus actualizado.');
    }

    public function destroy(Campus $campus): RedirectResponse
    {
        $dependencies = $this->campusDependencies($campus);
        if ($dependencies->isNotEmpty()) {
            return redirect()
                ->route('campuses.index')
                ->withErrors([
                    'campus' => 'No se puede eliminar el campus porque tiene operación viva asociada: '.$dependencies->implode(', ').'.',
                ]);
        }

        $campus->delete();

        return redirect()->route('campuses.index')->with('success', 'Campus eliminado.');
    }

    private function validatedData(Request $request, ?Campus $campus = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:40', Rule::unique('campuses', 'code')->ignore($campus?->id)],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);
    }

    private function campusDependencies(Campus $campus)
    {
        $map = collect([
            'usuarios' => User::query()->where('campus_id', $campus->id)->exists(),
            'alumnos' => Student::query()->where('campus_id', $campus->id)->exists(),
            'representantes' => Representative::query()->where('campus_id', $campus->id)->exists(),
            'profesores' => Teacher::query()->where('campus_id', $campus->id)->exists(),
            'niveles' => AcademicLevel::query()->where('campus_id', $campus->id)->exists(),
            'periodos' => Period::query()->where('campus_id', $campus->id)->exists(),
            'horarios' => ScheduleTemplate::query()->where('campus_id', $campus->id)->exists(),
            'feriados' => Holiday::query()->where('campus_id', $campus->id)->exists(),
            'cursos' => Course::query()->where('campus_id', $campus->id)->exists(),
            'grupos' => Group::query()->where('campus_id', $campus->id)->exists(),
            'inscripciones' => Enrollment::query()->where('campus_id', $campus->id)->exists(),
            'sesiones' => ClassSession::query()->where('campus_id', $campus->id)->exists(),
            'cargos' => Charge::query()->where('campus_id', $campus->id)->exists(),
            'pagos' => Payment::query()->where('campus_id', $campus->id)->exists(),
            'recibos' => Receipt::query()->where('campus_id', $campus->id)->exists(),
            'alertas' => Alert::query()->where('campus_id', $campus->id)->exists(),
            'exportaciones' => ReportExport::query()->where('campus_id', $campus->id)->exists(),
        ]);

        return $map
            ->filter(fn (bool $exists) => $exists)
            ->keys()
            ->values();
    }
}
