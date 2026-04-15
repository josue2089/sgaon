@php
    $representative = old('representative', optional(($student->representatives ?? collect())->first())->toArray() ?? []);
    $authorizedContacts = old('authorized_contacts', ($student->authorizedContacts ?? collect())->sortBy('slot')->values()->map->toArray()->all());
    $authorizedContacts = array_values($authorizedContacts);
    $authorizedContacts[0] = $authorizedContacts[0] ?? [];
    $authorizedContacts[1] = $authorizedContacts[1] ?? [];
@endphp

<div class="grid-2">
    <div>
        <label>Foto de perfil</label>
        @if(!empty($student?->profile_photo_path))
            <div class="profile-photo-preview-wrap">
                <img class="profile-photo-preview" src="{{ \Illuminate\Support\Facades\Storage::url($student->profile_photo_path) }}" alt="Foto actual del alumno">
            </div>
        @endif
        <input type="file" class="filepond" name="profile_photo" accept="image/png,image/jpeg,image/webp">
    </div>
    <div>
        <label>N° Contrato</label>
        <input name="contract_number" value="{{ old('contract_number', $student->contract_number ?? '') }}">
    </div>

    <div>
        <label>Campus</label>
        <select name="campus_id" required>
            @foreach($campuses as $campus)
                <option value="{{ $campus->id }}" @selected(old('campus_id',$student->campus_id ?? '')==$campus->id)>{{ $campus->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label>Fecha de inscripción</label>
        <input type="date" name="enrollment_date" value="{{ old('enrollment_date',isset($student->enrollment_date)?$student->enrollment_date?->format('Y-m-d'):'') }}">
    </div>
</div>

<div class="card mt-2">
    <div class="section-head section-head-tight">
        <h2 class="section-title section-title-md">Datos del alumno</h2>
        <div class="entity-sub">Información principal de inscripción</div>
    </div>
    <div class="grid-2">
        <div><label>Nombres</label><input name="first_name" value="{{ old('first_name',$student->first_name ?? '') }}" required></div>
        <div><label>Apellidos</label><input name="last_name" value="{{ old('last_name',$student->last_name ?? '') }}" required></div>
        <div><label>N° Cédula</label><input name="document_id" value="{{ old('document_id',$student->document_id ?? '') }}"></div>
        <div><label>Fecha de nacimiento</label><input type="date" name="birth_date" value="{{ old('birth_date',isset($student->birth_date)?$student->birth_date?->format('Y-m-d'):'') }}"></div>
        <div><label>Email</label><input name="email" type="email" value="{{ old('email',$student->email ?? '') }}"></div>
        <div><label>Celular</label><input name="mobile_phone" value="{{ old('mobile_phone',$student->mobile_phone ?? $student->phone ?? '') }}"></div>
        <div><label>Teléfono</label><input name="landline_phone" value="{{ old('landline_phone',$student->landline_phone ?? '') }}"></div>
        <div><label>Nivel de inscripción</label>
            <select name="registration_level_id">
                <option value="">Seleccione</option>
                @foreach($levels as $level)
                    <option value="{{ $level->id }}" @selected((string) old('registration_level_id',$student->registration_level_id ?? '') === (string) $level->id)>{{ $level->name }}</option>
                @endforeach
            </select>
        </div>
        <div style="grid-column:1/-1;"><label>Dirección</label><textarea name="address">{{ old('address',$student->address ?? '') }}</textarea></div>
        <div>
            <label>¿Tiene familiar en la institución?</label>
            <select name="family_in_institution">
                <option value="0" @selected(!old('family_in_institution', $student->family_in_institution ?? false))>No</option>
                <option value="1" @selected((bool) old('family_in_institution', $student->family_in_institution ?? false))>Sí</option>
            </select>
        </div>
        <div><label>Detalle familiar</label><input name="family_in_institution_details" value="{{ old('family_in_institution_details',$student->family_in_institution_details ?? '') }}" placeholder="Nombre o referencia"></div>
        <div>
            <label>Status</label>
            <select name="status">
                @foreach(['active','inactive','withdrawn','graduated'] as $status)
                    <option value="{{ $status }}" @selected(old('status',$student->status ?? 'active')==$status)>{{ $status }}</option>
                @endforeach
            </select>
        </div>
    </div>
</div>

<div class="card mt-2">
    <div class="section-head section-head-tight">
        <h2 class="section-title section-title-md">Datos del representante</h2>
        <div class="entity-sub">Representante principal asociado al alumno</div>
    </div>
    <div class="grid-2">
        <div><label>Nombres</label><input name="representative[first_name]" value="{{ $representative['first_name'] ?? '' }}"></div>
        <div><label>Apellidos</label><input name="representative[last_name]" value="{{ $representative['last_name'] ?? '' }}"></div>
        <div><label>Cédula</label><input name="representative[document_id]" value="{{ $representative['document_id'] ?? '' }}"></div>
        <div><label>Email</label><input type="email" name="representative[email]" value="{{ $representative['email'] ?? '' }}"></div>
        <div><label>Teléfono habitación</label><input name="representative[home_phone]" value="{{ $representative['home_phone'] ?? '' }}"></div>
        <div><label>Celular</label><input name="representative[mobile_phone]" value="{{ $representative['mobile_phone'] ?? ($representative['phone'] ?? '') }}"></div>
        <div><label>Teléfono contacto</label><input name="representative[phone]" value="{{ $representative['phone'] ?? '' }}"></div>
        <div><label>Teléfono oficina</label><input name="representative[office_phone]" value="{{ $representative['office_phone'] ?? '' }}"></div>
        <div><label>Lugar de trabajo</label><input name="representative[work_place]" value="{{ $representative['work_place'] ?? '' }}"></div>
        <div><label>Dirección oficina</label><input name="representative[work_address]" value="{{ $representative['work_address'] ?? '' }}"></div>
        <div style="grid-column:1/-1;"><label>Dirección de habitación</label><textarea name="representative[address]">{{ $representative['address'] ?? '' }}</textarea></div>
    </div>
</div>

@for($i = 0; $i < 2; $i++)
    <div class="card mt-2">
        <div class="section-head section-head-tight">
            <h2 class="section-title section-title-md">Persona autorizada ({{ $i + 1 }})</h2>
            <div class="entity-sub">Contacto autorizado para retiro o atención del alumno</div>
        </div>
        <div class="grid-2">
            <div><label>Nombres</label><input name="authorized_contacts[{{ $i }}][first_name]" value="{{ $authorizedContacts[$i]['first_name'] ?? '' }}"></div>
            <div><label>Apellidos</label><input name="authorized_contacts[{{ $i }}][last_name]" value="{{ $authorizedContacts[$i]['last_name'] ?? '' }}"></div>
            <div><label>Cédula</label><input name="authorized_contacts[{{ $i }}][document_id]" value="{{ $authorizedContacts[$i]['document_id'] ?? '' }}"></div>
            <div><label>Parentesco</label><input name="authorized_contacts[{{ $i }}][relationship]" value="{{ $authorizedContacts[$i]['relationship'] ?? '' }}"></div>
            <div><label>Teléfono habitación</label><input name="authorized_contacts[{{ $i }}][home_phone]" value="{{ $authorizedContacts[$i]['home_phone'] ?? '' }}"></div>
            <div><label>Celular</label><input name="authorized_contacts[{{ $i }}][mobile_phone]" value="{{ $authorizedContacts[$i]['mobile_phone'] ?? '' }}"></div>
            <div><label>Lugar de trabajo</label><input name="authorized_contacts[{{ $i }}][work_place]" value="{{ $authorizedContacts[$i]['work_place'] ?? '' }}"></div>
            <div><label>Dirección trabajo</label><input name="authorized_contacts[{{ $i }}][work_address]" value="{{ $authorizedContacts[$i]['work_address'] ?? '' }}"></div>
            <div style="grid-column:1/-1;"><label>Dirección de habitación</label><textarea name="authorized_contacts[{{ $i }}][address]">{{ $authorizedContacts[$i]['address'] ?? '' }}</textarea></div>
        </div>
    </div>
@endfor

<div class="card mt-2">
    <div class="section-head section-head-tight">
        <h2 class="section-title section-title-md">Ficha médica</h2>
        <div class="entity-sub">Información relevante de salud</div>
    </div>
    <div class="grid-2">
        <div>
            <label>¿Es alérgico?</label>
            <select name="medical_has_allergies">
                <option value="0" @selected(!old('medical_has_allergies', $student->medical_has_allergies ?? false))>No</option>
                <option value="1" @selected((bool) old('medical_has_allergies', $student->medical_has_allergies ?? false))>Sí</option>
            </select>
        </div>
        <div><label>Indique cuál(es)</label><input name="medical_allergy_details" value="{{ old('medical_allergy_details',$student->medical_allergy_details ?? '') }}"></div>
        <div>
            <label>¿Recibe tratamiento actualmente?</label>
            <select name="medical_has_treatment">
                <option value="0" @selected(!old('medical_has_treatment', $student->medical_has_treatment ?? false))>No</option>
                <option value="1" @selected((bool) old('medical_has_treatment', $student->medical_has_treatment ?? false))>Sí</option>
            </select>
        </div>
        <div><label>Explique tratamiento</label><input name="medical_treatment_details" value="{{ old('medical_treatment_details',$student->medical_treatment_details ?? '') }}"></div>
        <div><label>Medicamento autorizado en caso de fiebre</label><input name="medical_fever_medication" value="{{ old('medical_fever_medication',$student->medical_fever_medication ?? '') }}"></div>
        <div><label>Medicamento autorizado en caso de cefalea</label><input name="medical_headache_medication" value="{{ old('medical_headache_medication',$student->medical_headache_medication ?? '') }}"></div>
        <div style="grid-column:1/-1;"><label>Observaciones médicas</label><textarea name="medical_notes">{{ old('medical_notes',$student->medical_notes ?? '') }}</textarea></div>
    </div>
</div>

<div class="card mt-2">
    <div class="section-head section-head-tight">
        <h2 class="section-title section-title-md">Datos comerciales</h2>
        <div class="entity-sub">Información de venta, promoción y pago</div>
    </div>
    <div class="grid-2">
        <div><label>Vendedor</label><input name="salesperson" value="{{ old('salesperson',$student->salesperson ?? '') }}"></div>
        <div><label>Promoción</label><input name="promotion" value="{{ old('promotion',$student->promotion ?? '') }}"></div>
        <div><label>Método de pago</label><input name="payment_method" value="{{ old('payment_method',$student->payment_method ?? '') }}"></div>
        <div><label>Cuotas</label><input type="number" min="1" max="48" name="installments" value="{{ old('installments',$student->installments ?? '') }}"></div>
        <div style="grid-column:1/-1;"><label>Observaciones comerciales</label><textarea name="commercial_notes">{{ old('commercial_notes',$student->commercial_notes ?? '') }}</textarea></div>
    </div>
</div>

@once
    <link href="https://unpkg.com/filepond@^4/dist/filepond.min.css" rel="stylesheet">
    <script src="https://unpkg.com/filepond@^4/dist/filepond.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('input.filepond').forEach(function (input) {
                FilePond.create(input, {
                    storeAsFile: true,
                    allowMultiple: false,
                    maxFileSize: '5MB',
                    labelIdle: 'Arrastra tu imagen o <span class="filepond--label-action">buscar</span>',
                });
            });
        });
    </script>
@endonce
