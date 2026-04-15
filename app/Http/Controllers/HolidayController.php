<?php

namespace App\Http\Controllers;

use App\Models\Campus;
use App\Models\Holiday;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class HolidayController extends Controller
{
    public function index(Request $request): View
    {
        $query = Holiday::query()->with('campus')->latest();

        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $query->where(function ($builder) use ($q): void {
                $builder
                    ->where('name', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            });
        }

        $status = (string) $request->query('status', '');
        if ($status !== '') {
            $query->where('status', $status);
        }

        $type = (string) $request->query('type', '');
        if ($type === 'recurring') {
            $query->where('is_recurring', true);
        } elseif ($type === 'dated') {
            $query->where('is_recurring', false);
        }

        return view('holidays.index', [
            'holidays' => $query->paginate(20)->withQueryString(),
            'filters' => [
                'q' => $q,
                'status' => $status,
                'type' => $type,
            ],
        ]);
    }

    public function create(): View
    {
        return view('holidays.create', [
            'holiday' => new Holiday(['status' => 'active']),
            'campuses' => Campus::query()->orderBy('name')->get(),
            'statusOptions' => ['active', 'inactive'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        Holiday::create($data);

        return redirect()->route('holidays.index')->with('success', 'Feriado creado.');
    }

    public function edit(Holiday $holiday): View
    {
        return view('holidays.edit', [
            'holiday' => $holiday,
            'campuses' => Campus::query()->orderBy('name')->get(),
            'statusOptions' => ['active', 'inactive'],
        ]);
    }

    public function update(Request $request, Holiday $holiday): RedirectResponse
    {
        $holiday->update($this->validatedData($request));

        return redirect()->route('holidays.index')->with('success', 'Feriado actualizado.');
    }

    public function destroy(Holiday $holiday): RedirectResponse
    {
        $holiday->delete();

        return redirect()->route('holidays.index')->with('success', 'Feriado eliminado.');
    }

    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'campus_id' => ['nullable', 'exists:campuses,id'],
            'name' => ['required', 'string', 'max:160'],
            'is_recurring' => ['nullable', 'boolean'],
            'holiday_date' => ['nullable', 'date'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'day' => ['nullable', 'integer', 'between:1,31'],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $data['is_recurring'] = (bool) ($data['is_recurring'] ?? false);

        if ($data['is_recurring']) {
            if (empty($data['month']) || empty($data['day'])) {
                throw ValidationException::withMessages([
                    'month' => 'Indica mes y día para feriados recurrentes.',
                ]);
            }
            $data['holiday_date'] = null;
        } else {
            if (empty($data['holiday_date'])) {
                throw ValidationException::withMessages([
                    'holiday_date' => 'Indica una fecha para el feriado.',
                ]);
            }
            $data['month'] = null;
            $data['day'] = null;
        }

        return $data;
    }
}
