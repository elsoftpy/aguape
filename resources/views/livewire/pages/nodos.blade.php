<div>
    <x-layouts.page-header :establecimiento="$this->establecimiento" title="Nodos LoRa">
        <x-slot:actions>
            <x-button text="Nuevo nodo" icon="plus" wire:click="abrirCreacion" />
        </x-slot:actions>
    </x-layouts.page-header>

    <div class="space-y-6 px-6 py-6 md:px-8">
        <p class="text-sm text-text-secondary">
            Administrá los nodos LoRa registrados, su zona asignada y el intervalo de transmisión de cada uno (entre 15 y 120 minutos).
        </p>

        <div class="overflow-hidden rounded-[10px] border border-border bg-panel">
            @forelse ($this->nodos as $nodo)
                <div wire:key="nodo-{{ $nodo->id }}" class="flex flex-wrap items-center justify-between gap-4 border-b border-border px-4 py-3 last:border-b-0">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="flex size-9 items-center justify-center rounded-lg bg-surface text-accent">
                            <x-icon name="cpu-chip" sm />
                        </span>

                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-text-primary">
                                {{ $nodo->nombre }}
                                <span class="font-mono text-xs text-text-tertiary">{{ $nodo->codigo }}</span>
                            </p>
                            <p class="truncate text-xs text-text-tertiary">
                                {{ $nodo->potrero?->nombre ?? 'Sin zona asignada' }}
                            </p>
                        </div>
                    </div>

                    <div class="flex shrink-0 items-center gap-4">
                        <span class="font-mono text-xs text-text-tertiary">
                            cada {{ $nodo->intervalo_transmision_minutos }} min
                        </span>

                        <span class="rounded-full px-2.5 py-1 text-xs font-medium
                            {{ $nodo->activo ? 'bg-success-dim text-success' : 'bg-danger-dim text-danger' }}">
                            {{ $nodo->activo ? 'Activo' : 'Inactivo' }}
                        </span>

                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                wire:click="abrirEdicion({{ $nodo->id }})"
                                class="flex items-center gap-1.5 rounded-md border border-border px-2.5 py-1 text-xs font-medium text-text-secondary transition hover:bg-surface hover:text-text-primary"
                            >
                                <x-icon name="pencil" class="size-3.5" />
                                Editar
                            </button>

                            <button
                                type="button"
                                wire:click="confirmarEliminacion({{ $nodo->id }})"
                                class="flex items-center gap-1.5 rounded-md border border-border px-2.5 py-1 text-xs font-medium text-danger transition hover:bg-danger/10"
                            >
                                <x-icon name="trash" class="size-3.5" />
                                Eliminar
                            </button>
                        </div>
                    </div>
                </div>
            @empty
                <p class="px-4 py-6 text-center text-sm text-text-tertiary">
                    No hay nodos registrados todavía.
                </p>
            @endforelse
        </div>

        @if ($this->nodos->hasPages())
            <div class="flex items-center justify-between gap-3 text-sm">
                <button
                    type="button"
                    wire:click="previousPage"
                    @disabled($this->nodos->onFirstPage())
                    class="rounded-md border border-border px-3 py-1.5 text-text-secondary transition hover:bg-surface hover:text-text-primary disabled:cursor-not-allowed disabled:opacity-40"
                >
                    Anterior
                </button>

                <span class="font-mono text-xs text-text-tertiary">
                    Página {{ $this->nodos->currentPage() }} de {{ $this->nodos->lastPage() }}
                </span>

                <button
                    type="button"
                    wire:click="nextPage"
                    @disabled(! $this->nodos->hasMorePages())
                    class="rounded-md border border-border px-3 py-1.5 text-text-secondary transition hover:bg-surface hover:text-text-primary disabled:cursor-not-allowed disabled:opacity-40"
                >
                    Siguiente
                </button>
            </div>
        @endif
    </div>

    <x-modal id="modal-nodo" wire="mostrarModal" :title="$editandoId ? 'Editar nodo' : 'Nuevo nodo'" center="md">
        <div class="space-y-4">
            <x-input wire:model="codigo" label="Código" placeholder="N-1" />

            <x-input wire:model="nombre" label="Nombre" placeholder="Corral central" />

            <x-date wire:model="instaladoEn" label="Fecha de instalación" format="DD/MM/YYYY" :max-date="today()" />

            <x-select.native
                wire:model="potreroId"
                label="Zona / potrero"
                :options="$this->potreroOptions"
            />

            <x-number
                wire:model="intervaloTransmisionMinutos"
                label="Intervalo de transmisión (minutos)"
                hint="Entre 15 y 120 minutos"
                :min="15"
                :max="120"
                :step="15"
            />

            <x-toggle wire:model="activo" label="Nodo activo" color="cyan" position="left" />
        </div>

        <x-slot:footer>
            <x-button text="Cancelar" color="red" wire:click="$set('mostrarModal', false)" />
            <x-button text="Guardar" wire:click="guardar" />
        </x-slot:footer>
    </x-modal>
</div>