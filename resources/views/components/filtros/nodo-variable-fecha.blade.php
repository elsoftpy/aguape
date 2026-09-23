@props(['nodoOptions', 'variableOptions', 'rangoInvalido' => false, 'hayFiltros' => false])

<div class="rounded-[10px] border border-border bg-panel p-4">
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <x-select.native wire:model.live="nodoId" label="Nodo" :options="$nodoOptions" />
        <x-select.native wire:model.live="variableId" label="Variable" :options="$variableOptions" />
        <x-date wire:model.live="desde" label="Desde" format="DD/MM/YYYY" />
        <x-date wire:model.live="hasta" label="Hasta" format="DD/MM/YYYY" />
    </div>

    @if ($rangoInvalido || $hayFiltros)
        <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
            <p class="text-sm text-danger">
                @if ($rangoInvalido)
                    La fecha "Hasta" es anterior a "Desde". Corregí el rango para buscar.
                @endif
            </p>

            @if ($hayFiltros)
                <button
                    type="button"
                    wire:click="limpiarFiltros"
                    class="flex items-center gap-1.5 rounded-md border border-border px-2.5 py-1 text-xs font-medium text-text-secondary transition hover:bg-surface hover:text-text-primary"
                >
                    <x-icon name="x-mark" class="size-3.5" />
                    Limpiar filtros
                </button>
            @endif
        </div>
    @endif
</div>