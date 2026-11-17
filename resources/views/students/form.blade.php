@php
    $representative = old('representative', optional(($student->representatives ?? collect())->first())->toArray() ?? []);
    $authorizedContacts = old('authorized_contacts', ($student->authorizedContacts ?? collect())->sortBy('slot')->values()->map->toArray()->all());
    $authorizedContacts = array_values($authorizedContacts);
    $authorizedContacts[0] = $authorizedContacts[0] ?? [];
    $authorizedContacts[1] = $authorizedContacts[1] ?? [];
@endphp

<div class="grid-2">
    <div>
        <label for="student-profile-photo">Foto de perfil</label>
        @if(!empty($student?->profile_photo_path))
            <div class="profile-photo-preview-wrap">
                <img class="profile-photo-preview" src="{{ \Illuminate\Support\Facades\Storage::url($student->profile_photo_path) }}" alt="Foto actual del alumno">
            </div>
        @endif
        <input id="student-profile-photo" type="file" class="filepond" name="profile_photo" accept="image/png,image/jpeg,image/webp">
    </div>
    <div>
        <label for="student-contract-number">N° Contrato</label>
        <input id="student-contract-number" name="contract_number" value="{{ old('contract_number', $student->contract_number ?? '') }}">
    </div>

    <div>
        <label for="student-campus-id">Campus</label>
        <select id="student-campus-id" name="campus_id" required>
            @foreach($campuses as $campus)
                <option value="{{ $campus->id }}" @selected(old('campus_id',$student->campus_id ?? '')==$campus->id)>{{ $campus->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="student-enrollment-date">Fecha de inscripción</label>
        <input id="student-enrollment-date" type="date" name="enrollment_date" value="{{ old('enrollment_date',isset($student->enrollment_date)?$student->enrollment_date?->format('Y-m-d'):'') }}">
    </div>
</div>

<div class="card mt-2">
    <div class="section-head section-head-tight">
        <h2 class="section-title section-title-md">Datos del alumno</h2>
        <div class="entity-sub">Información principal de inscripción</div>
    </div>
    <div class="grid-2">
        <x-form.field label="Nombres" name="first_name" for="student-first-name" required><input id="student-first-name" name="first_name" value="{{ old('first_name',$student->first_name ?? '') }}" required></x-form.field>
        <x-form.field label="Apellidos" name="last_name" for="student-last-name" required><input id="student-last-name" name="last_name" value="{{ old('last_name',$student->last_name ?? '') }}" required></x-form.field>
        <x-form.field label="N° Cédula" name="document_id" for="student-document-id"><input id="student-document-id" name="document_id" value="{{ old('document_id',$student->document_id ?? '') }}"></x-form.field>
        <x-form.field label="Fecha de nacimiento" name="birth_date" for="student-birth-date"><input id="student-birth-date" type="date" name="birth_date" value="{{ old('birth_date',isset($student->birth_date)?$student->birth_date?->format('Y-m-d'):'') }}"></x-form.field>
        <x-form.field label="Email" name="email" for="student-email"><input id="student-email" name="email" type="email" value="{{ old('email',$student->email ?? '') }}"></x-form.field>
        <x-form.field label="Celular" name="mobile_phone" for="student-mobile-phone"><input id="student-mobile-phone" name="mobile_phone" value="{{ old('mobile_phone',$student->mobile_phone ?? $student->phone ?? '') }}"></x-form.field>
        <x-form.field label="Teléfono" name="landline_phone" for="student-landline-phone"><input id="student-landline-phone" name="landline_phone" value="{{ old('landline_phone',$student->landline_phone ?? '') }}"></x-form.field>
        <div><label for="student-registration-program-id">Programa de inscripción</label>
            <select id="student-registration-program-id" name="registration_program_id">
                <option value="">Seleccione</option>
                @foreach($programs as $program)
                    <option value="{{ $program->id }}" data-extracurricular="{{ $program->is_extracurricular ? 1 : 0 }}" @selected((string) old('registration_program_id', $student->registration_program_id ?? '') === (string) $program->id)>{{ $program->name }}{{ $program->is_extracurricular ? ' (extracurricular)' : '' }}</option>
                @endforeach
            </select>
        </div>
        <x-form.field label="Dirección" name="address" for="student-address" class="col-span-full"><textarea id="student-address" name="address">{{ old('address',$student->address ?? '') }}</textarea></x-form.field>
        <div>
            <label for="student-family-in-institution">¿Tiene familiar en la institución?</label>
            <select id="student-family-in-institution" name="family_in_institution">
                <option value="0" @selected(!old('family_in_institution', $student->family_in_institution ?? false))>No</option>
                <option value="1" @selected((bool) old('family_in_institution', $student->family_in_institution ?? false))>Sí</option>
            </select>
        </div>
        <x-form.field label="Detalle familiar" name="family_in_institution_details" for="student-family-in-institution-details"><input id="student-family-in-institution-details" name="family_in_institution_details" value="{{ old('family_in_institution_details',$student->family_in_institution_details ?? '') }}" placeholder="Nombre o referencia"></x-form.field>
        <div>
            <label for="student-status">Estado</label>
            <select id="student-status" name="status">
                @foreach(['active','inactive','withdrawn','graduated'] as $status)
                    <option value="{{ $status }}" @selected(old('status',$student->status ?? 'active')==$status)>{{ \App\Support\StatusLabel::label($status) }}</option>
                @endforeach
            </select>
        </div>
    </div>
</div>

<details class="card mt-2 form-section" open>
    <summary class="section-head section-head-tight form-section-summary">
        <h2 class="section-title section-title-md">Datos del representante</h2>
        <div class="entity-sub">Representante principal asociado al alumno</div>
    </summary>
    <div class="grid-2">
        <x-form.field label="Nombres" name="representative[first_name]" for="student-representative-first-name"><input id="student-representative-first-name" name="representative[first_name]" value="{{ $representative['first_name'] ?? '' }}"></x-form.field>
        <x-form.field label="Apellidos" name="representative[last_name]" for="student-representative-last-name"><input id="student-representative-last-name" name="representative[last_name]" value="{{ $representative['last_name'] ?? '' }}"></x-form.field>
        <x-form.field label="Cédula" name="representative[document_id]" for="student-representative-document-id"><input id="student-representative-document-id" name="representative[document_id]" value="{{ $representative['document_id'] ?? '' }}"></x-form.field>
        <div><label for="student-representative-nationality">Nacionalidad</label>
            <select id="student-representative-nationality" name="representative[nationality]">
                <option value="">Seleccione</option>
                <option value="V" @selected(($representative['nationality'] ?? '') === 'V')>V (venezolano)</option>
                <option value="E" @selected(($representative['nationality'] ?? '') === 'E')>E (extranjero)</option>
            </select>
        </div>
        <x-form.field label="Parentesco con el alumno" name="representative[relation]" for="student-representative-relation"><input id="student-representative-relation" name="representative[relation]" value="{{ $representative['relation'] ?? '' }}" placeholder="Madre, padre, abuelo…"></x-form.field>
        <x-form.field label="Email" name="representative[email]" for="student-representative-email"><input id="student-representative-email" type="email" name="representative[email]" value="{{ $representative['email'] ?? '' }}"></x-form.field>
        <x-form.field label="Teléfono habitación" name="representative[home_phone]" for="student-representative-home-phone"><input id="student-representative-home-phone" name="representative[home_phone]" value="{{ $representative['home_phone'] ?? '' }}"></x-form.field>
        <x-form.field label="Celular" name="representative[mobile_phone]" for="student-representative-mobile-phone"><input id="student-representative-mobile-phone" name="representative[mobile_phone]" value="{{ $representative['mobile_phone'] ?? ($representative['phone'] ?? '') }}"></x-form.field>
        <x-form.field label="Teléfono contacto" name="representative[phone]" for="student-representative-phone"><input id="student-representative-phone" name="representative[phone]" value="{{ $representative['phone'] ?? '' }}"></x-form.field>
        <x-form.field label="Teléfono oficina" name="representative[office_phone]" for="student-representative-office-phone"><input id="student-representative-office-phone" name="representative[office_phone]" value="{{ $representative['office_phone'] ?? '' }}"></x-form.field>
        <x-form.field label="Lugar de trabajo" name="representative[work_place]" for="student-representative-work-place"><input id="student-representative-work-place" name="representative[work_place]" value="{{ $representative['work_place'] ?? '' }}"></x-form.field>
        <x-form.field label="Dirección oficina" name="representative[work_address]" for="student-representative-work-address"><input id="student-representative-work-address" name="representative[work_address]" value="{{ $representative['work_address'] ?? '' }}"></x-form.field>
        <x-form.field label="Dirección de habitación" name="representative[address]" for="student-representative-address" class="col-span-full"><textarea id="student-representative-address" name="representative[address]">{{ $representative['address'] ?? '' }}</textarea></x-form.field>
    </div>
</details>

@for($i = 0; $i < 2; $i++)
    <details class="card mt-2 form-section" @if(filled($authorizedContacts[$i]['first_name'] ?? null) || filled($authorizedContacts[$i]['document_id'] ?? null) || ($i === 0 && empty($student->id))) open @endif>
    <summary class="section-head section-head-tight form-section-summary">
            <h2 class="section-title section-title-md">Persona autorizada ({{ $i + 1 }})</h2>
            <div class="entity-sub">Contacto autorizado para retiro o atención del alumno</div>
        </summary>
        <div class="grid-2">
            <x-form.field label="Nombres" name="authorized_contacts[{{ $i }}][first_name]" for="student-authorized-contacts-{{ $i }}-first-name"><input id="student-authorized-contacts-{{ $i }}-first-name" name="authorized_contacts[{{ $i }}][first_name]" value="{{ $authorizedContacts[$i]['first_name'] ?? '' }}"></x-form.field>
            <x-form.field label="Apellidos" name="authorized_contacts[{{ $i }}][last_name]" for="student-authorized-contacts-{{ $i }}-last-name"><input id="student-authorized-contacts-{{ $i }}-last-name" name="authorized_contacts[{{ $i }}][last_name]" value="{{ $authorizedContacts[$i]['last_name'] ?? '' }}"></x-form.field>
            <x-form.field label="Cédula" name="authorized_contacts[{{ $i }}][document_id]" for="student-authorized-contacts-{{ $i }}-document-id"><input id="student-authorized-contacts-{{ $i }}-document-id" name="authorized_contacts[{{ $i }}][document_id]" value="{{ $authorizedContacts[$i]['document_id'] ?? '' }}"></x-form.field>
            <x-form.field label="Parentesco" name="authorized_contacts[{{ $i }}][relationship]" for="student-authorized-contacts-{{ $i }}-relationship"><input id="student-authorized-contacts-{{ $i }}-relationship" name="authorized_contacts[{{ $i }}][relationship]" value="{{ $authorizedContacts[$i]['relationship'] ?? '' }}"></x-form.field>
            <x-form.field label="Teléfono habitación" name="authorized_contacts[{{ $i }}][home_phone]" for="student-authorized-contacts-{{ $i }}-home-phone"><input id="student-authorized-contacts-{{ $i }}-home-phone" name="authorized_contacts[{{ $i }}][home_phone]" value="{{ $authorizedContacts[$i]['home_phone'] ?? '' }}"></x-form.field>
            <x-form.field label="Celular" name="authorized_contacts[{{ $i }}][mobile_phone]" for="student-authorized-contacts-{{ $i }}-mobile-phone"><input id="student-authorized-contacts-{{ $i }}-mobile-phone" name="authorized_contacts[{{ $i }}][mobile_phone]" value="{{ $authorizedContacts[$i]['mobile_phone'] ?? '' }}"></x-form.field>
            <x-form.field label="Lugar de trabajo" name="authorized_contacts[{{ $i }}][work_place]" for="student-authorized-contacts-{{ $i }}-work-place"><input id="student-authorized-contacts-{{ $i }}-work-place" name="authorized_contacts[{{ $i }}][work_place]" value="{{ $authorizedContacts[$i]['work_place'] ?? '' }}"></x-form.field>
            <x-form.field label="Dirección trabajo" name="authorized_contacts[{{ $i }}][work_address]" for="student-authorized-contacts-{{ $i }}-work-address"><input id="student-authorized-contacts-{{ $i }}-work-address" name="authorized_contacts[{{ $i }}][work_address]" value="{{ $authorizedContacts[$i]['work_address'] ?? '' }}"></x-form.field>
            <x-form.field label="Dirección de habitación" name="authorized_contacts[{{ $i }}][address]" for="student-authorized-contacts-{{ $i }}-address" class="col-span-full"><textarea id="student-authorized-contacts-{{ $i }}-address" name="authorized_contacts[{{ $i }}][address]">{{ $authorizedContacts[$i]['address'] ?? '' }}</textarea></x-form.field>
        </div>
    </details>
@endfor

@php
    $showExtracurricular = old('registration_program_id')
        ? (bool) optional($programs->firstWhere('id', (int) old('registration_program_id')))->is_extracurricular
        : (isset($student->id) && $student->isExtracurricular()) || (bool) optional($student->registrationProgram ?? null)->is_extracurricular;
@endphp
<div class="card mt-2" id="extracurricular-section"{{ $showExtracurricular ? '' : ' hidden' }}>
    <div class="section-head section-head-tight">
        <h2 class="section-title section-title-md">Actividad extracurricular</h2>
        <div class="entity-sub">Planilla de inscripción de actividades extracurriculares en colegios</div>
    </div>
    <fieldset id="extracurricular-fields" style="border:0;padding:0;margin:0;min-width:0;" @disabled(! $showExtracurricular)>
    <div class="grid-2">
        <x-form.field label="Grado / Año" name="school_grade" for="student-school-grade"><input id="student-school-grade" name="school_grade" value="{{ old('school_grade', $student->school_grade ?? '') }}" placeholder="Ej. 4to grado"></x-form.field>
        <x-form.field label="Sección" name="school_section" for="student-school-section"><input id="student-school-section" name="school_section" value="{{ old('school_section', $student->school_section ?? '') }}" placeholder="Ej. U"></x-form.field>
        <x-form.field label="Teléfono de emergencia" name="emergency_phone" for="student-emergency-phone"><input id="student-emergency-phone" name="emergency_phone" value="{{ old('emergency_phone', $student->emergency_phone ?? '') }}"></x-form.field>
        <x-form.field label="Nivel / Dominio actual" name="extracurricular_level" for="student-extracurricular-level"><input id="student-extracurricular-level" name="extracurricular_level" value="{{ old('extracurricular_level', $student->extracurricular_level ?? '') }}"></x-form.field>
        <x-form.field label="Objetivos / Competencias a desarrollar" name="extracurricular_objectives" for="student-extracurricular-objectives" class="col-span-full"><textarea id="student-extracurricular-objectives" name="extracurricular_objectives">{{ old('extracurricular_objectives', $student->extracurricular_objectives ?? \App\Models\Student::DEFAULT_EXTRACURRICULAR_OBJECTIVES) }}</textarea></x-form.field>
        <x-form.field label="Observaciones / Recomendaciones del docente" name="teacher_observations" for="student-teacher-observations" class="col-span-full"><textarea id="student-teacher-observations" name="teacher_observations">{{ old('teacher_observations', $student->teacher_observations ?? '') }}</textarea></x-form.field>
        <x-form.field label="Condición de pago / Matrícula" name="payment_condition" for="student-payment-condition" class="col-span-full"><input id="student-payment-condition" name="payment_condition" value="{{ old('payment_condition', $student->payment_condition ?? '') }}" placeholder="Ej. 10 cuotas de 35 USD (octubre a julio)"></x-form.field>
    </div>
    </fieldset>
</div>

<details class="card mt-2 form-section" @if((bool) old('medical_has_allergies', $student->medical_has_allergies ?? false) || (bool) old('medical_has_treatment', $student->medical_has_treatment ?? false) || filled(old('medical_notes', $student->medical_notes ?? null))) open @endif>
    <summary class="section-head section-head-tight form-section-summary">
        <h2 class="section-title section-title-md">Ficha médica</h2>
        <div class="entity-sub">Información relevante de salud</div>
    </summary>
    <div class="grid-2">
        <div>
            <label for="student-medical-has-allergies">¿Es alérgico?</label>
            <select id="student-medical-has-allergies" name="medical_has_allergies">
                <option value="0" @selected(!old('medical_has_allergies', $student->medical_has_allergies ?? false))>No</option>
                <option value="1" @selected((bool) old('medical_has_allergies', $student->medical_has_allergies ?? false))>Sí</option>
            </select>
        </div>
        <x-form.field label="Indique cuál(es)" name="medical_allergy_details" for="student-medical-allergy-details"><input id="student-medical-allergy-details" name="medical_allergy_details" value="{{ old('medical_allergy_details',$student->medical_allergy_details ?? '') }}"></x-form.field>
        <div>
            <label for="student-medical-has-treatment">¿Recibe tratamiento actualmente?</label>
            <select id="student-medical-has-treatment" name="medical_has_treatment">
                <option value="0" @selected(!old('medical_has_treatment', $student->medical_has_treatment ?? false))>No</option>
                <option value="1" @selected((bool) old('medical_has_treatment', $student->medical_has_treatment ?? false))>Sí</option>
            </select>
        </div>
        <x-form.field label="Explique tratamiento" name="medical_treatment_details" for="student-medical-treatment-details"><input id="student-medical-treatment-details" name="medical_treatment_details" value="{{ old('medical_treatment_details',$student->medical_treatment_details ?? '') }}"></x-form.field>
        <x-form.field label="Medicamento autorizado en caso de fiebre" name="medical_fever_medication" for="student-medical-fever-medication"><input id="student-medical-fever-medication" name="medical_fever_medication" value="{{ old('medical_fever_medication',$student->medical_fever_medication ?? '') }}"></x-form.field>
        <x-form.field label="Medicamento autorizado en caso de cefalea" name="medical_headache_medication" for="student-medical-headache-medication"><input id="student-medical-headache-medication" name="medical_headache_medication" value="{{ old('medical_headache_medication',$student->medical_headache_medication ?? '') }}"></x-form.field>
        <x-form.field label="Observaciones médicas" name="medical_notes" for="student-medical-notes" class="col-span-full"><textarea id="student-medical-notes" name="medical_notes">{{ old('medical_notes',$student->medical_notes ?? '') }}</textarea></x-form.field>
    </div>
</details>

<details class="card mt-2 form-section" @if(filled(old('salesperson', $student->salesperson ?? null)) || filled(old('promotion', $student->promotion ?? null)) || filled(old('commercial_notes', $student->commercial_notes ?? null))) open @endif>
    <summary class="section-head section-head-tight form-section-summary">
        <h2 class="section-title section-title-md">Datos comerciales</h2>
        <div class="entity-sub">Información de venta, promoción y pago</div>
    </summary>
    <div class="grid-2">
        <x-form.field label="Vendedor" name="salesperson" for="student-salesperson"><input id="student-salesperson" name="salesperson" value="{{ old('salesperson',$student->salesperson ?? '') }}"></x-form.field>
        <x-form.field label="Promoción" name="promotion" for="student-promotion"><input id="student-promotion" name="promotion" value="{{ old('promotion',$student->promotion ?? '') }}"></x-form.field>
        <x-form.field label="Método de pago" name="payment_method" for="student-payment-method"><input id="student-payment-method" name="payment_method" value="{{ old('payment_method',$student->payment_method ?? '') }}"></x-form.field>
        <x-form.field label="Cuotas" name="installments" for="student-installments"><input id="student-installments" type="number" min="1" max="48" name="installments" value="{{ old('installments',$student->installments ?? '') }}"></x-form.field>
        <x-form.field label="Observaciones comerciales" name="commercial_notes" for="student-commercial-notes" class="col-span-full"><textarea id="student-commercial-notes" name="commercial_notes">{{ old('commercial_notes',$student->commercial_notes ?? '') }}</textarea></x-form.field>
    </div>
</details>

@once
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (document.getElementById('form-errors')) {
                document.querySelectorAll('details.form-section').forEach(function (section) { section.open = true; });
            }
        });
    </script>
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

            const programSelect = document.querySelector('select[name="registration_program_id"]');
            const extracurricularSection = document.getElementById('extracurricular-section');
            if (programSelect && extracurricularSection) {
                programSelect.addEventListener('change', function () {
                    const option = programSelect.selectedOptions[0];
                    const isExtracurricular = !!option && option.dataset.extracurricular === '1';
                    extracurricularSection.hidden = !isExtracurricular;
                    document.getElementById('extracurricular-fields').disabled = !isExtracurricular;
                });
            }
        });
    </script>
@endonce
