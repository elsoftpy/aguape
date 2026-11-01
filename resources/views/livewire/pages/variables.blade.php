<div>
    <x-layouts.page-header :establecimiento="$this->establecimiento" title="Variables monitoreadas">
        <x-slot:actions>
            <x-button text="Nueva variable" icon="plus" wire:click="abrirCreacion" />
        </x-slot:actions>
    </x-layouts.page-header>

    <div class="space-y-6 px-6 py-6 md:px-8">
        <p class="text-sm text-text-secondary">
            Variables que el sistema procesa. La clave es el nombre con el que los nodos envían cada lectura y no se puede cambiar después de creada.
        </p>

        <div class="overflow-hidden rounded-[10px] border border-border bg-panel">
            @forelse ($this->variables as $variable)
                <div wire:key="variable-{{ $variable->id }}" class="flex flex-wrap items-center justify-between gap-4 border-b border-border px-4 py-3 last:border-b-0">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="flex size-9 items-center justify-center rounded-lg bg-surface text-accent">
                            <x-icon :name="$variable->icono()" sm />
                        </span>

                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-text-primary">{{ $variable->nombre }}</p>
                            <p class="truncate font-mono text-xs text-text-tertiary">{{ $variable->clave }}</p>
                        </div>
                    </div>

                    <div class="flex shrink-0 items-center gap-4">
                        <span class="font-mono text-xs text-text-tertiary">{{ $variable->unidad ?? 'Sin unidad' }}</span>

                        <button
                            type="button"
                            wire:click="abrirEdicion({{ $variable->id }})"
                            class="flex items-center gap-1.5 rounded-md border border-border px-2.5 py-1 text-xs font-medium text-text-secondary transition hover:bg-surface hover:text-text-primary"
                        >
                            <x-icon name="pencil" class="size-3.5" />
                            Editar
                        </button>
                    </div>
                </div>
            @empty
                <p class="px-4 py-6 text-center text-sm text-text-tertiary">
                    No hay variables registradas todavía.
                </p>
            @endforelse
        </div>
    </div>

    <x-modal id="modal-variable" wire="mostrarModal" :title="$editandoId ? 'Editar variable' : 'Nueva variable'" center="md">
        <div class="space-y-4">
            @if ($editandoId)
                <x-input :value="$clave" label="Clave" hint="La clave no se puede cambiar." disabled />
            @else
                <x-input wire:model="clave" label="Clave" placeholder="temperatura_aire" hint="Debe coincidir con el nombre que envía el nodo." />
            @endif

            <x-input wire:model="nombre" label="Nombre" placeholder="Temperatura del aire" />

            <x-input wire:model="unidad" label="Unidad de medida" placeholder="°C" hint="Opcional" />
        </div>

        <x-slot:footer>
            <x-button text="Cancelar" color="red" wire:click="$set('mostrarModal', false)" />
            <x-button text="Guardar" wire:click="guardar" />
        </x-slot:footer>
    </x-modal>
</div>
