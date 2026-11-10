<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\PortalAccessService;
use App\Support\AuditTrail;
use App\Support\DocumentNumber;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function showForgotPassword(): View
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request, PortalAccessService $access): RedirectResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:255'],
        ]);

        // El mensaje es siempre el mismo: no revela si la cuenta existe. #471
        try {
            $user = $this->findByLogin($data['login']);
            if ($user && $user->status !== 'inactive') {
                $user->email
                    ? Password::sendResetLink(['email' => $user->email])
                    : $access->sendRecovery($user);
            }
        } catch (Throwable $exception) {
            report($exception);
        }

        return back()->with('status', 'Si los datos corresponden a una cuenta, enviamos un enlace para restablecer la contraseña al correo registrado.');
    }

    public function showResetPassword(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset(
            $data,
            function ($user) use ($data) {
                $user->forceFill([
                    'password' => Hash::make($data['password']),
                    'remember_token' => Str::random(60),
                ])->save();
                $user->portalInvitations()->whereNull('accepted_at')->update(['accepted_at' => now()]);

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors([
                'email' => __($status),
            ])->withInput($request->only('email'));
        }

        return redirect()->route('login')->with('success', 'Contraseña actualizada. Ya puedes iniciar sesión.');
    }

    private function findByLogin(string $login): ?User
    {
        $login = trim($login);
        if (str_contains($login, '@')) {
            return User::query()->whereRaw('LOWER(email) = ?', [Str::lower($login)])->first();
        }

        $document = DocumentNumber::normalize($login);

        return $document ? User::query()->where('login_document', $document)->first() : null;
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $login = trim($data['login']);
        $credentials = str_contains($login, '@')
            ? ['email' => $login]
            : ['login_document' => DocumentNumber::normalize($login)];

        if (! reset($credentials) || ! Auth::attempt($credentials + ['password' => $data['password']], $request->boolean('remember'))) {
            return back()->withErrors(['login' => 'Credenciales inválidas.'])->onlyInput('login');
        }

        if (Auth::user()?->status === 'inactive') {
            Auth::logout();

            return back()->withErrors(['login' => 'Este usuario está desactivado. Consulta con la administración.'])->onlyInput('login');
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
