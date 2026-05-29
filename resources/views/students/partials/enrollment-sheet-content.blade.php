@php($representative = $student->representatives->first())
<div class="header">
    <table class="brand">
        <tr>
            <td class="brand-logo">
                @if(!empty($logoDataUri))
                    <img src="{{ $logoDataUri }}" alt="ON English">
                @endif
            </td>
            <td class="brand-meta">
                <h1>Ficha de inscripción del alumno</h1>
                <div>{{ $student->full_name }}</div>
            </td>
        </tr>
    </table>
</div>

<table class="grid">
    <tr>
        <td>
            <div class="card">
                <h2>Datos del alumno</h2>
                <div class="row"><span class="label">Contrato:</span> {{ $student->contract_number ?: 'N/D' }}</div>
                <div class="row"><span class="label">Nombre:</span> {{ $student->full_name }}</div>
                <div class="row"><span class="label">Cédula:</span> {{ $student->document_id ?: 'N/D' }}</div>
                <div class="row"><span class="label">Nacimiento:</span> {{ $student->birth_date?->format('d/m/Y') ?? 'N/D' }}</div>
                <div class="row"><span class="label">Edad:</span> {{ $student->age ? $student->age.' años' : 'N/D' }}</div>
                <div class="row"><span class="label">Sede:</span> {{ $student->campus?->name ?? 'N/D' }}</div>
                <div class="row"><span class="label">Programa inscripción:</span> {{ $student->registrationProgram?->name ?? 'N/D' }}</div>
                <div class="row"><span class="label">Email:</span> {{ $student->email ?: 'N/D' }}</div>
                <div class="row"><span class="label">Celular:</span> {{ $student->mobile_phone ?: $student->phone ?: 'N/D' }}</div>
                <div class="row"><span class="label">Teléfono:</span> {{ $student->landline_phone ?: 'N/D' }}</div>
                <div class="row"><span class="label">Dirección:</span> {{ $student->address ?: 'N/D' }}</div>
                <div class="row"><span class="label">Fecha inscripción:</span> {{ $student->enrollment_date?->format('d/m/Y') ?? 'N/D' }}</div>
                <div class="row"><span class="label">Familiar en institución:</span> {{ $student->family_in_institution ? 'Sí' : 'No' }}{{ $student->family_in_institution_details ? ' · '.$student->family_in_institution_details : '' }}</div>
            </div>
        </td>
        <td>
            <div class="card">
                <h2>Representante</h2>
                @if($representative)
                    <div class="row"><span class="label">Nombre:</span> {{ $representative->full_name }}</div>
                    <div class="row"><span class="label">Cédula:</span> {{ $representative->document_id ?: 'N/D' }}</div>
                    <div class="row"><span class="label">Email:</span> {{ $representative->email ?: 'N/D' }}</div>
                    <div class="row"><span class="label">Habitación:</span> {{ $representative->home_phone ?: 'N/D' }}</div>
                    <div class="row"><span class="label">Celular:</span> {{ $representative->mobile_phone ?: $representative->phone ?: 'N/D' }}</div>
                    <div class="row"><span class="label">Oficina:</span> {{ $representative->office_phone ?: 'N/D' }}</div>
                    <div class="row"><span class="label">Trabajo:</span> {{ $representative->work_place ?: 'N/D' }}</div>
                    <div class="row"><span class="label">Dir. oficina:</span> {{ $representative->work_address ?: 'N/D' }}</div>
                    <div class="row"><span class="label">Dir. habitación:</span> {{ $representative->address ?: 'N/D' }}</div>
                @else
                    <div class="row">Sin representante registrado.</div>
                @endif
            </div>
        </td>
    </tr>
</table>

<h2>Personas autorizadas</h2>
<table>
    <thead>
        <tr>
            <th>#</th>
            <th>Nombre</th>
            <th>Cédula</th>
            <th>Parentesco</th>
            <th>Teléfonos</th>
            <th>Trabajo</th>
            <th>Dirección</th>
        </tr>
    </thead>
    <tbody>
        @forelse($student->authorizedContacts as $contact)
            <tr>
                <td>{{ $contact->slot }}</td>
                <td>{{ $contact->full_name ?: 'N/D' }}</td>
                <td>{{ $contact->document_id ?: 'N/D' }}</td>
                <td>{{ $contact->relationship ?: 'N/D' }}</td>
                <td>Hab. {{ $contact->home_phone ?: 'N/D' }}<br>Cel. {{ $contact->mobile_phone ?: 'N/D' }}</td>
                <td>{{ $contact->work_place ?: 'N/D' }}<br>{{ $contact->work_address ?: '' }}</td>
                <td>{{ $contact->address ?: 'N/D' }}</td>
            </tr>
        @empty
            <tr><td colspan="7">No hay autorizados registrados.</td></tr>
        @endforelse
    </tbody>
</table>

<table class="grid">
    <tr>
        <td>
            <div class="card">
                <h2>Ficha médica</h2>
                <div class="row"><span class="label">Alergias:</span> {{ $student->medical_has_allergies ? 'Sí' : 'No' }}{{ $student->medical_allergy_details ? ' · '.$student->medical_allergy_details : '' }}</div>
                <div class="row"><span class="label">Tratamiento:</span> {{ $student->medical_has_treatment ? 'Sí' : 'No' }}{{ $student->medical_treatment_details ? ' · '.$student->medical_treatment_details : '' }}</div>
                <div class="row"><span class="label">Medicamento fiebre:</span> {{ $student->medical_fever_medication ?: 'N/D' }}</div>
                <div class="row"><span class="label">Medicamento cefalea:</span> {{ $student->medical_headache_medication ?: 'N/D' }}</div>
                <div class="row"><span class="label">Observaciones:</span> {{ $student->medical_notes ?: 'N/D' }}</div>
            </div>
        </td>
        <td>
            <div class="card">
                <h2>Datos comerciales</h2>
                <div class="row"><span class="label">Vendedor:</span> {{ $student->salesperson ?: 'N/D' }}</div>
                <div class="row"><span class="label">Promoción:</span> {{ $student->promotion ?: 'N/D' }}</div>
                <div class="row"><span class="label">Método de pago:</span> {{ $student->payment_method ?: 'N/D' }}</div>
                <div class="row"><span class="label">Cuotas:</span> {{ $student->installments ?: 'N/D' }}</div>
                <div class="row"><span class="label">Observaciones:</span> {{ $student->commercial_notes ?: 'N/D' }}</div>
            </div>
        </td>
    </tr>
</table>
