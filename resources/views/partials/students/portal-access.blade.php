{{-- Estado del acceso al portal de cada persona de la ficha. #471 --}}
@php($toneByState = ['none' => 'info', 'invited' => 'warn', 'expired' => 'danger', 'active' => 'ok', 'disabled' => 'danger'])
<div class="card" id="portal-access">
    <div class="section-head section-head-tight">
        <h2 class="section-title section-title-md">Acceso al portal</h2>
        <div class="entity-sub">Quién puede entrar con usuario y contraseña</div>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th scope="col">Persona</th><th scope="col">Usuario</th><th scope="col">Estado</th><th scope="col">Acciones</th></tr></thead>
            <tbody>
            @foreach($portalAccess as $row)
                <tr>
                    <td>
                        <div class="table-title">{{ $row['name'] }}</div>
                        <div class="table-sub">{{ $row['title'] }}</div>
                    </td>
                    <td>{{ $row['login'] ?: '—' }}</td>
                    <td>@include('partials.ui.status-badge', ['tone' => $toneByState[$row['state']], 'text' => $row['state_label']])</td>
                    <td class="table-actions">
                        @if($row['can_invite'])
                            <form method="POST" action="{{ route('students.portal-access.invite', $student) }}" data-guard-submit>
                                @csrf
                                <input type="hidden" name="kind" value="{{ $row['kind'] }}">
                                <input type="hidden" name="id" value="{{ $row['id'] }}">
                                <button class="btn secondary" type="submit">{{ $row['state'] === 'none' ? 'Invitar' : ($row['state'] === 'disabled' ? 'Reactivar e invitar' : 'Reenviar invitación') }}</button>
                            </form>
                        @else
                            <div class="table-sub">{{ $row['blocker'] }}</div>
                        @endif
                        @if($row['user_id'] && $row['state'] !== 'disabled')
                            <form method="POST" action="{{ route('students.portal-access.deactivate', $student) }}" data-confirm="¿Desactivar el acceso de {{ $row['name'] }}? No podrá iniciar sesión." data-confirm-danger>
                                @csrf
                                <input type="hidden" name="kind" value="{{ $row['kind'] }}">
                                <input type="hidden" name="id" value="{{ $row['id'] }}">
                                <button class="btn secondary" type="submit">Desactivar</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
