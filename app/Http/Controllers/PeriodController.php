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
    private function campusId(): ?int
    {
        return \App\Support\CampusScope::campusIdFor(request()->user());
    }

    public function index(Request $request): View
    {
        $query = Period::query()
            ->with('campus')
            ->when($this->campusId(), fn (Builder $builder) => $builder->where('campus_id', $this->campusId()))
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
                ->when($this->campusId(), fn (Builder $builder) => $builder->where('id', $this->campusId()))
                ->orderBy('name')
                ->get(),
            'statusOptions' => ['active', 'inactive'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $campusId = $this->campusId();

        $data = $request->validate([
            'campus_id' => ['required', 'exists:campuses,id'],
            'code' => [
                'required',
                'string',
                'max:40',
                Rule::unique('periods', 'code')->where(fn ($query) => $query->where('campus_id', $campusId ?: $request->integer('campus_id'))),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        if ($campusId) {
            $data['campus_id'] = $campusId;
        }

        Period::create($data);

        return redirect()->route('periods.index')->with('success', 'Período creado.');
    }

    public function edit(Period $period): View
    {
        return view('periods.edit', [
            'period' => $period,
            'campuses' => Campus::query()
                ->when($this->campusId(), fn (Builder $builder) => $builder->where('id', $this->campusId()))
                ->orderBy('name')
                ->get(),
            'statusOptions' => ['active', 'inactive'],
        ]);
    }

    public function update(Request $request, Period $period): RedirectResponse
    {
        $campusId = $this->campusId();

        $data = $request->validate([
            'campus_id' => ['required', 'exists:campuses,id'],
            'code' => [
                'required',
                'string',
                'max:40',
                Rule::unique('periods', 'code')
                    ->ignore($period->id)
                    ->where(fn ($query) => $query->where('campus_id', $campusId ?: $request->integer('campus_id'))),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        if ($campusId) {
            $data['campus_id'] = $campusId;
        }

        $period->update($data);

        return redirect()->route('periods.index')->with('success', 'Período actualizado.');
    }

    public function destroy(Period $period): RedirectResponse
    {
        $period->delete();

        return redirect()->route('periods.index')->with('success', 'Período eliminado.');
    }
}
