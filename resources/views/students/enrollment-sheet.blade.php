@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">Ficha de inscripción</h1>
        <p class="page-subtitle">Versión imprimible completa del alumno.</p>
    </div>
    <div class="form-actions">
        <a class="btn secondary" href="{{ route('students.enrollment-sheet.pdf', $student) }}">Descargar PDF</a>
        <button class="btn secondary" type="button" onclick="window.print()">Imprimir</button>
        <a class="btn secondary" href="{{ route('students.show', $student) }}">Volver</a>
    </div>
</div>

@include('students.partials.enrollment-sheet-content')
@endsection
