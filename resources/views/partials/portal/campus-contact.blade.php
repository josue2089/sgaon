{{-- Contacto de la sede para las familias. Variable opcional: $campus. #448 --}}
@php($phone = $campus?->contact_phone)
@php($whatsapp = \App\Support\WhatsappLink::normalizePhone($phone))
@if($campus && ($phone || $campus->contact_email))
    <div class="stack-xs">
        <div><strong>Sede {{ $campus->name }}</strong></div>
        @if($phone)
            <div>
                Teléfono: <a href="tel:{{ preg_replace('/[^\d+]/', '', $phone) }}">{{ $phone }}</a>
                @if($whatsapp)
                    · <a href="https://wa.me/{{ $whatsapp }}" target="_blank" rel="noopener">Escribir por WhatsApp</a>
                @endif
            </div>
        @endif
        @if($campus->contact_email)
            <div>Correo: <a href="mailto:{{ $campus->contact_email }}">{{ $campus->contact_email }}</a></div>
        @endif
    </div>
@else
    <p>Escríbele a la coordinación de tu sede{{ $campus ? ' ('.$campus->name.')' : '' }} y cuéntale qué pasó.</p>
@endif
