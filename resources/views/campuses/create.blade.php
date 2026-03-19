@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">Nuevo campus</h1>
        <p class="page-subtitle">Registra una sede operativa de la academia</p>
    </div>
</div>
<div class="card">
    <form method="POST" action="{{ route('campuses.store') }}">
        @csrf
        @include('campuses.form')
        <div class="form-actions">
            <button class="btn" type="submit">Guardar campus</button>
            <a class="btn secondary" href="{{ route('campuses.index') }}">Volver</a>
        </div>
    </form>
</div>
@endsection
