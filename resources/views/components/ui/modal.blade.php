@props(['id', 'title', 'subtitle' => null, 'size' => 'sm'])
{{-- Modal sobre <dialog>: se abre con un botón [data-modal-open="id"] y se cierra con [data-modal-close], Esc o clic fuera. --}}
<dialog id="{{ $id }}" {{ $attributes->class(['ui-modal', 'ui-modal--'.$size]) }} aria-labelledby="{{ $id }}-title">
    <div class="ui-modal-head">
        <div>
            <h3 class="section-title" id="{{ $id }}-title">{{ $title }}</h3>
            @if($subtitle)
                <p class="entity-sub">{{ $subtitle }}</p>
            @endif
        </div>
        <button class="ui-modal-x" type="button" data-modal-close aria-label="Cerrar">&times;</button>
    </div>
    <div class="ui-modal-body">
        {{ $slot }}
    </div>
    @isset($footer)
        <div class="form-actions ui-modal-foot">{{ $footer }}</div>
    @endisset
</dialog>
