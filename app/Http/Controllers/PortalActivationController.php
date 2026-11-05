<?php

namespace App\Http\Controllers;

use App\Models\PortalInvitation;
use App\Services\PortalAccessService;
use App\Support\AuditTrail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Elegir la contraseña con el enlace de invitación o recuperación. #471 */
class PortalActivationController extends Controller
{
    public function show(string $token): Response
    {
        $invitation = PortalInvitation::findUsable($token);

        return response()->view('auth.activate', [
            'token' => $token,
            'invitation' => $invitation,
            'loginHint' => $invitation ? app(PortalAccessService::class)->loginHint($invitation->user) : null,
        ], $invitation ? 200 : 410);
    }

    public function store(Request $request, string $token, PortalAccessService $access): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $invitation = PortalInvitation::findUsable($token);
        if (! $invitation) {
            return redirect()->route('portal.activate', $token);
        }

        $user = $access->activate($invitation, $data['password']);
        AuditTrail::log($request, 'portal.access.activate', $user, ['purpose' => $invitation->purpose]);

        return redirect()->route('login')
            ->with('success', 'Tu contraseña quedó guardada. Inicia sesión con '.$access->loginHint($user).'.');
    }
}
