<?php

namespace App\Http\Controllers;

use App\Models\Representative;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Support\AuditTrail;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

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

    public function sendResetLink(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = $this->resolveRecoverableUser($data['email']);
        if (! $user) {
            return back()->withErrors([
                'email' => 'No encontramos una cuenta con ese correo.',
            ])->onlyInput('email');
        }

        $status = Password::sendResetLink([
            'email' => $user->email,
        ]);

        if ($status !== Password::RESET_LINK_SENT) {
            return back()->withErrors([
                'email' => __($status),
            ])->onlyInput('email');
        }

        return back()->with('status', 'Te enviamos un enlace para restablecer tu contraseña.');
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

    private function resolveRecoverableUser(string $email): ?User
    {
        $normalizedEmail = Str::lower(trim($email));

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
            ->first();

        if ($user) {
            return $user;
        }

        $student = Student::query()
            ->whereNotNull('email')
            ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
            ->first();
        if ($student) {
            $user = User::create([
                'campus_id' => $student->campus_id,
                'name' => $student->full_name ?: 'Alumno',
                'email' => trim((string) $student->email),
                'phone' => $student->mobile_phone ?: $student->phone,
                'password' => Hash::make(Str::random(32)),
                'role' => User::ROLE_STUDENT,
                'status' => 'active',
            ]);

            if (! $student->user_id) {
                $student->forceFill(['user_id' => $user->id])->save();
            }

            return $user;
        }

        $teacher = Teacher::query()
            ->whereNotNull('email')
            ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
            ->first();
        if ($teacher) {
            $user = User::create([
                'campus_id' => $teacher->campus_id,
                'name' => $teacher->full_name ?: 'Profesor',
                'email' => trim((string) $teacher->email),
                'phone' => $teacher->phone,
                'password' => Hash::make(Str::random(32)),
                'role' => User::ROLE_TEACHER,
                'status' => 'active',
            ]);

            if (! $teacher->user_id) {
                $teacher->forceFill(['user_id' => $user->id])->save();
            }

            return $user;
        }

        $representative = Representative::query()
            ->whereNotNull('email')
            ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
            ->first();
        if ($representative) {
            $user = User::create([
                'campus_id' => $representative->campus_id,
                'name' => $representative->full_name ?: 'Representante',
                'email' => trim((string) $representative->email),
                'phone' => $representative->mobile_phone ?: $representative->phone,
                'password' => Hash::make(Str::random(32)),
                'role' => User::ROLE_REPRESENTATIVE,
                'status' => 'active',
            ]);

            if (! $representative->user_id) {
                $representative->forceFill(['user_id' => $user->id])->save();
            }

            return $user;
        }

        return null;
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
