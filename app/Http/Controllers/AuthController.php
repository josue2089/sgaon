<?php

namespace App\Http\Controllers;

use App\Support\AuditTrail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Credenciales inválidas.'])->onlyInput('email');
        }

        $request->session()->regenerate();
        AuditTrail::log($request, 'auth.login');

        return redirect()->route('dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        AuditTrail::log($request, 'auth.logout');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
