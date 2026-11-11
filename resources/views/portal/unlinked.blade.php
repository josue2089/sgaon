@extends('layouts.app')
@section('content')
{{-- La cuenta existe pero no está vinculada a una ficha de alumno o representante. #448 --}}
<div class="card stack-sm">
    <h1 class="section-title section-title-md">Tu cuenta todavía no está vinculada</h1>
    <p>
        Entraste correctamente, pero tu usuario aún no está asociado a {{ $kind === 'student' ? 'una ficha de alumno' : 'la ficha de un representante' }}.
        Pídele a la sede que lo vincule, indicando el correo o la cédula con la que entras.
    </p>
    @include('partials.portal.campus-contact')
</div>
@endsection
