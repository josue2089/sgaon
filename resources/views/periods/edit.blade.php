@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">Editar período ✏️</h1>
        <p class="page-subtitle">Actualiza código, descripción o estado del período</p>
    </div>
</div>
<div class="card">
    <form method="POST" action="{{ route('periods.update', $period) }}">
        @csrf
        @method('PUT')
        @include('periods.form')
        <div class="form-actions">
            <button class="btn" type="submit">Actualizar período</button>
            <a class="btn secondary" href="{{ route('periods.index') }}">Volver</a>
        </div>
    </form>
</div>
@endsection
