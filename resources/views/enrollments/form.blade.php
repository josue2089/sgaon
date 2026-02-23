<div class="grid-2">
<div><label>Alumno</label><select name="student_id">@foreach($students as $student)<option value="{{ $student->id }}" @selected(old('student_id',$enrollment->student_id ?? '')==$student->id)>{{ $student->full_name }}</option>@endforeach</select></div>
<div><label>Grupo</label><select name="group_id">@foreach($groups as $group)<option value="{{ $group->id }}" @selected(old('group_id',$enrollment->group_id ?? '')==$group->id)>{{ $group->name }} - {{ $group->course->name ?? '' }}</option>@endforeach</select></div>
<div><label>Fecha inscripción</label><input type="date" name="enrolled_at" value="{{ old('enrolled_at',isset($enrollment->enrolled_at)?$enrollment->enrolled_at?->format('Y-m-d'):'') }}"></div>
<div><label>Status</label><select name="status">@foreach(['active','inactive','completed','withdrawn'] as $status)<option value="{{ $status }}" @selected(old('status',$enrollment->status ?? 'active')==$status)>{{ $status }}</option>@endforeach</select></div>
<div><label>Progreso (%)</label><input type="number" min="0" max="100" name="progress" value="{{ old('progress',$enrollment->progress ?? 0) }}"></div>
<div><label>Notas</label><textarea name="notes">{{ old('notes',$enrollment->notes ?? '') }}</textarea></div>
</div>
