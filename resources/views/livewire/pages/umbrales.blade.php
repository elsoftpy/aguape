<div>
    <x-layouts.page-header :establecimiento="$this->establecimiento" title="Configuración de umbrales" />

    <div class="space-y-6 px-6 py-6 md:px-8">
        <p class="text-sm text-text-secondary">
            Definí los rangos normales para cada variable. Cuando una lectura salga de rango, se genera una alerta automáticamente.
        </p>

        <div class="space-y-3">
            @foreach ($this->variables as $variable)
                @php $fila = $filas[$variable->id]; @endphp

                <div wire:key="variable-{{ $variable->id }}" class="flex flex-wrap items-center gap-4 rounded-[10px] border border-border bg-panel p-4">
                    <div class="flex min-w-[180px] flex-1 items-center gap-3">
                        <span class="flex size-9 items-center justify-center rounded-lg bg-surface text-accent">
                            <x-icon :name="$variable->icono()" sm />
                        </span>
                        <span class="text-sm font-semibold text-text-primary">{{ $variable->nombre }}</span>
                    </div>

                    <x-input
                        wire:model="filas.{{ $variable->id }}.minimo"
                        x-mask:dynamic="$money($input, ',', '.', 2)"
                        inputmode="decimal"
                        label="Mínimo"
                        placeholder="—"
                        class="w-28"
                    />

                    <x-input
                        wire:model="filas.{{ $variable->id }}.maximo"
                        x-mask:dynamic="$money($input, ',', '.', 2)"
                        inputmode="decimal"
                        label="Máximo"
                        placeholder="—"
                        class="w-28"
                    />

                    <span class="w-12 font-mono text-xs text-text-tertiary">{{ $variable->unidad }}</span>

                    <x-toggle wire:model="filas.{{ $variable->id }}.activo" label="Alertas activas" color="cyan" position="left" />

                    <button
                        type="button"
                        wire:click="guardar({{ $variable->id }})"
                        class="flex items-center gap-1.5 rounded-md border border-border px-2.5 py-1.5 text-xs font-medium text-text-secondary transition hover:bg-surface hover:text-text-primary"
                    >
                        <x-icon name="check" class="size-3.5" />
                        Guardar
                    </button>
                </div>
            @endforeach
        </div>
    </div>
</div>