<?php

namespace App\Http\Controllers;

use App\Support\AuditTrail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Cada usuario cambia su propia contraseña. #471 */
class AccountPasswordController extends Controller
{
    public function edit(): View
    {
        return view('account.password');
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'different:current_password'],
        ]);

        $user = $request->user();
        $user->forceFill(['password' => $data['password'], 'remember_token' => Str::random(60)])->save();
        $request->session()->regenerate();
        AuditTrail::log($request, 'account.password.update', $user);

        return back()->with('success', 'Contraseña actualizada.');
    }
}
