<div class="grid-2">
<div>@include('partials.ui.student-select', ['students' => $students, 'name' => 'student_id', 'id' => 'enrollment-student', 'required' => true, 'placeholder' => 'Selecciona un alumno', 'selected' => old('student_id', $enrollment->student_id ?? request('student_id', ''))])</div>
<div>@include('partials.ui.group-select', ['groups' => $groups, 'id' => 'enrollment-group', 'selected' => $enrollment->group_id ?? ''])</div>
<div><label>Fecha inscripción</label><input type="date" name="enrolled_at" value="{{ old('enrolled_at',isset($enrollment->enrolled_at)?$enrollment->enrolled_at?->format('Y-m-d'):'') }}"></div>
<div><label>Estado</label><select name="status">@foreach(['active','inactive','completed','withdrawn'] as $status)<option value="{{ $status }}" @selected(old('status',$enrollment->status ?? 'active')==$status)>{{ \App\Support\StatusLabel::label($status, 'enrollment') }}</option>@endforeach</select></div>
<div><label>Progreso (%)</label><input type="number" min="0" max="100" name="progress" value="{{ old('progress',$enrollment->progress ?? 0) }}"></div>
<div><label>Notas</label><textarea name="notes">{{ old('notes',$enrollment->notes ?? '') }}</textarea></div>
@if(empty($enrollment->id))<div>@include('partials.finance.skip-tuition')</div>@endif
</div>
