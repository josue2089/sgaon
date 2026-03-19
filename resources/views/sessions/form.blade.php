<div class="grid-2">
<div><label>Grupo</label><select name="group_id">@foreach($groups as $group)<option value="{{ $group->id }}" @selected(old('group_id',$session->group_id ?? '')==$group->id)>{{ $group->name }} - {{ $group->course->name ?? '' }}</option>@endforeach</select></div>
<div><label>Fecha</label><input type="date" name="session_date" value="{{ old('session_date',isset($session->session_date)?$session->session_date?->format('Y-m-d'):'') }}"></div>
<div><label>Inicio</label><input type="time" name="starts_at" value="{{ old('starts_at',$session->starts_at ?? '') }}"></div>
<div><label>Fin</label><input type="time" name="ends_at" value="{{ old('ends_at',$session->ends_at ?? '') }}"></div>
<div><label>Tema</label><input name="topic" value="{{ old('topic',$session->topic ?? '') }}"></div>
<div><label>Avance del programa</label><select name="program_status"><option value="">Sin indicar</option><option value="on_track" @selected(old('program_status',$session->program_status ?? '')==='on_track')>Al día</option><option value="delayed" @selected(old('program_status',$session->program_status ?? '')==='delayed')>Con retraso</option></select></div>
<div style="grid-column:1/-1;"><label>Observación del programa</label><textarea name="program_notes">{{ old('program_notes',$session->program_notes ?? '') }}</textarea></div>
</div>
