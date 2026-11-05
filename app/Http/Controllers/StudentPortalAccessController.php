<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesCampusAccess;
use App\Models\Representative;
use App\Models\Student;
use App\Models\User;
use App\Services\PortalAccessService;
use App\Support\AuditTrail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Tarjeta "Acceso al portal" de la ficha del alumno. #471 */
class StudentPortalAccessController extends Controller
{
    use ScopesCampusAccess;

    public function invite(Request $request, Student $student, PortalAccessService $access): RedirectResponse
    {
        $data = $this->validatedTarget($request);

        if ($data['kind'] === 'student') {
            $this->assertStudent($student, (int) $data['id']);
            $invitation = $access->inviteStudent($student, $request->user());
        } else {
            $invitation = $access->inviteRepresentative($this->representativeOf($student, (int) $data['id']), $request->user());
        }

        AuditTrail::log($request, 'portal.access.invite', $invitation->user, ['student_id' => $student->id, 'kind' => $data['kind'], 'sent_to' => $invitation->sent_to]);

        return back()->with('success', 'Invitación enviada a '.$invitation->sent_to.'. Vence en '.PortalAccessService::INVITATION_DAYS.' días.');
    }

    public function deactivate(Request $request, Student $student, PortalAccessService $access): RedirectResponse
    {
        $data = $this->validatedTarget($request);

        if ($data['kind'] === 'student') {
            $this->assertStudent($student, (int) $data['id']);
            $user = $student->user;
        } else {
            $user = $this->representativeOf($student, (int) $data['id'])->user;
        }
        abort_unless($user instanceof User, 404);

        $access->deactivate($user);
        AuditTrail::log($request, 'portal.access.deactivate', $user, ['student_id' => $student->id, 'kind' => $data['kind']]);

        return back()->with('success', 'Acceso desactivado.');
    }

    /** @return array{kind: string, id: string} */
    private function validatedTarget(Request $request): array
    {
        return $request->validate([
            'kind' => ['required', Rule::in(['student', 'representative'])],
            'id' => ['required', 'integer'],
        ]);
    }

    private function assertStudent(Student $student, int $id): void
    {
        abort_unless((int) $student->id === $id, 404);
    }

    private function representativeOf(Student $student, int $id): Representative
    {
        return $student->representatives()->whereKey($id)->firstOrFail();
    }
}
