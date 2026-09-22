@extends('layouts.app')
@section('content')
@php
    $fromCourse = request('redirect_to', old('redirect_to')) === 'course';
    $backCourse = $fromCourse ? (\App\Models\Course::query()->where('managed_group_id', $session->group_id)->first() ?? $session->group?->course) : null;
@endphp
<div class="module-head"><div><h1 class="page-title">Editar sesión ✏️</h1><p class="page-subtitle">Ajusta fecha, hora y tema de la sesión. Si la mueves después del fin del curso, la fecha de fin se actualiza sola.</p></div></div>
<div class="card"><form method="POST" action="{{ route('sessions.update',$session) }}" data-guard-submit>@csrf @method('PUT')
@if($fromCourse)<input type="hidden" name="redirect_to" value="course">@endif
@include('sessions.form')<div class="form-actions"><button class="btn" type="submit" data-submit-busy-label="Actualizando…">Actualizar</button><a class="btn secondary" href="{{ $backCourse ? route('courses.show', $backCourse) : route('sessions.index') }}">Volver</a></div></form></div>
@endsection
