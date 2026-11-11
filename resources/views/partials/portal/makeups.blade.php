{{--
    Recuperativas de un alumno: pago (en un modal, con la cámara del teléfono) y reserva de horario. #448
    Variables: $makeupRequests, $eligibleMakeupSessions, $makeupPaymentInstructions,
    $paymentRoute y $bookRoute (closures que reciben la solicitud) y $idPrefix (único por alumno).
--}}
@if($makeupRequests->isEmpty())
<div class="card">
    <div class="empty-state-inline">{{ $emptyText ?? 'No hay recuperativas pendientes por gestionar.' }}</div>
</div>
@else
<div class="card">
    <h3 class="section-title section-title-md">Recuperativas</h3>
    <p class="entity-sub">Envía el comprobante y, cuando la sede lo valide, reserva el horario.</p>
    @if(filled($makeupPaymentInstructions))
        <div class="makeup-instructions"><h4>Instrucciones de pago</h4><p>{!! nl2br(e($makeupPaymentInstructions)) !!}</p></div>
    @endif
    <div class="makeup-student-list">
        @foreach($makeupRequests as $makeupRequest)
            @php($course = $makeupRequest->enrollment?->group?->course)
            @php($dialogId = $idPrefix.'makeup-pay-'.$makeupRequest->id)
            <article class="makeup-student-card">
                <div class="makeup-student-head"><div><div class="table-title">{{ $course?->name ?? 'N/D' }}</div><div class="table-sub">{{ $makeupRequest->missedSession?->session_date?->format('d/m/Y') ?? 'N/D' }} · {{ ['absent' => 'Inasistencia', 'justified' => 'Inasistencia justificada', 'manual' => 'Registrada por la sede'][$makeupRequest->request_type] ?? ucfirst((string) $makeupRequest->request_type) }}</div></div><div>@include('partials.ui.status', ['status' => $makeupRequest->status, 'domain' => 'makeup'])</div></div>
                <div class="makeup-student-meta"><div><strong>Programa:</strong> {{ $course?->program?->name ?? 'N/D' }}</div><div><strong>Nivel:</strong> {{ $course?->programLevel?->name ?? 'N/D' }}</div><div><strong>Monto:</strong> {{ \App\Support\MoneyFormat::usd($makeupRequest->price) }}</div></div>
                <div class="grid-2">
                    <div class="makeup-student-pane">
                        <h4>Pago / Reposo</h4>
                        @if(in_array($makeupRequest->status, ['pending_payment', 'rejected'], true))
                            <button type="button" class="btn secondary" onclick="document.getElementById('{{ $dialogId }}').showModal()">Enviar comprobante</button>
                            <dialog id="{{ $dialogId }}" class="charge-pay-dialog">
                                <div class="charge-pay-dialog-head">
                                    <h4 class="charge-pay-dialog-title">Comprobante de la recuperativa</h4>
                                    <button type="button" class="charge-pay-dialog-close" onclick="document.getElementById('{{ $dialogId }}').close()" aria-label="Cerrar">&times;</button>
                                </div>
                                <p class="charge-pay-dialog-summary">{{ $course?->name ?? 'Recuperativa' }} · <strong>{{ \App\Support\MoneyFormat::usd($makeupRequest->price) }}</strong></p>
                                <form method="POST" action="{{ $paymentRoute($makeupRequest) }}" enctype="multipart/form-data" class="stack-xs charge-pay-form" data-guard-submit>
                                    @csrf
                                    <div>
                                        <label for="{{ $dialogId }}-proof">Comprobante (foto o PDF)</label>
                                        <input id="{{ $dialogId }}-proof" type="file" name="payment_proof" required accept="image/*,.pdf">
                                    </div>
                                    <label class="checkbox-inline"><input type="checkbox" name="medical_support_required" value="1"> Con reposo médico ($5)</label>
                                    <div>
                                        <label for="{{ $dialogId }}-medical">Reposo médico (solo si marcaste la casilla)</label>
                                        <input id="{{ $dialogId }}-medical" type="file" name="medical_support" accept="image/*,.pdf">
                                    </div>
                                    <div>
                                        <label for="{{ $dialogId }}-notes">Referencia u observación</label>
                                        <input id="{{ $dialogId }}-notes" name="payment_notes" placeholder="Opcional">
                                    </div>
                                    <div class="charge-pay-dialog-footer">
                                        <button type="button" class="btn secondary" onclick="document.getElementById('{{ $dialogId }}').close()">Cancelar</button>
                                        <button class="btn" type="submit">Enviar comprobante</button>
                                    </div>
                                </form>
                            </dialog>
                        @elseif($makeupRequest->status === 'pending_validation')
                            @include('partials.ui.status-badge', ['tone' => 'warn', 'text' => 'En validación'])
                            <div class="table-sub">Ya recibimos el comprobante. Te avisaremos al validarlo.</div>
                        @else
                            <div class="table-sub">Pago enviado: {{ $makeupRequest->payment_proof ? 'Sí' : 'No' }}</div><div class="table-sub">Reposo: {{ $makeupRequest->medical_support_attachment ? 'Sí' : 'No' }}</div>
                        @endif
                    </div>
                    <div class="makeup-student-pane">
                        <h4>Reserva de horario</h4>
                        @if($makeupRequest->status === 'approved_for_booking')
                            @php($sessions = $eligibleMakeupSessions->filter(fn ($session) => (int) $session->program_level_id === (int) $course?->program_level_id))
                            @if($sessions->isEmpty())
                                <div class="table-sub">Todavía no hay horarios disponibles para este nivel. La sede los publicará pronto.</div>
                            @else
                                <form method="POST" action="{{ $bookRoute($makeupRequest) }}" class="stack-xs">
                                    @csrf
                                    <label for="{{ $idPrefix }}makeup-book-{{ $makeupRequest->id }}">Horario</label>
                                    <select id="{{ $idPrefix }}makeup-book-{{ $makeupRequest->id }}" name="makeup_session_id" required>
                                        <option value="">Seleccione horario</option>
                                        @foreach($sessions as $session)
                                            <option value="{{ $session->id }}">{{ $session->display_label }} · {{ $session->teacher?->full_name ?? 'N/D' }} · Cupos {{ $session->available_slots }}</option>
                                        @endforeach
                                    </select>
                                    <button class="btn secondary" type="submit">Reservar</button>
                                </form>
                            @endif
                        @elseif($makeupRequest->booking)
                            <div><strong>Fecha:</strong> {{ $makeupRequest->booking->makeupSession?->session_date?->format('d/m/Y') ?? 'N/D' }}</div><div class="table-sub">{{ $makeupRequest->booking->makeupSession?->starts_at ?? '' }} - {{ $makeupRequest->booking->makeupSession?->ends_at ?? '' }}</div><div class="table-sub">{{ $makeupRequest->booking->makeupSession?->teacher?->full_name ?? 'N/D' }}</div>
                        @else
                            <div class="table-sub">Se habilita al validar el pago.</div>
                        @endif
                    </div>
                </div>
            </article>
        @endforeach
    </div>
</div>
@endif
