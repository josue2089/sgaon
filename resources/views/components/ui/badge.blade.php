@props(['status' => null, 'domain' => null, 'tone' => null, 'text' => null, 'title' => null])
@include('partials.ui.status-badge', [
    'tone' => $tone ?? \App\Support\StatusLabel::tone($status),
    'text' => $text ?? \App\Support\StatusLabel::label($status, $domain),
    'title' => $title,
])
