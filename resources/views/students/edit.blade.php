@extends('layouts.app')
@section('content')
<div class="module-head"><div><h1 class="page-title">Editar alumno ✏️</h1><p class="page-subtitle">Actualiza datos personales y académicos del alumno</p></div></div>
<div class="card"><form method="POST" action="{{ route('students.update',$student) }}" enctype="multipart/form-data" data-guard-submit>@csrf @method('PUT') @include('students.form')<div class="form-actions"><button class="btn" type="submit" data-submit-busy-label="Actualizando…">Actualizar</button><a class="btn secondary" href="{{ route('students.index') }}">Volver</a></div></form></div>
@include('partials.ui.audit-timeline', ['auditLogs' => $auditLogs ?? collect()])
@endsection
