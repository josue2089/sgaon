{{-- Ficha del alumno: pestaña "resumen". Separada de students/show en #454. --}}
@php use App\Support\MoneyFormat; @endphp
<section class="portal-tab-panel student-panel is-active" id="panel-resumen" data-tab-panel="resumen" role="tabpanel" aria-labelledby="tab-resumen">
<div class="summary-grid student-summary-grid" id="student-progress">
    <div class="card summary-card">
        <div class="summary-label">Nivel actual</div>
        <div class="summary-value">{{ $summary['current_level_label'] }}</div>
        <div class="table-sub">{{ $summary['current_level_name'] }}</div>
        @if(! empty($summary['current_level_cefr']))
            <div class="table-sub">{{ $summary['current_level_cefr'] }}</div>
        @endif
    </div>
    <div class="card summary-card">
        <div class="summary-label">Siguiente nivel</div>
        <div class="summary-value">{{ $nextCourseLevel?->code ?? 'N/D' }}</div>
        <div class="table-sub">{{ $summary['next_level_name'] }}</div>
    </div>
    <div class="card summary-card">
        <div class="summary-label">Fin del curso actual</div>
        <div class="summary-value">{{ $summary['completion_date']?->format('d/m') ?? 'N/D' }}</div>
        <div class="table-sub">{{ $summary['reminder_date'] ? 'Recordatorio: '.$summary['reminder_date']->format('d/m/Y') : 'Sin recordatorio programable' }}</div>
    </div>
    <div class="card summary-card">
        <div class="summary-label">Saldo pendiente</div>
        <div class="summary-value">{{ MoneyFormat::usd($summary['outstanding_total']) }}</div>
        <div class="table-sub">{{ is_null($summary['attendance_rate']) ? 'Asistencia N/D' : 'Asistencia '.$summary['attendance_rate'].'%' }}</div>
    </div>
</div>

<div class="detail-grid student-detail-grid" id="student-academic">
    <div class="card">
        <div class="section-head section-head-tight">
            <h2 class="section-title section-title-md">Datos del alumno</h2>
            <div class="entity-sub">Información base y contacto</div>
        </div>
        <div class="student-profile-head">
            <span class="table-avatar student-detail-avatar">
                @if($student->profile_photo_path)
                    <img src="{{ \Illuminate\Support\Facades\Storage::url($student->profile_photo_path) }}" alt="{{ $student->full_name }}">
                @else
                    {{ strtoupper(substr($student->first_name, 0, 1)) }}
                @endif
            </span>
            <div>
                <div class="table-title">{{ $student->full_name }}</div>
                <div class="table-sub">{{ $student->email ?: 'Sin email' }}</div>
            </div>
        </div>
        <div class="detail-list detail-list-soft">
            <div><strong>N° Contrato:</strong> {{ $student->contract_number ?: 'N/D' }}</div>
            <div><strong>Documento:</strong> {{ $student->document_id ?: 'Sin documento' }}</div>
            <div><strong>Celular:</strong> {{ $student->mobile_phone ?: $student->phone ?: 'Sin teléfono' }}</div>
            <div><strong>Teléfono:</strong> {{ $student->landline_phone ?: 'Sin teléfono fijo' }}</div>
            <div><strong>Sede:</strong> {{ $student->campus?->name ?? 'Sin sede' }}</div>
            <div><strong>Programa inscripción:</strong> {{ $student->registrationProgram?->name ?? 'N/D' }}</div>
            <div><strong>Estado:</strong> {{ \App\Support\StatusLabel::label($student->status) }}</div>
            <div><strong>Edad:</strong> {{ $student->age ? $student->age.' años' : 'N/D' }}</div>
            <div><strong>Fecha inscripción:</strong> {{ $student->enrollment_date?->format('d/m/Y') ?? 'N/D' }}</div>
            <div><strong>Familiar en institución:</strong> {{ $student->family_in_institution ? 'Sí' : 'No' }}{{ $student->family_in_institution_details ? ' · '.$student->family_in_institution_details : '' }}</div>
            <div><strong>Dirección:</strong> {{ $student->address ?: 'Sin dirección' }}</div>
        </div>
    </div>

    <div class="card">
        <div class="section-head section-head-tight">
            <h2 class="section-title section-title-md">Curso actual y progresión</h2>
            <div class="entity-sub">Programa, nivel real y próxima transición</div>
        </div>
        @if($currentCourse)
            <div class="detail-list detail-list-soft">
                <div><strong>Curso:</strong> {{ $currentCourse->name }}</div>
                <div><strong>Programa:</strong> {{ $currentCourse->program?->name ?? 'N/D' }}</div>
                <div><strong>Profesor:</strong> {{ $currentCourse->teacher?->full_name ?? 'N/D' }}</div>
                <div><strong>Período:</strong> {{ $currentCourse->period?->code ?? 'N/D' }}</div>
                <div><strong>Horario:</strong> {{ $currentCourse->scheduleTemplate?->display_label ?? 'N/D' }}</div>
                <div><strong>Nivel real:</strong> {{ $currentCourseLevel ? (($currentCourseLevel->sort_order ?? $currentCourseLevel->scale_position).'/'.($currentCourseLevel->program_total ?? $currentCourseLevel->scale_total).' · '.$currentCourseLevel->name) : 'Sin nivel asignado' }}</div>
                @if(! empty($summary['current_level_cefr']))
                    <div><strong>Equivalencia:</strong> {{ $summary['current_level_cefr'] }}</div>
                @endif
                <div><strong>Detalle del nivel:</strong> {{ $currentCourseLevel?->description ?? 'Sin descripción' }}</div>
                <div><strong>Próximo nivel:</strong> {{ $nextCourseLevel?->name ?? 'No definido' }}</div>
                <div><strong>Fecha finalización:</strong> {{ $currentCourse->end_date?->format('d/m/Y') ?? 'N/D' }}</div>
            </div>
            @if($student->alerts->count() > 0)
                <div class="stack-sm">
                    @foreach($student->alerts as $alert)
                        <div class="flash {{ $alert->type === 'finance' ? 'err' : 'ok' }}">{{ $alert->message }}</div>
                    @endforeach
                </div>
            @endif
        @else
            <div class="empty-state">El alumno no tiene un curso actual con nivel escalable asignado.</div>
        @endif
    </div>
</div>

@include('partials.students.portal-access')

<div class="detail-grid student-detail-grid">
    <div class="card">
        <div class="section-head section-head-tight">
            <h2 class="section-title section-title-md">Representante principal</h2>
            <div class="entity-sub">Datos de contacto y trabajo</div>
        </div>
        @php($representative = $student->representatives->first())
        @if($representative)
            <div class="detail-list detail-list-soft">
                <div><strong>Nombre:</strong> {{ $representative->full_name }}</div>
                <div><strong>Cédula:</strong> {{ $representative->nationality ? $representative->nationality.'-' : '' }}{{ $representative->document_id ?: 'N/D' }}</div>
                <div><strong>Parentesco:</strong> {{ $representative->relation ?: 'N/D' }}</div>
                <div><strong>Email:</strong> {{ $representative->email ?: 'N/D' }}</div>
                <div><strong>Teléfono habitación:</strong> {{ $representative->home_phone ?: 'N/D' }}</div>
                <div><strong>Celular:</strong> {{ $representative->mobile_phone ?: $representative->phone ?: 'N/D' }}</div>
                <div><strong>Teléfono oficina:</strong> {{ $representative->office_phone ?: 'N/D' }}</div>
                <div><strong>Lugar de trabajo:</strong> {{ $representative->work_place ?: 'N/D' }}</div>
                <div><strong>Dirección oficina:</strong> {{ $representative->work_address ?: 'N/D' }}</div>
                <div><strong>Dirección habitación:</strong> {{ $representative->address ?: 'N/D' }}</div>
            </div>
        @else
            <div class="empty-state">No hay representante principal registrado.</div>
        @endif
    </div>

    <div class="card">
        <div class="section-head section-head-tight">
            <h2 class="section-title section-title-md">Personas autorizadas</h2>
            <div class="entity-sub">Contactos autorizados del alumno</div>
        </div>
        @if($student->authorizedContacts->count() > 0)
            <div class="detail-list detail-list-soft">
                @foreach($student->authorizedContacts as $contact)
                    <div>
                        <strong>Autorizado {{ $contact->slot }}:</strong> {{ $contact->full_name ?: 'N/D' }}<br>
                        Cédula: {{ $contact->document_id ?: 'N/D' }} · Parentesco: {{ $contact->relationship ?: 'N/D' }}<br>
                        Habitación: {{ $contact->home_phone ?: 'N/D' }} · Celular: {{ $contact->mobile_phone ?: 'N/D' }}<br>
                        Trabajo: {{ $contact->work_place ?: 'N/D' }} · Dirección: {{ $contact->work_address ?: 'N/D' }}<br>
                        Habitación: {{ $contact->address ?: 'N/D' }}
                    </div>
                @endforeach
            </div>
        @else
            <div class="empty-state">No hay personas autorizadas registradas.</div>
        @endif
    </div>
</div>

<div class="detail-grid student-detail-grid">
    <div class="card">
        <div class="section-head section-head-tight">
            <h2 class="section-title section-title-md">Ficha médica</h2>
            <div class="entity-sub">Alergias, tratamientos y autorizaciones</div>
        </div>
        <div class="detail-list detail-list-soft">
            <div><strong>Alergias:</strong> {{ $student->medical_has_allergies ? 'Sí' : 'No' }}{{ $student->medical_allergy_details ? ' · '.$student->medical_allergy_details : '' }}</div>
            <div><strong>Tratamiento actual:</strong> {{ $student->medical_has_treatment ? 'Sí' : 'No' }}{{ $student->medical_treatment_details ? ' · '.$student->medical_treatment_details : '' }}</div>
            <div><strong>Medicamento fiebre:</strong> {{ $student->medical_fever_medication ?: 'N/D' }}</div>
            <div><strong>Medicamento cefalea:</strong> {{ $student->medical_headache_medication ?: 'N/D' }}</div>
            <div><strong>Observaciones:</strong> {{ $student->medical_notes ?: 'Sin observaciones' }}</div>
        </div>
    </div>

    <div class="card">
        <div class="section-head section-head-tight">
            <h2 class="section-title section-title-md">Datos comerciales</h2>
            <div class="entity-sub">Seguimiento de venta e inscripción</div>
        </div>
        <div class="detail-list detail-list-soft">
            <div><strong>Vendedor:</strong> {{ $student->salesperson ?: 'N/D' }}</div>
            <div><strong>Promoción:</strong> {{ $student->promotion ?: 'N/D' }}</div>
            <div><strong>Método de pago:</strong> {{ $student->payment_method ?: 'N/D' }}</div>
            <div><strong>Cuotas:</strong> {{ $student->installments ?: 'N/D' }}</div>
            <div><strong>Observaciones:</strong> {{ $student->commercial_notes ?: 'Sin observaciones' }}</div>
        </div>
    </div>
</div>
</section>
