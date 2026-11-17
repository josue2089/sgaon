{{-- Agenda y asistencia de un alumno. Lo usan el portal del alumno y el del representante. #448 --}}
@if($agenda->isEmpty() && $attendance->isEmpty())
    <div class="card">
        <div class="empty-state-inline">{{ $emptyText ?? 'No hay clases próximas ni registros de asistencia todavía.' }}</div>
    </div>
@else
<div class="card table-card">
    <div class="section-head"><h3 class="section-title section-title-md">Agenda</h3><div class="entity-sub">Clases regulares y recuperativas reservadas</div></div>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th scope="col">Tipo</th><th scope="col">Fecha</th><th scope="col">Horario</th><th scope="col">Clase</th><th scope="col">Profesor</th></tr></thead>
            <tbody>
            @forelse($agenda as $item)
                <tr><td>@include('partials.ui.status-badge', ['tone' => $item['kind'] === 'makeup' ? 'warn' : 'info', 'text' => $item['kind'] === 'makeup' ? 'Recuperativa' : 'Regular'])</td><td>{{ $item['date']?->format('d/m/Y') ?? 'N/D' }}</td><td>{{ $item['starts_at'] ?? 'N/D' }} - {{ $item['ends_at'] ?? 'N/D' }}</td><td>{{ $item['label'] }}</td><td>{{ $item['teacher'] }}</td></tr>
            @empty
                <tr><td colspan="5"><div class="empty-state-inline">No hay agenda próxima.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="card table-card">
    <h3 class="section-title section-title-sm">Resumen de asistencia</h3>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th scope="col">Grupo</th><th scope="col">Presente</th><th scope="col">Ausente</th><th scope="col">Tarde</th><th scope="col">Justificada</th></tr></thead>
            <tbody>
            @forelse($attendance as $item)
                <tr><td>{{ $item->group->name ?? '' }}</td><td>{{ $item->present_count }}</td><td>{{ $item->absent_count }}</td><td>{{ $item->late_count }}</td><td>{{ $item->justified_count }}</td></tr>
            @empty
                <tr><td colspan="5"><div class="empty-state-inline">Sin registros</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endif
