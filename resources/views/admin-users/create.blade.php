@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">Nuevo usuario admin</h1>
        <p class="page-subtitle">Crear cuenta administrativa con acceso por sede</p>
    </div>
    <a class="btn secondary" href="{{ route('admin-users.index') }}">Volver</a>
</div>

<div class="card">
    <form class="stack-sm" method="POST" action="{{ route('admin-users.store') }}">
        @csrf
        @include('admin-users.form', ['user' => $user, 'campuses' => $campuses, 'accessMode' => $accessMode, 'selectedCampusIds' => $selectedCampusIds])
        <div class="form-actions">
            <button class="btn" type="submit">Crear y enviar credenciales</button>
        </div>
    </form>
</div>
@endsection
