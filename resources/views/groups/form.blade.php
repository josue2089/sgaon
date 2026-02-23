<div class="grid-2">
<div><label>Campus</label><select name="campus_id">@foreach($campuses as $campus)<option value="{{ $campus->id }}" @selected(old('campus_id',$group->campus_id ?? '')==$campus->id)>{{ $campus->name }}</option>@endforeach</select></div>
<div><label>Curso</label><select name="course_id">@foreach($courses as $course)<option value="{{ $course->id }}" @selected(old('course_id',$group->course_id ?? '')==$course->id)>{{ $course->name }}</option>@endforeach</select></div>
<div><label>Profesor</label><select name="teacher_id"><option value="">Sin asignar</option>@foreach($teachers as $teacher)<option value="{{ $teacher->id }}" @selected(old('teacher_id',$group->teacher_id ?? '')==$teacher->id)>{{ $teacher->full_name }}</option>@endforeach</select></div>
<div><label>Nombre</label><input name="name" value="{{ old('name',$group->name ?? '') }}"></div>
<div><label>Periodo</label><input name="period" value="{{ old('period',$group->period ?? '') }}"></div>
<div><label>Horario</label><input name="schedule" value="{{ old('schedule',$group->schedule ?? '') }}"></div>
<div><label>Inicio</label><input type="date" name="start_date" value="{{ old('start_date',isset($group->start_date)?$group->start_date?->format('Y-m-d'):'') }}"></div>
<div><label>Fin</label><input type="date" name="end_date" value="{{ old('end_date',isset($group->end_date)?$group->end_date?->format('Y-m-d'):'') }}"></div>
<div><label>Status</label><select name="status">@foreach(['active','inactive'] as $status)<option value="{{ $status }}" @selected(old('status',$group->status ?? 'active')==$status)>{{ $status }}</option>@endforeach</select></div>
</div>
