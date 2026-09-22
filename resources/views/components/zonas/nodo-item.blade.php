@props(['nodo'])

@php
    $ultimaLectura = $nodo->lecturas_max_medido_en;
    $transmitiendo = $nodo->estaTransmitiendo($ultimaLectura);
@endphp

<a
    href="{{ route('zonas.nodo', $nodo) }}"
    wire:navigate
    {{ $attributes->merge(['class' => 'flex items-center justify-between gap-3 border-t border-border px-4 py-2.5 transition hover:bg-surface']) }}
>
    <div class="flex min-w-0 items-center gap-2.5">
        <span
            class="size-2 shrink-0 rounded-full {{ $transmitiendo ? 'bg-success' : 'bg-text-tertiary' }}"
            title="{{ $transmitiendo ? 'Transmitiendo' : ($nodo->activo ? 'Sin señal' : 'Inactivo') }}"
        ></span>

        <span class="truncate text-sm font-medium text-text-primary">{{ $nodo->nombre }}</span>
        <span class="shrink-0 font-mono text-xs text-text-tertiary">{{ $nodo->codigo }}</span>
    </div>

    <span class="flex shrink-0 items-center gap-1.5 font-mono text-[11px] text-text-tertiary">
        {{ $ultimaLectura?->diffForHumans() ?? 'Sin lecturas' }}
        <x-icon name="chevron-right" class="size-3.5 text-accent" />
    </span>
</a>