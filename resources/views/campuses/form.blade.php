<div class="grid-2">
    <div>
        <label>Nombre</label>
        <input name="name" value="{{ old('name', $campus->name ?? '') }}" required>
    </div>
    <div>
        <label>Código</label>
        <input name="code" value="{{ old('code', $campus->code ?? '') }}" required>
    </div>
    <div>
        <label>Ciudad</label>
        <input name="city" value="{{ old('city', $campus->city ?? '') }}">
    </div>
    <div>
        <label>Estado / Provincia</label>
        <input name="state" value="{{ old('state', $campus->state ?? '') }}">
    </div>
    <div>
        <label>País</label>
        <input name="country" value="{{ old('country', $campus->country ?? '') }}">
    </div>
    <div>
        <label for="campus-contact-phone">Teléfono o WhatsApp de contacto</label>
        <input id="campus-contact-phone" name="contact_phone" value="{{ old('contact_phone', $campus->contact_phone ?? '') }}" placeholder="0414-1234567">
        <div class="form-hint">Lo ven las familias en el portal cuando necesitan ayuda.</div>
    </div>
    <div>
        <label for="campus-contact-email">Correo de contacto</label>
        <input id="campus-contact-email" type="email" name="contact_email" value="{{ old('contact_email', $campus->contact_email ?? '') }}">
    </div>
    <div>
        <label>Logo de la sede (PNG o JPG)</label>
        @if(!empty($campus->logo_path))
            <img src="{{ \Illuminate\Support\Facades\Storage::url($campus->logo_path) }}" alt="Logo de {{ $campus->name }}" style="max-height:56px;display:block;margin-bottom:.5rem;">
        @endif
        <input type="file" name="logo" accept="image/png,image/jpeg">
        <div class="form-hint">Se usa en la planilla de inscripción extracurricular. Ej. logo del colegio.</div>
    </div>
    <div>
        <label>Estado</label>
        <select name="status" required>
            @foreach($statusOptions as $status)
                <option value="{{ $status }}" @selected(old('status', $campus->status ?? 'active') === $status)>{{ \App\Support\StatusLabel::label($status, 'campus') }}</option>
            @endforeach
        </select>
    </div>
</div>
