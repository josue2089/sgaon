@extends('layouts.app')
@section('content')
{{-- Mis recuperativas del profesor. #446 --}}
<div class="module-head">
    <div>
        <h1 class="page-title">Mis recuperativas</h1>
        <p class="page-subtitle">Tus clases recuperativas de los últimos 30 días y las próximas. Marca si cada alumno asistió.</p>
    </div>
</div>

@unless($teacher)
    <div class="card"><div class="empty-state-inline">Tu usuario no está vinculado a una ficha de profesor. Pide a la administración que lo revise.</div></div>
@else
    @foreach(['Próximas' => $upcoming, 'Pasadas' => $past] as $title => $sessions)
        <div class="card table-card">
            <h2 class="section-title section-title-sm">{{ $title }}</h2>
            @forelse($sessions as $session)
                @php($isPast = $session->session_date->lte($today))
                <div class="makeup-teacher-session">
                    <div class="table-title">{{ $session->session_date->locale('es')->isoFormat('dddd D [de] MMMM') }} · {{ substr((string) $session->starts_at, 0, 5) }}{{ $session->ends_at ? ' - '.substr((string) $session->ends_at, 0, 5) : '' }}</div>
                    <div class="table-sub">{{ $session->program?->name ?? 'Programa N/D' }}{{ $session->programLevel ? ' · '.$session->programLevel->name : '' }} · {{ $session->bookings->count() }}/{{ $session->capacity }} reservados</div>
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead><tr><th>Alumno</th><th>Clase que recupera</th><th>Estado</th><th>Asistencia</th></tr></thead>
                            <tbody>
                            @forelse($session->bookings as $booking)
                                @php($missed = $booking->makeupRequest?->missedSession)
                                <tr>
                                    <td>{{ $booking->makeupRequest?->student?->full_name ?? 'N/D' }}</td>
                                    <td>
                                        <div>{{ $missed?->group?->course?->name ?? 'N/D' }}</div>
                                        <div class="table-sub">{{ $missed?->session_date?->format('d/m/Y') }}</div>
                                    </td>
                                    <td>@include('partials.ui.status', ['status' => $booking->status, 'domain' => 'booking'])</td>
                                    <td>
                                        @if($isPast)
                                            <form method="POST" action="{{ route('teacher.makeups.bookings.update', $booking) }}" class="form-actions">
                                                @csrf
                                                @method('PATCH')
                                                <button class="btn {{ $booking->status === 'attended' ? '' : 'secondary' }}" type="submit" name="status" value="attended">Asistió</button>
                                                <button class="btn {{ $booking->status === 'missed' ? 'btn-danger' : 'secondary' }}" type="submit" name="status" value="missed">No asistió</button>
                                            </form>
                                        @else
                                            <span class="table-sub">Se marca el día de la clase</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4"><div class="empty-state-inline">Todavía no hay alumnos reservados.</div></td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @empty
                <div class="empty-state-inline">{{ $title === 'Próximas' ? 'No tienes recuperativas programadas.' : 'No tuviste recuperativas en los últimos 30 días.' }}</div>
            @endforelse
        </div>
    @endforeach
@endunless
@endsection
