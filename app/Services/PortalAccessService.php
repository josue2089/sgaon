<?php

namespace App\Services;

use App\Mail\PortalInvitationMail;
use App\Models\PortalInvitation;
use App\Models\Program;
use App\Models\Representative;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use App\Support\DocumentNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Cuentas del portal para representantes y alumnos. #471
 * Nunca se envían claves: la persona elige su contraseña con un enlace que vence.
 *
 * - Cada representante con correo tiene su cuenta.
 * - Los alumnos de High School y adultos también; Pre-Primary y Primary entran por el representante.
 * - Si el correo del alumno falta o es el de su representante, entra con su cédula y la invitación
 *   llega al representante.
 */
class PortalAccessService
{
    public const STATE_NONE = 'none';

    public const STATE_INVITED = 'invited';

    public const STATE_EXPIRED = 'expired';

    public const STATE_ACTIVE = 'active';

    public const STATE_DISABLED = 'disabled';

    public const INVITATION_DAYS = 7;

    public const RECOVERY_MINUTES = 60;

    private const STATE_LABELS = [
        self::STATE_NONE => 'Sin acceso',
        self::STATE_INVITED => 'Invitado',
        self::STATE_EXPIRED => 'Invitación vencida',
        self::STATE_ACTIVE => 'Activo',
        self::STATE_DISABLED => 'Desactivado',
    ];

    public function studentCanHaveAccount(Student $student): bool
    {
        $student->loadMissing(['enrollments.group.course.program', 'registrationProgram']);
        $enrollment = $student->enrollments->firstWhere('status', 'active');
        /** @var Program|null $program */
        $program = $enrollment?->group?->course?->program ?? $student->registrationProgram;

        if ($program?->audience) {
            return in_array($program->audience, ['teens', 'adults'], true);
        }

        // Programas sin público definido (robótica, deportes…): se decide por la edad de High School.
        $minimumAge = (int) (Program::query()->where('code', 'HS')->value('age_min') ?: 11);

        return $student->birth_date && $student->birth_date->age >= $minimumAge;
    }

    public function inviteStudent(Student $student, ?User $actor = null): PortalInvitation
    {
        $plan = $this->studentPlan($student);
        if ($plan['blocker']) {
            throw ValidationException::withMessages(['portal_access' => $plan['blocker']]);
        }

        return DB::transaction(function () use ($student, $plan, $actor) {
            $user = $this->ensureUser($student, User::ROLE_STUDENT, $plan);

            return $this->issue($user, $plan['recipient'], $actor, PortalInvitation::PURPOSE_INVITATION, $plan['to_representative']);
        });
    }

    public function inviteRepresentative(Representative $representative, ?User $actor = null): PortalInvitation
    {
        $plan = $this->representativePlan($representative);
        if ($plan['blocker']) {
            throw ValidationException::withMessages(['portal_access' => $plan['blocker']]);
        }

        return DB::transaction(function () use ($representative, $plan, $actor) {
            $user = $this->ensureUser($representative, User::ROLE_REPRESENTATIVE, $plan);

            return $this->issue($user, $plan['recipient'], $actor);
        });
    }

    public function deactivate(User $user): void
    {
        if ($user->role === User::ROLE_ADMIN) {
            throw ValidationException::withMessages(['portal_access' => 'Las cuentas de administrador se gestionan en Usuarios.']);
        }

        DB::transaction(function () use ($user) {
            $user->forceFill(['status' => 'inactive', 'remember_token' => Str::random(60)])->save();
            $user->portalInvitations()->whereNull('accepted_at')->delete();
        });
    }

    /** Enlace de recuperación para quien no tiene correo propio: llega al correo de su representante. */
    public function sendRecovery(User $user): ?PortalInvitation
    {
        $recipient = $this->recoveryRecipient($user);
        if (! $recipient) {
            return null;
        }

        $toRepresentative = Student::query()->where('user_id', $user->id)->exists();

        return DB::transaction(fn () => $this->issue($user, $recipient, null, PortalInvitation::PURPOSE_RECOVERY, $toRepresentative));
    }

    public function activate(PortalInvitation $invitation, string $password): User
    {
        return DB::transaction(function () use ($invitation, $password) {
            $user = $invitation->user;
            $user->forceFill([
                'password' => $password,
                'remember_token' => Str::random(60),
                'status' => 'active',
            ])->save();
            $invitation->forceFill(['accepted_at' => now()])->save();

            return $user;
        });
    }

    public function loginHint(User $user): string
    {
        return $user->email ?: 'tu cédula ('.$user->login_document.')';
    }

    /**
     * Estado del acceso de cada persona de la ficha del alumno.
     *
     * @return list<array{kind: string, id: int, title: string, name: string, state: string, state_label: string, login: ?string, user_id: ?int, can_invite: bool, blocker: ?string}>
     */
    public function summaryFor(Student $student): array
    {
        $student->loadMissing(['representatives.user', 'user']);
        $rows = [];

        foreach ($student->representatives as $representative) {
            $rows[] = $this->row('representative', $representative, 'Representante', $representative->user, $this->representativePlan($representative)['blocker']);
        }
        $rows[] = $this->row('student', $student, 'Alumno', $student->user, $this->studentPlan($student)['blocker']);

        return $rows;
    }

    private function row(string $kind, Student|Representative $profile, string $title, ?User $user, ?string $blocker): array
    {
        $state = $this->stateFor($user);

        return [
            'kind' => $kind,
            'id' => (int) $profile->id,
            'title' => $title,
            'name' => $profile->full_name,
            'state' => $state,
            'state_label' => self::STATE_LABELS[$state],
            'login' => $user ? ($user->email ?: $user->login_document) : null,
            'user_id' => $user?->id,
            'can_invite' => ! $blocker,
            'blocker' => $blocker,
        ];
    }

    private function stateFor(?User $user): string
    {
        if (! $user) {
            return self::STATE_NONE;
        }
        if ($user->status === 'inactive') {
            return self::STATE_DISABLED;
        }

        $latest = $user->portalInvitations()->where('purpose', PortalInvitation::PURPOSE_INVITATION)->latest('id')->first();
        if (! $latest || $latest->accepted_at) {
            return self::STATE_ACTIVE;
        }

        return $latest->expires_at->isPast() ? self::STATE_EXPIRED : self::STATE_INVITED;
    }

    /** @return array{blocker: ?string, recipient: ?string, email: ?string, document: ?string, existing: ?User, to_representative: bool} */
    private function studentPlan(Student $student): array
    {
        $student->loadMissing(['representatives', 'user']);
        $existing = $student->user;

        if (! $existing && ! $this->studentCanHaveAccount($student)) {
            return $this->blocked('Alumno de Pre-Primary o Primary: entra por el portal del representante.');
        }

        $representativeEmails = $student->representatives
            ->map(fn (Representative $representative) => $this->normalizeEmail($representative->email))
            ->filter();
        $ownEmail = $this->normalizeEmail($student->email);
        $emailOwner = $ownEmail ? $this->userWithEmail($ownEmail) : null;

        $usableEmail = $ownEmail
            && ! $representativeEmails->contains($ownEmail)
            && (! $emailOwner || (int) $emailOwner->id === (int) $existing?->id || (! $existing && $this->canLinkStudent($emailOwner)));
        if ($usableEmail && ! $existing && $emailOwner) {
            $existing = $emailOwner;
        }

        // Una cédula igual a la del representante no identifica al alumno.
        $representativeDocuments = $student->representatives
            ->map(fn (Representative $representative) => DocumentNumber::normalize($representative->document_id))
            ->filter();
        $document = DocumentNumber::normalize($student->document_id);
        if ($representativeDocuments->contains($document) || ! $this->documentIsFree($document, $existing)) {
            $document = null;
        }

        $recipient = $usableEmail ? $ownEmail : $representativeEmails->first();
        if (! $recipient) {
            return $this->blocked('Falta un correo: el alumno no tiene correo propio ni un representante con correo.');
        }
        if (! $usableEmail && ! $document && ! $existing?->email && ! $existing?->login_document) {
            return $this->blocked('Necesita correo propio o una cédula propia para iniciar sesión.');
        }

        return [
            'blocker' => null,
            'recipient' => $recipient,
            'email' => $usableEmail ? $ownEmail : null,
            'document' => $document,
            'existing' => $existing,
            'to_representative' => ! $usableEmail,
        ];
    }

    /** @return array{blocker: ?string, recipient: ?string, email: ?string, document: ?string, existing: ?User, to_representative: bool} */
    private function representativePlan(Representative $representative): array
    {
        $representative->loadMissing('user');
        $existing = $representative->user;
        $email = $this->normalizeEmail($representative->email);
        if (! $email) {
            return $this->blocked('El representante no tiene correo.');
        }

        $emailOwner = $this->userWithEmail($email);
        $usableEmail = ! $emailOwner || (int) $emailOwner->id === (int) $existing?->id || (! $existing && $emailOwner->role === User::ROLE_REPRESENTATIVE);
        if ($usableEmail && ! $existing && $emailOwner) {
            $existing = $emailOwner;
        }

        $document = DocumentNumber::normalize($representative->document_id);
        if (! $this->documentIsFree($document, $existing)) {
            $document = null;
        }
        if (! $usableEmail && ! $document && ! $existing?->login_document) {
            return $this->blocked('Su correo ya lo usa otra cuenta y no tiene una cédula libre para iniciar sesión.');
        }

        return [
            'blocker' => null,
            'recipient' => $email,
            'email' => $usableEmail ? $email : null,
            'document' => $document,
            'existing' => $existing,
            'to_representative' => false,
        ];
    }

    private function blocked(string $reason): array
    {
        return ['blocker' => $reason, 'recipient' => null, 'email' => null, 'document' => null, 'existing' => null, 'to_representative' => false];
    }

    private function ensureUser(Student|Representative $profile, string $role, array $plan): User
    {
        /** @var User|null $user */
        $user = $plan['existing'];

        if (! $user) {
            $user = User::create([
                'campus_id' => $profile->campus_id,
                'name' => $profile->full_name,
                'email' => $plan['email'],
                'phone' => $profile->mobile_phone ?: $profile->phone,
                'password' => Str::random(40),
                'role' => $role,
                'status' => 'active',
            ]);
        }

        $user->forceFill([
            'email' => $user->email ?: $plan['email'],
            'login_document' => $user->login_document ?: $plan['document'],
            'status' => 'active',
        ])->save();

        $roleModel = Role::query()->firstOrCreate(['name' => $user->role], ['label' => ucfirst($user->role)]);
        $user->roles()->syncWithoutDetaching([$roleModel->id]);

        if ((int) $profile->user_id !== (int) $user->id) {
            $profile->forceFill(['user_id' => $user->id])->save();
        }

        return $user;
    }

    private function issue(User $user, string $recipient, ?User $actor, string $purpose = PortalInvitation::PURPOSE_INVITATION, bool $toRepresentative = false): PortalInvitation
    {
        $user->portalInvitations()->whereNull('accepted_at')->delete();

        $token = Str::random(64);
        $invitation = $user->portalInvitations()->create([
            'invited_by' => $actor?->id,
            'purpose' => $purpose,
            'token_hash' => PortalInvitation::hashToken($token),
            'sent_to' => $recipient,
            'expires_at' => $purpose === PortalInvitation::PURPOSE_RECOVERY
                ? now()->addMinutes(self::RECOVERY_MINUTES)
                : now()->addDays(self::INVITATION_DAYS),
        ]);

        Mail::to($recipient)->send(new PortalInvitationMail(
            $user,
            route('portal.activate', $token),
            $this->loginHint($user),
            $purpose,
            $toRepresentative,
        ));

        return $invitation;
    }

    private function recoveryRecipient(User $user): ?string
    {
        if ($user->email) {
            return $user->email;
        }

        $student = Student::query()->with('representatives')->where('user_id', $user->id)->first();
        $fromRepresentatives = $student?->representatives
            ->map(fn (Representative $representative) => $this->normalizeEmail($representative->email))
            ->filter()
            ->first();

        return $fromRepresentatives
            ?: $this->normalizeEmail(Representative::query()->where('user_id', $user->id)->value('email'));
    }

    private function canLinkStudent(User $user): bool
    {
        return $user->role === User::ROLE_STUDENT && ! Student::query()->where('user_id', $user->id)->exists();
    }

    private function userWithEmail(string $email): ?User
    {
        return User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
    }

    private function documentIsFree(?string $document, ?User $existing): bool
    {
        if (! $document) {
            return false;
        }

        return ! User::query()
            ->where('login_document', $document)
            ->when($existing, fn ($query) => $query->whereKeyNot($existing->id))
            ->exists();
    }

    private function normalizeEmail(?string $email): ?string
    {
        $email = mb_strtolower(trim((string) $email));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }
}
