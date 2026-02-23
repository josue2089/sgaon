<div class="grid-2">
<div><label>Campus</label><select name="campus_id">@foreach($campuses as $campus)<option value="{{ $campus->id }}" @selected(old('campus_id',$course->campus_id ?? '')==$campus->id)>{{ $campus->name }}</option>@endforeach</select></div>
<div><label>Nivel</label><select name="academic_level_id">@foreach($levels as $level)<option value="{{ $level->id }}" @selected(old('academic_level_id',$course->academic_level_id ?? '')==$level->id)>{{ $level->name }}</option>@endforeach</select></div>
<div><label>Nombre</label><input name="name" value="{{ old('name',$course->name ?? '') }}"></div>
<div><label>Código</label><input name="code" value="{{ old('code',$course->code ?? '') }}"></div>
<div><label>Status</label><select name="status">@foreach(['active','inactive'] as $status)<option value="{{ $status }}" @selected(old('status',$course->status ?? 'active')==$status)>{{ $status }}</option>@endforeach</select></div>
<div><label>Descripción</label><textarea name="description">{{ old('description',$course->description ?? '') }}</textarea></div>
</div>
