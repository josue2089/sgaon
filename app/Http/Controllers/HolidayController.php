<?php

namespace App\Http\Controllers;

use App\Models\Campus;
use App\Models\Holiday;
use App\Services\HolidayCalendarSync;
use App\Support\AuditTrail;
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
        $holiday = Holiday::create($data);

        return $this->resyncAndRedirect($request, $holiday, [$holiday->campus_id], 'Feriado creado.');
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
        $previousCampusId = $holiday->campus_id;
        $previousFrom = $this->resyncFromDate($holiday);
        $holiday->update($this->validatedData($request));
        $newFrom = $this->resyncFromDate($holiday);

        return $this->resyncAndRedirect(
            $request,
            $holiday,
            [$previousCampusId, $holiday->campus_id],
            'Feriado actualizado.',
            $previousFrom->lt($newFrom) ? $previousFrom : $newFrom,
        );
    }

    public function destroy(Request $request, Holiday $holiday): RedirectResponse
    {
        $holiday->delete();

        return $this->resyncAndRedirect($request, $holiday, [$holiday->campus_id], 'Feriado eliminado.');
    }

    /**
     * @param  array<int, int|null>  $campusIds
     */
    private function resyncFromDate(Holiday $holiday): \Carbon\Carbon
    {
        return $holiday->is_recurring || ! $holiday->holiday_date
            ? now()->startOfYear()
            : $holiday->holiday_date->copy()->startOfDay();
    }

    private function resyncAndRedirect(Request $request, Holiday $holiday, array $campusIds, string $prefix, ?\Carbon\Carbon $fromDate = null): RedirectResponse
    {
        $sync = app(HolidayCalendarSync::class);
        $fromDate ??= $this->resyncFromDate($holiday);

        // Un feriado global (null) afecta a todas las sedes, así que basta con una pasada.
        $campusIds = in_array(null, $campusIds, true) ? [null] : array_values(array_unique($campusIds));

        $result = ['updated' => 0, 'skipped' => [], 'conflicts' => []];
        foreach ($campusIds as $campusId) {
            $partial = $sync->resyncForHoliday($campusId, $fromDate);
            $result['updated'] += $partial['updated'];
            $result['skipped'] += $partial['skipped'];
            $result['conflicts'] += $partial['conflicts'];
        }

        AuditTrail::log($request, 'holiday.resync', $holiday, $result);

        return redirect()
            ->route('holidays.index')
            ->with(HolidayCalendarSync::flashMessages($result, $prefix));
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
