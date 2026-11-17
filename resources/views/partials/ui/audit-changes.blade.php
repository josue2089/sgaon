{{-- Cambios de un registro de auditoría en lenguaje claro (antes → después). #451 --}}
@php($changes = \App\Support\AuditLabel::changes($log))
@if($changes === [])
    <span class="table-sub">—</span>
@else
    <ul class="audit-changes">
        @foreach($changes as $change)
            <li>
                <span class="audit-changes-field">{{ $change['field'] }}:</span>
                @if($change['from'] !== null)<span class="audit-changes-from">{{ $change['from'] }}</span> → @endif
                <span>{{ $change['to'] }}</span>
            </li>
        @endforeach
    </ul>
@endif
