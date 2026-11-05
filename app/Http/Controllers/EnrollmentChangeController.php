<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesCampusAccess;
use App\Models\Charge;
use App\Models\Enrollment;
use App\Models\Group;
use App\Services\EnrollmentChangeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Retirar y cambiar de curso. La sede la verifica el middleware campus.access. #441 */
class EnrollmentChangeController extends Controller
{
    use ScopesCampusAccess;

    public function withdraw(Request $request, Enrollment $enrollment, EnrollmentChangeService $changes): RedirectResponse
    {
        $data = $request->validate([
            'withdrawn_at' => ['required', 'date', 'sane_date'],
            'reason' => ['required', 'string', 'max:255'],
            'void_future_charges' => ['nullable', 'boolean'],
        ], ['reason.required' => 'Indica el motivo del retiro.']);

        $result = $changes->withdraw(
            $enrollment,
            Carbon::parse($data['withdrawn_at']),
            trim($data['reason']),
            $request->boolean('void_future_charges'),
            $request,
        );

        $message = 'Alumno retirado.';
        if ($result['voided'] > 0) {
            $message .= " Cargos futuros anulados: {$result['voided']}.";
        }
        if ($result['pending'] > 0) {
            $message .= " Cargos sin pagar de este curso que siguen en su cuenta: {$result['pending']}.";
        }

        return back()->with('success', $message);
    }

    public function editTransfer(Enrollment $enrollment): View
    {
        abort_unless($enrollment->status === 'active', 404);
        $enrollment->load(['student', 'group.course']);

        return view('enrollments.transfer', [
            'enrollment' => $enrollment,
            'groups' => Group::query()
                ->with(['course.teacher', 'course.period'])
                ->withCount(['enrollments as active_enrollments_count' => fn ($query) => $query->where('status', 'active')])
                ->where('campus_id', $enrollment->campus_id)
                ->where('status', 'active')
                ->whereDoesntHave('enrollments', fn ($query) => $query->where('student_id', $enrollment->student_id)->where('status', 'active'))
                ->get()
                ->sortBy(fn (Group $group) => mb_strtolower($group->course?->name ?? $group->name))
                ->values(),
            'charges' => Charge::query()
                ->where('enrollment_id', $enrollment->id)
                ->where('charge_type', '!=', 'makeup')
                ->whereNull('voided_at')
                ->orderBy('due_date')
                ->get(),
        ]);
    }

    public function transfer(Request $request, Enrollment $enrollment, EnrollmentChangeService $changes): RedirectResponse
    {
        $data = $request->validate([
            'group_id' => ['required', 'integer', Rule::exists('groups', 'id')],
            'changed_at' => ['required', 'date', 'sane_date'],
            'reason' => ['required', 'string', 'max:255'],
            'charges' => ['required', Rule::in([EnrollmentChangeService::CHARGES_MOVE, EnrollmentChangeService::CHARGES_VOID])],
        ], ['reason.required' => 'Indica el motivo del cambio.']);

        $target = Group::query()->findOrFail($data['group_id']);
        $this->authorizeCampus((int) $target->campus_id);
        if ((int) $target->id === (int) $enrollment->group_id) {
            return back()->withErrors(['group_id' => 'Elige un curso distinto al actual.'])->withInput();
        }

        $new = $changes->transfer($enrollment, $target, Carbon::parse($data['changed_at']), trim($data['reason']), $data['charges'], $request);
        $new->load('group.course');

        return redirect()
            ->to(route('students.show', $new->student_id).'#academico')
            ->with('success', 'Cambiado a '.($new->group?->course?->name ?? $new->group?->name).'.');
    }
}
