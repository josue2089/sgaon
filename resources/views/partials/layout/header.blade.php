@php
    $navItems = \App\Support\Navigation::for($user, request()->route()?->getName());
    $canSearchStudents = $user?->role === 'admin' && \Illuminate\Support\Facades\Route::has('students.search');
@endphp
<header class="fi-header">
    <div class="fi-header-inner">
        <button class="fi-burger" type="button" data-modal-open="fi-drawer" aria-label="Abrir menú" aria-controls="fi-drawer">
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/></svg>
        </button>

        <a class="fi-brand" href="{{ route('dashboard') }}" aria-label="Ir al inicio">
            <span class="fi-brand-mark">
                <img src="{{ asset('images/logo.png') }}" alt="ON English">
            </span>
        </a>

        <nav class="fi-nav" aria-label="Menú principal">
            @foreach($navItems as $item)
                @if($item['url'])
                    <a class="fi-nav-item {{ $item['active'] ? 'active' : '' }}" href="{{ $item['url'] }}" @if($item['active']) aria-current="page" @endif>
                        @include('partials.layout.nav-icon', ['icon' => $item['icon']])
                        {{ $item['label'] }}
                    </a>
                @else
                    <details class="fi-menu fi-menu-inline">
                        <summary class="fi-nav-item {{ $item['active'] ? 'active' : '' }}">
                            @include('partials.layout.nav-icon', ['icon' => $item['icon']])
                            {{ $item['label'] }}
                            <svg class="fi-caret" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m7 10 5 5 5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </summary>
                        <div class="fi-menu-panel fi-menu-panel-nav">
                            @foreach($item['children'] as $child)
                                <a href="{{ $child['url'] }}" class="fi-menu-link {{ $child['active'] ? 'is-active' : '' }}">{{ $child['label'] }}</a>
                            @endforeach
                        </div>
                    </details>
                @endif
            @endforeach
        </nav>

        <div class="fi-header-actions">
            @if($canSearchStudents)
                @include('partials.layout.student-search', ['searchId' => 'header'])
            @endif

            <details class="fi-menu">
                <summary class="fi-icon-badge" aria-label="Notificaciones{{ $openAlertsCount > 0 ? ' ('.$openAlertsCount.' abiertas)' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M15 17H5.8a.8.8 0 0 1-.65-1.27c1.06-1.45 1.85-3 1.85-5.18a5 5 0 0 1 10 0c0 2.17.8 3.73 1.85 5.18A.8.8 0 0 1 18.2 17H15Zm0 0a3 3 0 0 1-6 0" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                    @if($openAlertsCount > 0)
                        <span class="fi-icon-dot"></span>
                    @endif
                </summary>
                <div class="fi-menu-panel">
                    <div class="fi-menu-title">Notificaciones</div>
                    @forelse($openAlerts as $alert)
                        @php
                            $alertUrl = route('dashboard');
                            if ($alert->type === 'finance' && $user?->role === 'admin' && $alert->student_id) {
                                $alertUrl = route('finance.index', ['student_id' => $alert->student_id]);
                            } elseif ($alert->type === 'attendance') {
                                $alertUrl = ($user?->role === 'admin' && $alert->student_id)
                                    ? route('students.show', $alert->student_id)
                                    : route('attendance.index');
                            } elseif ($alert->type === 'level_renewal' && $user?->role === 'admin' && $alert->student_id) {
                                $alertUrl = route('students.show', $alert->student_id);
                            } elseif ($alert->type === 'makeup_recovery') {
                                $alertUrl = $user?->role === 'admin'
                                    ? ($hasRoute('makeups.index') ? route('makeups.index', ['student_id' => $alert->student_id]) : route('dashboard'))
                                    : route('portal.student');
                            }
                        @endphp
                        <a href="{{ $alertUrl }}" class="fi-menu-link">
                            <strong>{{ $alert->typeLabel() }}</strong>
                            <small>{{ \Illuminate\Support\Str::limit($alert->message, 52) }}</small>
                        </a>
                    @empty
                        <div class="fi-menu-empty">Sin alertas abiertas</div>
                    @endforelse
                </div>
            </details>

            <details class="fi-menu">
                <summary class="fi-user-pill" aria-label="Mi cuenta">
                    <span class="fi-avatar">{{ strtoupper(substr((string) ($user?->name ?? 'A'), 0, 1)) }}</span>
                    <span class="fi-user-name">{{ $user?->name ?? 'Usuario' }}</span>
                </summary>
                <div class="fi-menu-panel fi-menu-panel-profile">
                    <div class="fi-menu-title">{{ $user?->name ?? 'Usuario' }}</div>
                    <div class="fi-menu-empty">Rol: {{ \App\Support\StatusLabel::role($user?->role) }}{{ $user?->isMasterAdmin() ? ' · Master' : '' }}</div>
                    @if($user?->role === 'student')
                        <a href="{{ route('portal.student') }}" class="fi-menu-link">Mi portal</a>
                    @endif
                    @if($user?->role === 'representative')
                        <a href="{{ route('portal.representative') }}" class="fi-menu-link">Portal familia</a>
                    @endif
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="fi-menu-logout" type="submit">Cerrar sesión</button>
                    </form>
                </div>
            </details>
        </div>
    </div>
</header>

{{-- Menú móvil / pantallas angostas: mismo contenido que el menú principal. --}}
<dialog id="fi-drawer" class="fi-drawer" aria-label="Menú">
    <div class="fi-drawer-head">
        <span class="fi-brand-mark"><img src="{{ asset('images/logo.png') }}" alt="ON English"></span>
        <button class="ui-modal-x" type="button" data-modal-close aria-label="Cerrar menú">&times;</button>
    </div>
    @if($canSearchStudents)
        @include('partials.layout.student-search', ['searchId' => 'drawer'])
    @endif
    <nav class="fi-drawer-nav" aria-label="Menú principal">
        @foreach($navItems as $item)
            @if($item['url'])
                <a class="fi-drawer-link {{ $item['active'] ? 'is-active' : '' }}" href="{{ $item['url'] }}">
                    @include('partials.layout.nav-icon', ['icon' => $item['icon']])
                    {{ $item['label'] }}
                </a>
            @else
                <div class="fi-drawer-group">
                    <div class="fi-drawer-title">
                        @include('partials.layout.nav-icon', ['icon' => $item['icon']])
                        {{ $item['label'] }}
                    </div>
                    @foreach($item['children'] as $child)
                        <a class="fi-drawer-link fi-drawer-sublink {{ $child['active'] ? 'is-active' : '' }}" href="{{ $child['url'] }}">{{ $child['label'] }}</a>
                    @endforeach
                </div>
            @endif
        @endforeach
    </nav>
</dialog>
