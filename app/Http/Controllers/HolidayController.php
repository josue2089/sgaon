<?php

namespace App\Http\Controllers;

use App\Models\Campus;
use App\Models\Holiday;
use App\Services\HolidayCalendarSync;
use App\Support\AuditTrail;
use App\Support\CampusScope;
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

        // Admin de sede: ve los feriados generales (solo lectura) y los de sus sedes.
        $allowed = CampusScope::allowedCampusIds($request->user());
        if (is_array($allowed)) {
            $query->where(fn ($builder) => $builder->whereNull('campus_id')->orWhereIn('campus_id', $allowed ?: [0]));
        }

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
        } elseif ($type === Holiday::KIND_SCHOOL_CLOSURE) {
            $query->where('kind', Holiday::KIND_SCHOOL_CLOSURE);
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

    public function create(Request $request): View
    {
        $isMaster = $request->user()->isMasterAdmin();

        return view('holidays.create', [
            'holiday' => new Holiday(['status' => 'active', 'kind' => $isMaster ? Holiday::KIND_HOLIDAY : Holiday::KIND_SCHOOL_CLOSURE]),
            'campuses' => $this->manageableCampuses($request),
            'statusOptions' => ['active', 'inactive'],
            'canManageGlobal' => $isMaster,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $holiday = Holiday::create($data);
        AuditTrail::log($request, 'holiday.create', $holiday, $data);

        return $this->resyncAndRedirect($request, $holiday, [$holiday->campus_id], $holiday->kind_label.' creado.');
    }

    public function edit(Request $request, Holiday $holiday): View
    {
        $this->authorizeHoliday($request, $holiday);

        return view('holidays.edit', [
            'holiday' => $holiday,
            'campuses' => $this->manageableCampuses($request),
            'statusOptions' => ['active', 'inactive'],
            'canManageGlobal' => $request->user()->isMasterAdmin(),
        ]);
    }

    public function update(Request $request, Holiday $holiday): RedirectResponse
    {
        $this->authorizeHoliday($request, $holiday);

        $previousCampusId = $holiday->campus_id;
        $previousFrom = $this->resyncFromDate($holiday);
        $data = $this->validatedData($request);
        $holiday->update($data);
        AuditTrail::log($request, 'holiday.update', $holiday, $data);
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
        $this->authorizeHoliday($request, $holiday);

        AuditTrail::log($request, 'holiday.delete', $holiday, $holiday->toArray());
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

    /**
     * Master: cualquier feriado. Admin de sede: solo los de sus sedes (nunca los generales).
     */
    private function authorizeHoliday(Request $request, Holiday $holiday): void
    {
        if ($request->user()->isMasterAdmin()) {
            return;
        }

        if (! $holiday->campus_id || ! CampusScope::userCanAccessCampus($request->user(), (int) $holiday->campus_id)) {
            abort(403);
        }
    }

    private function manageableCampuses(Request $request)
    {
        $allowed = CampusScope::allowedCampusIds($request->user());

        return Campus::query()
            ->when(is_array($allowed), fn ($query) => $query->whereIn('id', $allowed ?: [0]))
            ->orderBy('name')
            ->get();
    }

    private function validatedData(Request $request): array
    {
        $isMaster = $request->user()->isMasterAdmin();

        $data = $request->validate([
            'campus_id' => [$isMaster ? 'nullable' : 'required', 'exists:campuses,id'],
            'name' => ['required', 'string', 'max:160'],
            'kind' => ['nullable', Rule::in([Holiday::KIND_HOLIDAY, Holiday::KIND_SCHOOL_CLOSURE])],
            'is_recurring' => ['nullable', 'boolean'],
            'holiday_date' => ['nullable', 'date', 'sane_date'],
            'end_date' => ['nullable', 'date', 'sane_date', 'after_or_equal:holiday_date'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'day' => ['nullable', 'integer', 'between:1,31'],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        if (! $isMaster) {
            if (! CampusScope::userCanAccessCampus($request->user(), (int) $data['campus_id'])) {
                abort(403);
            }
            $data['kind'] = Holiday::KIND_SCHOOL_CLOSURE;
        }
        $data['kind'] = $data['kind'] ?? Holiday::KIND_HOLIDAY;

        $data['is_recurring'] = (bool) ($data['is_recurring'] ?? false);

        if ($data['is_recurring']) {
            if (empty($data['month']) || empty($data['day'])) {
                throw ValidationException::withMessages([
                    'month' => 'Indica mes y día para feriados recurrentes.',
                ]);
            }
            $data['holiday_date'] = null;
            $data['end_date'] = null;
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
