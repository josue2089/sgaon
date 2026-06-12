<?php

namespace App\Http\Controllers;

use App\Models\Campus;
use App\Models\User;
use App\Services\AdminUserProvisioner;
use App\Support\AuditTrail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    public function index(): View
    {
        $users = User::query()
            ->with(['campus', 'campuses'])
            ->where('role', User::ROLE_ADMIN)
            ->where('is_master', false)
            ->orderBy('name')
            ->paginate(20);

        return view('admin-users.index', [
            'users' => $users,
        ]);
    }

    public function create(): View
    {
        return view('admin-users.create', $this->formData());
    }

    public function store(Request $request, AdminUserProvisioner $provisioner): RedirectResponse
    {
        $data = $this->validatedData($request);

        $result = $provisioner->create(
            $data['name'],
            $data['email'],
            $data['phone'] ?? null,
            $data['status'],
            $data['access_mode'],
            $data['campus_ids'] ?? [],
        );

        AuditTrail::log($request, 'admin_user.create', $result['user'], [
            'email' => $result['user']->email,
            'access_mode' => $data['access_mode'],
            'campus_ids' => $data['campus_ids'] ?? [],
        ]);

        return redirect()
            ->route('admin-users.index')
            ->with('success', 'Usuario administrativo creado. Se enviaron las credenciales por email.');
    }

    public function edit(User $adminUser): View
    {
        $this->guardManagedUser($adminUser);

        return view('admin-users.edit', $this->formData($adminUser));
    }

    public function update(Request $request, User $adminUser, AdminUserProvisioner $provisioner): RedirectResponse
    {
        $this->guardManagedUser($adminUser);

        $data = $this->validatedData($request, $adminUser);

        $user = $provisioner->update(
            $adminUser,
            $data['name'],
            $data['phone'] ?? null,
            $data['status'],
            $data['access_mode'],
            $data['campus_ids'] ?? [],
        );

        AuditTrail::log($request, 'admin_user.update', $user, [
            'email' => $user->email,
            'access_mode' => $data['access_mode'],
            'campus_ids' => $data['campus_ids'] ?? [],
        ]);

        return redirect()
            ->route('admin-users.index')
            ->with('success', 'Usuario administrativo actualizado.');
    }

    public function resendCredentials(Request $request, User $adminUser, AdminUserProvisioner $provisioner): RedirectResponse
    {
        $this->guardManagedUser($adminUser);

        $provisioner->resendCredentials($adminUser);

        AuditTrail::log($request, 'admin_user.resend_credentials', $adminUser, [
            'email' => $adminUser->email,
        ]);

        return back()->with('success', 'Credenciales reenviadas por email.');
    }

    private function formData(?User $user = null): array
    {
        $accessMode = 'selected';
        if ($user) {
            $accessMode = $user->access_all_campuses ? 'all' : 'selected';
        }

        return [
            'user' => $user ?? new User(['status' => 'active', 'role' => User::ROLE_ADMIN]),
            'campuses' => Campus::query()->where('status', 'active')->orderBy('name')->get(),
            'accessMode' => old('access_mode', $accessMode),
            'selectedCampusIds' => old('campus_ids', $user?->campuses->pluck('id')->all() ?? []),
        ];
    }

    private function validatedData(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user?->id),
            ],
            'phone' => ['nullable', 'string', 'max:40'],
            'status' => ['required', 'in:active,inactive'],
            'access_mode' => ['required', 'in:all,selected'],
            'campus_ids' => ['nullable', 'array'],
            'campus_ids.*' => ['integer', 'exists:campuses,id'],
        ]);

        if ($data['access_mode'] === 'selected' && empty($data['campus_ids'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'campus_ids' => 'Selecciona al menos una sede.',
            ]);
        }

        if ($data['access_mode'] === 'all') {
            $data['campus_ids'] = [];
        }

        return $data;
    }

    private function guardManagedUser(User $user): void
    {
        if ($user->role !== User::ROLE_ADMIN || $user->is_master) {
            abort(404);
        }
    }
}
