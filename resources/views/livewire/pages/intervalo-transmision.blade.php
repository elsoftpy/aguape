<div>
    <x-layouts.page-header :establecimiento="$this->establecimiento" title="Intervalo de transmisión" />

    <div class="space-y-6 px-6 py-6 md:px-8">
        <p class="text-sm text-text-secondary">
            Cada cuántos minutos miden y transmiten los nodos. Es el mismo para todos, entre {{ \App\Models\ConfiguracionGeneral::INTERVALO_MINIMO }} y {{ \App\Models\ConfiguracionGeneral::INTERVALO_MAXIMO }} minutos. Un nodo aplica el cambio después de su próxima transmisión.
        </p>

        <div class="max-w-md space-y-4 rounded-[10px] border border-border bg-panel p-4">
            <p class="text-sm text-text-primary">
                Intervalo vigente:
                <span class="font-mono font-semibold">{{ $this->configuracion->intervalo_transmision_minutos }} min</span>
                <span class="font-mono text-xs text-text-tertiary">· versión {{ $this->configuracion->cfgVersionNodos() }}</span>
            </p>

            <x-input
                wire:model="intervaloTransmisionMinutos"
                inputmode="numeric"
                label="Nuevo intervalo (minutos)"
                :hint="'Entre '.\App\Models\ConfiguracionGeneral::INTERVALO_MINIMO.' y '.\App\Models\ConfiguracionGeneral::INTERVALO_MAXIMO.' minutos'"
            />

            <div class="flex justify-end gap-2">
                <x-button text="Cancelar" color="red" wire:click="cancelar" />
                <x-button text="Guardar" wire:click="guardar" />
            </div>
        </div>
    </div>
</div>
