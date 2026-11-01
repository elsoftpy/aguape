<div>
    <x-layouts.page-header :establecimiento="$this->establecimiento" title="Establecimientos">
        <x-slot:actions>
            <x-button text="Nuevo establecimiento" icon="plus" wire:click="abrirCreacion" />
        </x-slot:actions>
    </x-layouts.page-header>

    <div class="space-y-6 px-6 py-6 md:px-8">
        <p class="text-sm text-text-secondary">
            Establecimientos registrados. Entrá a "Potreros" para gestionar la estructura de cada uno.
        </p>

        <div class="overflow-hidden rounded-[10px] border border-border bg-panel">
            @forelse ($this->establecimientos as $establecimiento)
                <div wire:key="establecimiento-{{ $establecimiento->id }}" class="flex flex-wrap items-center justify-between gap-4 border-b border-border px-4 py-3 last:border-b-0">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="flex size-9 items-center justify-center rounded-lg bg-surface text-accent">
                            <x-icon name="home-modern" sm />
                        </span>

                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-text-primary">{{ $establecimiento->nombre }}</p>
                            <p class="truncate text-xs text-text-tertiary">
                                {{ $establecimiento->ubicacion ?? 'Sin ubicación cargada' }}
                            </p>
                        </div>
                    </div>

                    <div class="flex shrink-0 items-center gap-4">
                        <span class="font-mono text-xs text-text-tertiary">
                            {{ $establecimiento->hectareas !== null ? \Illuminate\Support\Number::format((float) $establecimiento->hectareas, maxPrecision: 2).' ha' : 'Sin superficie' }}
                            · {{ $establecimiento->potreros_count }} {{ Str::plural('potrero', $establecimiento->potreros_count) }}
                            · {{ $establecimiento->nodos_count }} {{ Str::plural('nodo', $establecimiento->nodos_count) }}
                        </span>

                        <div class="flex items-center gap-2">
                            <a
                                href="{{ route('zonas', ['establecimiento' => $establecimiento->id]) }}"
                                wire:navigate
                                class="flex items-center gap-1.5 rounded-md border border-border px-2.5 py-1 text-xs font-medium text-text-secondary transition hover:bg-surface hover:text-text-primary"
                            >
                                <x-icon name="map" class="size-3.5" />
                                Potreros
                            </a>

                            <button
                                type="button"
                                wire:click="abrirEdicion({{ $establecimiento->id }})"
                                class="flex items-center gap-1.5 rounded-md border border-border px-2.5 py-1 text-xs font-medium text-text-secondary transition hover:bg-surface hover:text-text-primary"
                            >
                                <x-icon name="pencil" class="size-3.5" />
                                Editar
                            </button>
                        </div>
                    </div>
                </div>
            @empty
                <p class="px-4 py-6 text-center text-sm text-text-tertiary">
                    No hay establecimientos registrados todavía.
                </p>
            @endforelse
        </div>
    </div>

    <x-modal id="modal-establecimiento" wire="mostrarModal" :title="$editandoId ? 'Editar establecimiento' : 'Nuevo establecimiento'" center="md">
        <div class="space-y-4">
            <x-input wire:model="nombre" label="Nombre" placeholder="Estancia San José" />

            <x-input wire:model="ubicacion" label="Ubicación" placeholder="Concepción, Paraguay" hint="Opcional" />

            <x-input
                wire:model="hectareas"
                x-mask:dynamic="$money($input, ',', '.', 2)"
                inputmode="decimal"
                label="Superficie (hectáreas)"
                hint="Opcional"
            />
        </div>

        <x-slot:footer>
            <x-button text="Cancelar" color="red" wire:click="$set('mostrarModal', false)" />
            <x-button text="Guardar" wire:click="guardar" />
        </x-slot:footer>
    </x-modal>
</div>
