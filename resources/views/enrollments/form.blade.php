<div class="grid-2">
@if(empty($enrollment->id))
<div>@include('partials.ui.student-select', ['students' => $students, 'name' => 'student_id', 'id' => 'enrollment-student', 'required' => true, 'remote' => true, 'placeholder' => 'Selecciona un alumno', 'selected' => old('student_id', $enrollment->student_id ?? request('student_id', ''))])</div>
<div>@include('partials.ui.group-select', ['groups' => $groups, 'id' => 'enrollment-group', 'selected' => $enrollment->group_id ?? ''])</div>
@else
<div><label>Alumno</label><div class="table-title">{{ $enrollment->student?->full_name ?? 'N/D' }}</div></div>
<div><label>Curso</label><div class="table-title">{{ $enrollment->group?->course?->name ?? $enrollment->group?->name ?? 'N/D' }}</div><div class="entity-sub">Para cambiarlo usa "Cambiar de curso", que también maneja los cargos.</div></div>
@endif
<x-form.field label="Fecha inscripción" name="enrolled_at" for="enrollment-enrolled-at"><input id="enrollment-enrolled-at" type="date" name="enrolled_at" value="{{ old('enrolled_at',isset($enrollment->enrolled_at)?$enrollment->enrolled_at?->format('Y-m-d'):'') }}"></x-form.field>
<x-form.field label="Estado" name="status" for="enrollment-status"><select id="enrollment-status" name="status">@foreach(empty($enrollment->id) ? ['active','inactive','completed','withdrawn'] : array_unique(['active','inactive','completed',$enrollment->status]) as $status)<option value="{{ $status }}" @selected(old('status',$enrollment->status ?? 'active')==$status)>{{ \App\Support\StatusLabel::label($status, 'enrollment') }}</option>@endforeach</select></x-form.field>
<x-form.field label="Progreso (%)" name="progress" for="enrollment-progress"><input id="enrollment-progress" type="number" min="0" max="100" name="progress" value="{{ old('progress',$enrollment->progress ?? 0) }}"></x-form.field>
<x-form.field label="Notas" name="notes" for="enrollment-notes"><textarea id="enrollment-notes" name="notes">{{ old('notes',$enrollment->notes ?? '') }}</textarea></x-form.field>
@if(empty($enrollment->id))<div>@include('partials.finance.skip-tuition')</div>@endif
</div>
