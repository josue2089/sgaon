{{-- #453: cada campo con x-form.field (etiqueta ligada al control y error junto al campo). --}}
<div class="grid-2">
<x-form.field label="Foto de perfil" name="profile_photo" for="teacher-photo">
    @if(!empty($teacher?->profile_photo_path))
        <div class="profile-photo-preview-wrap">
            <img class="profile-photo-preview" src="{{ \Illuminate\Support\Facades\Storage::url($teacher->profile_photo_path) }}" alt="Foto actual del profesor">
        </div>
    @endif
    <input type="file" id="teacher-photo" class="filepond" name="profile_photo" accept="image/png,image/jpeg,image/webp">
</x-form.field>
<x-form.field label="Sede" name="campus_id" for="teacher-campus" required>
    <select id="teacher-campus" name="campus_id" required>@foreach($campuses as $campus)<option value="{{ $campus->id }}" @selected(old('campus_id',$teacher->campus_id ?? '')==$campus->id)>{{ $campus->name }}</option>@endforeach</select>
</x-form.field>
<x-form.field label="Nombre" name="first_name" for="teacher-first-name" required>
    <input id="teacher-first-name" name="first_name" value="{{ old('first_name',$teacher->first_name ?? '') }}" required autocomplete="given-name">
</x-form.field>
<x-form.field label="Apellido" name="last_name" for="teacher-last-name" required>
    <input id="teacher-last-name" name="last_name" value="{{ old('last_name',$teacher->last_name ?? '') }}" required autocomplete="family-name">
</x-form.field>
<x-form.field label="Documento" name="document_id" for="teacher-document">
    <input id="teacher-document" name="document_id" value="{{ old('document_id',$teacher->document_id ?? '') }}">
</x-form.field>
<x-form.field label="Email" name="email" for="teacher-email">
    <input id="teacher-email" type="email" name="email" value="{{ old('email',$teacher->email ?? '') }}" autocomplete="email">
    @if(isset($teacher->id))
        <div class="form-hint">
            @if($teacher->user)
                Acceso a la plataforma: {{ $teacher->user->email }}{{ $teacher->user->role !== 'teacher' ? ' (usuario con rol '.\App\Support\StatusLabel::role($teacher->user->role).')' : '' }}{{ $teacher->user->status === 'inactive' ? ' · inactivo' : '' }}. Si cambias el email, el acceso cambia con él.
            @else
                Sin usuario de acceso todavía: se crea la primera vez que el profesor entra con este email.
            @endif
        </div>
    @endif
</x-form.field>
<x-form.field label="Teléfono" name="phone" for="teacher-phone">
    <input id="teacher-phone" type="tel" name="phone" value="{{ old('phone',$teacher->phone ?? '') }}" autocomplete="tel">
</x-form.field>
<x-form.field label="Estado" name="status" for="teacher-status" required>
    <select id="teacher-status" name="status" required>@foreach(['active','inactive'] as $status)<option value="{{ $status }}" @selected(old('status',$teacher->status ?? 'active')==$status)>{{ \App\Support\StatusLabel::label($status) }}</option>@endforeach</select>
</x-form.field>
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
