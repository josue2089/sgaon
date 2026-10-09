<span class="fi-nav-icon" aria-hidden="true">
    @switch($icon)
        @case('home')
            <svg viewBox="0 0 24 24" fill="none"><path d="M4 4h7v7H4zM13 4h7v5h-7zM13 11h7v9h-7zM4 13h7v7H4z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg>
            @break
        @case('students')
            <svg viewBox="0 0 24 24" fill="none"><path d="M16 19a4 4 0 0 0-8 0M15 8a3 3 0 1 1-6 0 3 3 0 0 1 6 0ZM20 18a3 3 0 0 0-3-3M4 18a3 3 0 0 1 3-3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
            @break
        @case('courses')
            <svg viewBox="0 0 24 24" fill="none"><path d="M4 6.5A2.5 2.5 0 0 1 6.5 4H20v14H6.5A2.5 2.5 0 0 0 4 20V6.5ZM4 20V8a2 2 0 0 1 2-2h14" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
            @break
        @case('attendance')
            <svg viewBox="0 0 24 24" fill="none"><path d="M8 4v3M16 4v3M4 10h16M7 14l2 2 5-5M6 6h12a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
            @break
        @case('finance')
            <svg viewBox="0 0 24 24" fill="none"><path d="M3 7.5A2.5 2.5 0 0 1 5.5 5h13A2.5 2.5 0 0 1 21 7.5v9a2.5 2.5 0 0 1-2.5 2.5h-13A2.5 2.5 0 0 1 3 16.5v-9ZM3 10h18" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg>
            @break
        @case('makeups')
            <svg viewBox="0 0 24 24" fill="none"><path d="M4 12a8 8 0 1 0 2.3-5.6M4 4v4h4M12 8v4l3 2" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
            @break
        @case('reports')
            <svg viewBox="0 0 24 24" fill="none"><path d="M5 19V9M12 19V5M19 19v-7M4 19h16" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
            @break
        @case('config')
            <svg viewBox="0 0 24 24" fill="none"><path d="M12 3l2 2.2 2.9-.2.7 2.8 2.5 1.4-1.4 2.5 1.4 2.5-2.5 1.4-.7 2.8-2.9-.2L12 21l-2.2-2.2-2.9.2-.7-2.8-2.5-1.4 1.4-2.5-1.4-2.5 2.5-1.4.7-2.8 2.9.2L12 3Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.5"/></svg>
            @break
    @endswitch
</span>
