<?php

namespace App\Http\Controllers;

use App\Models\Teacher;
use App\Services\PortalAccessService;
use App\Support\AuditTrail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Tarjeta "Acceso a la plataforma" de la ficha del profesor. La sede la verifica campus.access. #445 */
class TeacherAccessController extends Controller
{
    public function invite(Request $request, Teacher $teacher, PortalAccessService $access): RedirectResponse
    {
        $invitation = $access->inviteTeacher($teacher, $request->user());
        AuditTrail::log($request, 'teacher.access.invite', $invitation->user, ['teacher_id' => $teacher->id, 'sent_to' => $invitation->sent_to]);

        return back()->with('success', 'Invitación enviada a '.$invitation->sent_to.'. Vence en '.PortalAccessService::INVITATION_DAYS.' días.');
    }

    public function deactivate(Request $request, Teacher $teacher, PortalAccessService $access): RedirectResponse
    {
        abort_unless($teacher->user, 404);
        $access->deactivate($teacher->user);
        AuditTrail::log($request, 'teacher.access.deactivate', $teacher->user, ['teacher_id' => $teacher->id]);

        return back()->with('success', 'Acceso del profesor desactivado.');
    }
}
