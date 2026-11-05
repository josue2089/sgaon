@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">Cambiar contraseña</h1>
        <p class="page-subtitle">Usa al menos 8 caracteres.</p>
    </div>
</div>

<form method="POST" action="{{ route('account.password.update') }}" class="card stack-sm" data-guard-submit>
    @csrf
    @method('PUT')
    <div>
        <label for="current_password">Contraseña actual</label>
        <input id="current_password" type="password" name="current_password" autocomplete="current-password" required>
    </div>
    <div>
        <label for="password">Contraseña nueva</label>
        <input id="password" type="password" name="password" autocomplete="new-password" minlength="8" required>
    </div>
    <div>
        <label for="password_confirmation">Confirmar contraseña nueva</label>
        <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" minlength="8" required>
    </div>
    <div class="form-actions">
        <button class="btn" type="submit">Guardar contraseña</button>
    </div>
</form>
@endsection
