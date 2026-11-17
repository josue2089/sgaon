{{-- Ficha del alumno: pestaña "recuperativas". Separada de students/show en #454. --}}
@php use App\Support\MoneyFormat; @endphp
<section class="portal-tab-panel student-panel" id="panel-recuperativas" data-tab-panel="recuperativas" role="tabpanel" aria-labelledby="tab-recuperativas">
<div class="card table-card" id="student-makeups">
    <div class="section-head">
        <h2 class="section-title section-title-md">Clases recuperativas</h2>
        <a href="{{ route('students.makeups.create', $student) }}">Registrar clase recuperativa</a>
    </div>
    @if(($makeupHistory ?? collect())->isNotEmpty())
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Clase perdida</th><th>Recuperativa</th><th>Profesor</th><th>Estado</th><th>Cargo</th></tr></thead>
                <tbody>
                @foreach($makeupHistory as $makeup)
                    @php($makeupSession = $makeup->booking?->makeupSession)
                    <tr>
                        <td>{{ $makeup->missedSession?->session_date?->format('d/m/Y') ?? 'Sin inasistencia (manual)' }}<div class="table-sub">{{ $makeup->enrollment?->group?->course?->name }}</div></td>
                        <td>{{ $makeupSession ? $makeupSession->session_date?->format('d/m/Y').' · '.substr((string) $makeupSession->starts_at, 0, 5).' - '.substr((string) $makeupSession->ends_at, 0, 5) : 'Sin reservar' }}</td>
                        <td>{{ $makeupSession?->teacher?->full_name ?? '—' }}</td>
                        <td>{{ \App\Support\StatusLabel::label($makeup->status, 'makeup') }}</td>
                        <td>
                            @if($makeup->charge)
                                {{ MoneyFormat::formatLedgerAmount((float) $makeup->charge->amount, $makeup->charge->currency) }}
                                <div class="table-sub">{{ $makeup->charge->voided_at ? 'Anulado' : \App\Support\StatusLabel::label($makeup->charge->status) }}</div>
                            @else
                                Sin cargo
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="empty-state">No hay clases recuperativas registradas.</div>
    @endif
</div>
</section>
