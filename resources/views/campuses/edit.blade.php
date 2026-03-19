@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">Editar campus</h1>
        <p class="page-subtitle">Actualiza datos de la sede</p>
    </div>
</div>
<div class="card">
    <form method="POST" action="{{ route('campuses.update', $campus) }}">
        @csrf
        @method('PUT')
        @include('campuses.form')
        <div class="form-actions">
            <button class="btn" type="submit">Actualizar campus</button>
            <a class="btn secondary" href="{{ route('campuses.index') }}">Volver</a>
        </div>
    </form>
</div>
@endsection
