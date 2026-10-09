@extends('layouts.app')
@section('content')
<div class="module-head">
    <div>
        <h1 class="page-title">Usuarios admin</h1>
        <p class="page-subtitle">Cuentas administrativas por sede</p>
    </div>
    <a class="btn" href="{{ route('admin-users.create') }}">Nuevo usuario admin</a>
</div>

@if($users->count() === 0)
    <div class="card empty-state">No hay usuarios administrativos de sede registrados.</div>
@else
    <div class="card table-card">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th>Sedes</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @foreach($users as $user)
                    <tr>
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td>
                            @if($user->access_all_campuses)
                                Todas las sedes
                            @elseif($user->campuses->isNotEmpty())
                                {{ $user->campuses->pluck('name')->join(', ') }}
                            @elseif($user->campus)
                                {{ $user->campus->name }}
                            @else
                                Sin sede
                            @endif
                        </td>
                        <td>@include('partials.ui.status', ['status' => $user->status])</td>
                        <td class="table-actions">
                            <a href="{{ route('admin-users.edit', $user) }}">Editar</a>
                            <form class="inline" method="POST" action="{{ route('admin-users.resend-credentials', $user) }}">
                                @csrf
                                <button type="submit" class="btn-link">Reenviar credenciales</button>
                            </form>
                            @if((int) auth()->id() !== (int) $user->id)
                                <form class="inline" method="POST" action="{{ route('admin-users.destroy', $user) }}" data-confirm="¿Eliminar este usuario administrativo?" data-confirm-danger>
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-link-danger">Eliminar</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @if($users->hasPages())
        <div class="card">{{ $users->links() }}</div>
    @endif
@endif
@endsection
