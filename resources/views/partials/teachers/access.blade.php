{{-- Acceso del profesor a la plataforma: invitar, reenviar o desactivar. #445 --}}
@php($toneByState = ['none' => 'info', 'invited' => 'warn', 'expired' => 'danger', 'active' => 'ok', 'disabled' => 'danger'])
<div class="card" id="teacher-access">
    <div class="section-head section-head-tight">
        <h2 class="section-title">Acceso a la plataforma</h2>
        @include('partials.ui.status-badge', ['tone' => $toneByState[$access['state']], 'text' => $access['state_label']])
    </div>
    <div class="detail-list">
        <div><strong>Usuario:</strong> {{ $access['login'] ?: '—' }}</div>
    </div>
    <div class="form-actions">
        @if($access['can_invite'])
            <form method="POST" action="{{ route('teachers.access.invite', $teacher) }}" data-guard-submit>
                @csrf
                <button class="btn secondary" type="submit">{{ $access['state'] === 'none' ? 'Invitar' : ($access['state'] === 'disabled' ? 'Reactivar e invitar' : 'Reenviar invitación') }}</button>
            </form>
        @else
            <div class="table-sub">{{ $access['blocker'] }}</div>
        @endif
        @if($access['user_id'] && $access['state'] !== 'disabled')
            <form method="POST" action="{{ route('teachers.access.deactivate', $teacher) }}" data-confirm="¿Desactivar el acceso de {{ $teacher->full_name }}? No podrá iniciar sesión." data-confirm-danger>
                @csrf
                <button class="btn secondary" type="submit">Desactivar</button>
            </form>
        @endif
    </div>
    <p class="entity-sub">El profesor recibe un enlace para elegir su contraseña y entra con su correo.</p>
</div>
