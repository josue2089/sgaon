<div class="grid-2">
<div><label>Campus</label><select name="campus_id">@foreach($campuses as $campus)<option value="{{ $campus->id }}" @selected(old('campus_id',$student->campus_id ?? '')==$campus->id)>{{ $campus->name }}</option>@endforeach</select></div>
<div><label>Nombre</label><input name="first_name" value="{{ old('first_name',$student->first_name ?? '') }}"></div>
<div><label>Apellido</label><input name="last_name" value="{{ old('last_name',$student->last_name ?? '') }}"></div>
<div><label>Documento</label><input name="document_id" value="{{ old('document_id',$student->document_id ?? '') }}"></div>
<div><label>Email</label><input name="email" value="{{ old('email',$student->email ?? '') }}"></div>
<div><label>Teléfono</label><input name="phone" value="{{ old('phone',$student->phone ?? '') }}"></div>
<div><label>Fecha nacimiento</label><input type="date" name="birth_date" value="{{ old('birth_date',isset($student->birth_date)?$student->birth_date?->format('Y-m-d'):'') }}"></div>
<div><label>Fecha inscripción</label><input type="date" name="enrollment_date" value="{{ old('enrollment_date',isset($student->enrollment_date)?$student->enrollment_date?->format('Y-m-d'):'') }}"></div>
<div><label>Status</label><select name="status">@foreach(['active','inactive','withdrawn','graduated'] as $status)<option value="{{ $status }}" @selected(old('status',$student->status ?? 'active')==$status)>{{ $status }}</option>@endforeach</select></div>
<div><label>Dirección</label><textarea name="address">{{ old('address',$student->address ?? '') }}</textarea></div>
</div>
