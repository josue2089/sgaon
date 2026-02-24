@php($iconName = $name ?? 'circle')
<span class="{{ $class ?? 'ui-icon' }}" aria-hidden="true">
    @switch($iconName)
        @case('users')
            <svg viewBox="0 0 24 24" fill="none"><path d="M16 19a4 4 0 0 0-8 0M15 8a3 3 0 1 1-6 0 3 3 0 0 1 6 0ZM20 18a3 3 0 0 0-3-3M4 18a3 3 0 0 1 3-3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
            @break
        @case('teacher')
            <svg viewBox="0 0 24 24" fill="none"><path d="M3 8l9-4 9 4-9 4-9-4Zm3 3.5V16c0 2.2 2.7 4 6 4s6-1.8 6-4v-4.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
            @break
        @case('book')
            <svg viewBox="0 0 24 24" fill="none"><path d="M4 6.5A2.5 2.5 0 0 1 6.5 4H20v14H6.5A2.5 2.5 0 0 0 4 20V6.5ZM4 20V8a2 2 0 0 1 2-2h14" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
            @break
        @case('check')
            <svg viewBox="0 0 24 24" fill="none"><path d="m5 12 4 4 10-10" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg>
            @break
        @case('warning')
            <svg viewBox="0 0 24 24" fill="none"><path d="M12 9v4M12 17h.01M10.3 3.8 2.7 18a2 2 0 0 0 1.76 3h15.08A2 2 0 0 0 21.3 18L13.7 3.8a2 2 0 0 0-3.4 0Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
            @break
        @case('trend')
            <svg viewBox="0 0 24 24" fill="none"><path d="m4 15 6-6 4 4 6-6M14 7h6v6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
            @break
        @case('payment')
            <svg viewBox="0 0 24 24" fill="none"><path d="M3 7.5A2.5 2.5 0 0 1 5.5 5h13A2.5 2.5 0 0 1 21 7.5v9a2.5 2.5 0 0 1-2.5 2.5h-13A2.5 2.5 0 0 1 3 16.5v-9ZM3 10h18" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg>
            @break
        @case('alert')
            <svg viewBox="0 0 24 24" fill="none"><path d="M15 17H5.8a.8.8 0 0 1-.65-1.27c1.06-1.45 1.85-3 1.85-5.18a5 5 0 0 1 10 0c0 2.17.8 3.73 1.85 5.18A.8.8 0 0 1 18.2 17H15Zm0 0a3 3 0 0 1-6 0" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
            @break
        @case('award')
            <svg viewBox="0 0 24 24" fill="none"><path d="M12 14a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM8.5 13.5 7 20l5-2.5L17 20l-1.5-6.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
            @break
        @case('calendar')
            <svg viewBox="0 0 24 24" fill="none"><path d="M8 4v3M16 4v3M4 10h16M7 14l2 2 5-5M6 6h12a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
            @break
        @default
            <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="8" stroke="currentColor" stroke-width="1.7"/></svg>
    @endswitch
</span>
