@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">Editar usuario admin</h1>
        <p class="page-subtitle">{{ $user->email }}</p>
    </div>
    <a class="btn secondary" href="{{ route('admin-users.index') }}">Volver</a>
</div>

<div class="card">
    <form class="stack-sm" method="POST" action="{{ route('admin-users.update', $user) }}">
        @csrf
        @method('PUT')
        @include('admin-users.form', ['user' => $user, 'campuses' => $campuses, 'accessMode' => $accessMode, 'selectedCampusIds' => $selectedCampusIds])
        <div class="form-actions">
            <button class="btn" type="submit">Guardar cambios</button>
        </div>
    </form>
</div>
@endsection
