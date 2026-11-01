<div>
    <x-layouts.page-header :establecimiento="$this->establecimiento" title="Zonas / potreros">
        @can(\App\Enums\PermissionsEnum::GestionarPotreros->value)
            <x-slot:actions>
                <x-button text="Nuevo potrero" icon="plus" wire:click="abrirCreacion" />
            </x-slot:actions>
        @endcan
    </x-layouts.page-header>

    <div class="space-y-6 px-6 py-6 md:px-8">
        <p class="text-sm text-text-secondary">
            Potreros del establecimiento y los nodos asignados a cada uno. Entrá a un nodo para ver sus últimas lecturas y el historial de las últimas 6 horas.
        </p>

        @if (count($this->establecimientoOptions) > 1)
            <div class="max-w-sm">
                <x-select.native wire:model.live="establecimientoId" label="Establecimiento" :options="$this->establecimientoOptions" />
            </div>
        @endif

        @if ($this->establecimiento === null)
            <div class="rounded-[10px] border border-border bg-panel px-4 py-6 text-center text-sm text-text-tertiary">
                No hay establecimientos registrados todavía.
            </div>
        @elseif ($this->potreros->isEmpty() && $this->nodosSinZona->isEmpty())
            <div class="rounded-[10px] border border-border bg-panel px-4 py-6 text-center text-sm text-text-tertiary">
                No hay potreros registrados todavía.
            </div>
        @else
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($this->potreros as $potrero)
                    <div wire:key="potrero-{{ $potrero->id }}" class="flex flex-col overflow-hidden rounded-[10px] border border-border bg-panel">
                        <div class="flex items-start justify-between gap-3 px-4 py-4">
                            <div class="min-w-0">
                                <p class="truncate text-base font-bold text-text-primary">{{ $potrero->nombre }}</p>
                                <p class="font-mono text-xs text-text-tertiary">
                                    {{ $potrero->hectareas !== null ? \Illuminate\Support\Number::format((float) $potrero->hectareas, maxPrecision: 2).' ha' : 'Sin superficie cargada' }}
                                    · {{ $potrero->nodos->count() }} {{ Str::plural('nodo', $potrero->nodos->count()) }}
                                </p>
                            </div>

                            @can(\App\Enums\PermissionsEnum::GestionarPotreros->value)
                                <div class="flex shrink-0 items-center gap-1">
                                    <button
                                        type="button"
                                        wire:click="abrirEdicion({{ $potrero->id }})"
                                        title="Editar"
                                        class="rounded-md p-1.5 text-text-secondary transition hover:bg-surface hover:text-text-primary"
                                    >
                                        <x-icon name="pencil" class="size-4" />
                                    </button>

                                    <button
                                        type="button"
                                        wire:click="confirmarEliminacion({{ $potrero->id }})"
                                        title="Eliminar"
                                        class="rounded-md p-1.5 text-danger transition hover:bg-danger/10"
                                    >
                                        <x-icon name="trash" class="size-4" />
                                    </button>
                                </div>
                            @endcan
                        </div>

                        @forelse ($potrero->nodos as $nodo)
                            <x-zonas.nodo-item :nodo="$nodo" wire:key="nodo-{{ $nodo->id }}" />
                        @empty
                            <p class="border-t border-border px-4 py-3 text-sm text-text-tertiary">
                                Sin nodos asignados.
                            </p>
                        @endforelse
                    </div>
                @endforeach

                @if ($this->nodosSinZona->isNotEmpty())
                    <div wire:key="potrero-sin-zona" class="flex flex-col overflow-hidden rounded-[10px] border border-dashed border-border bg-panel">
                        <div class="px-4 py-4">
                            <p class="text-base font-bold text-text-secondary">Sin zona asignada</p>
                            <p class="font-mono text-xs text-text-tertiary">
                                {{ $this->nodosSinZona->count() }} {{ Str::plural('nodo', $this->nodosSinZona->count()) }}
                            </p>
                        </div>

                        @foreach ($this->nodosSinZona as $nodo)
                            <x-zonas.nodo-item :nodo="$nodo" wire:key="nodo-{{ $nodo->id }}" />
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
    </div>

    @can(\App\Enums\PermissionsEnum::GestionarPotreros->value)
        <x-modal id="modal-potrero" wire="mostrarModal" :title="$editandoId ? 'Editar potrero' : 'Nuevo potrero'" center="md">
            <div class="space-y-4">
                <x-input wire:model="nombre" label="Nombre" placeholder="Potrero 1" />

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
    @endcan
</div>