{{-- #447: una tarjeta por alumno con botones segmentados; cada rubro es opcional y se puede guardar a medias. --}}
{{-- Espera $roster (enrollment_id => ['enrollment' => Enrollment, 'entry' => ?GradeEntry]), $skillColumns, $skillLabels, $ratingOptions, $ratingLabels. --}}
@error('entries')<div class="form-error grades-form-error">{{ $message }}</div>@enderror
<div class="grades-card-grid">
    @foreach($roster as $enrollmentId => $item)
        @php($entry = $item['entry'])
        @php($studentName = $item['enrollment']->student?->full_name ?? 'Alumno #'.$item['enrollment']->student_id)
        <fieldset class="card grades-student-card">
            <legend class="grades-student-card-name">{{ $studentName }}</legend>
            @foreach($skillColumns as $skill => $column)
                @php($current = old('entries.'.$enrollmentId.'.'.$column, $entry?->{$column}))
                <div class="grades-skill-row" role="radiogroup" aria-label="{{ $skillLabels[$skill] }} · {{ $studentName }}">
                    <span class="grades-skill-label">{{ $skillLabels[$skill] }}</span>
                    <div class="grades-segmented">
                        <label class="grades-segment grades-segment-empty">
                            <input type="radio" name="entries[{{ $enrollmentId }}][{{ $column }}]" value="" @checked(blank($current))>
                            <span>Sin nota</span>
                        </label>
                        @foreach($ratingOptions as $value)
                            <label class="grades-segment grades-segment-{{ \App\Support\GradeRubric::ratingTone($value) }}">
                                <input type="radio" name="entries[{{ $enrollmentId }}][{{ $column }}]" value="{{ $value }}" @checked($current === $value)>
                                <span>{{ $ratingLabels[$value] }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('entries.'.$enrollmentId.'.'.$column)<div class="form-error">{{ $message }}</div>@enderror
                </div>
            @endforeach
            <label class="grades-skill-label" for="obs-{{ $enrollmentId }}">Observaciones</label>
            <textarea id="obs-{{ $enrollmentId }}" name="entries[{{ $enrollmentId }}][observations]" rows="2" class="grades-matrix-notes" placeholder="Opcional">{{ old('entries.'.$enrollmentId.'.observations', $entry?->observations) }}</textarea>
        </fieldset>
    @endforeach
</div>
