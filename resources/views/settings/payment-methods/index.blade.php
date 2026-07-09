@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">Métodos y cuentas de pago</h1>
        <p class="page-subtitle">Configuración global por moneda (USD / EUR / Bs) visible en portal y financiero.</p>
    </div>
    <a class="btn" href="{{ route('settings.payment-methods.create') }}">Nuevo método</a>
</div>

@if($methods->isEmpty())
    <div class="card empty-state">No hay métodos de pago configurados.</div>
@else
    <div class="card table-card">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Moneda</th>
                    <th>Tipo</th>
                    <th>Etiqueta</th>
                    <th>Cuenta</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @foreach($methods as $method)
                    <tr>
                        <td>{{ $method->currency }}</td>
                        <td>{{ $method->typeLabel() }}</td>
                        <td>{{ $method->label }}</td>
                        <td>{{ $method->bank_name ?: '—' }} {{ $method->account_number ? '· '.$method->account_number : '' }}</td>
                        <td>@include('partials.ui.status-badge', ['tone' => $method->is_active ? 'ok' : 'warn', 'text' => $method->is_active ? 'Activo' : 'Inactivo'])</td>
                        <td class="table-actions">
                            <a href="{{ route('settings.payment-methods.edit', $method) }}">Editar</a>
                            <form method="POST" action="{{ route('settings.payment-methods.destroy', $method) }}" style="display:inline;" onsubmit="return confirm('¿Eliminar este método de pago?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-link-danger">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
@endsection
