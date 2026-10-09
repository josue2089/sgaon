@include('partials.ui.status-badge', ['tone' => \App\Support\StatusLabel::tone($status ?? null), 'text' => \App\Support\StatusLabel::label($status ?? null, $domain ?? null)])
