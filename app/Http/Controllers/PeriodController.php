<?php

namespace App\Http\Controllers;

use App\Models\Campus;
use App\Models\Period;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PeriodController extends Controller
{
    use \App\Http\Controllers\Concerns\ScopesCampusAccess;

    public function index(Request $request): View
    {
        $query = Period::query()
            ->with('campus')
            ->tap(fn ($builder) => $this->applyCampusScope($builder, 'campus_id'))
            ->latest();

        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $query->where(function (Builder $builder) use ($q) {
                $builder
                    ->where('code', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            });
        }

        $status = (string) $request->query('status', '');
        if ($status !== '') {
            $query->where('status', $status);
        }

        return view('periods.index', [
            'periods' => $query->paginate(15)->withQueryString(),
            'filters' => [
                'q' => $q,
                'status' => $status,
            ],
        ]);
    }

    public function create(): View
    {
        return view('periods.create', [
            'period' => new Period(),
            'campuses' => Campus::query()
                ->tap(fn ($builder) => $this->applyCampusScope($builder, 'id'))
                ->orderBy('name')
                ->get(),
            'statusOptions' => ['active', 'inactive'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $campusId = $this->writableCampusId($request->input('campus_id'));
        $request->merge(['campus_id' => $campusId]);

        $data = $request->validate([
            'campus_id' => ['required', 'exists:campuses,id'],
            'code' => [
                'required',
                'string',
                'max:40',
                Rule::unique('periods', 'code')->where(fn ($query) => $query->where('campus_id', $campusId)),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);


        Period::create($data);

        return redirect()->route('periods.index')->with('success', 'Período creado.');
    }

    public function edit(Period $period): View
    {
        return view('periods.edit', [
            'period' => $period,
            'campuses' => Campus::query()
                ->tap(fn ($builder) => $this->applyCampusScope($builder, 'id'))
                ->orderBy('name')
                ->get(),
            'statusOptions' => ['active', 'inactive'],
        ]);
    }

    public function update(Request $request, Period $period): RedirectResponse
    {
        $campusId = $this->writableCampusId($request->input('campus_id'));
        $request->merge(['campus_id' => $campusId]);

        $data = $request->validate([
            'campus_id' => ['required', 'exists:campuses,id'],
            'code' => [
                'required',
                'string',
                'max:40',
                Rule::unique('periods', 'code')
                    ->ignore($period->id)
                    ->where(fn ($query) => $query->where('campus_id', $campusId)),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);


        $period->update($data);

        return redirect()->route('periods.index')->with('success', 'Período actualizado.');
    }

    public function destroy(Period $period): RedirectResponse
    {
        $period->delete();

        return redirect()->route('periods.index')->with('success', 'Período eliminado.');
    }
}
