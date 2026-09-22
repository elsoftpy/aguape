<div wire:poll.30s>
    <x-layouts.page-header :establecimiento="$this->establecimiento" title="Detalle de nodo" />

    <div class="space-y-6 px-6 py-6 md:px-8">
        <a href="{{ route('zonas') }}" wire:navigate class="inline-flex items-center gap-1.5 text-sm font-medium text-accent hover:underline">
            <x-icon name="arrow-left" class="size-4" />
            Volver a Zonas / potreros
        </a>

        @php $transmitiendo = $nodo->estaTransmitiendo($this->ultimaLectura?->medido_en); @endphp

        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-2xl font-bold text-text-primary">
                    {{ $nodo->nombre }}
                    <span class="font-mono text-sm font-medium text-text-tertiary">{{ $nodo->codigo }}</span>
                </h2>
                <p class="text-sm text-text-secondary">
                    {{ $nodo->potrero?->nombre ?? 'Sin zona asignada' }}
                    @if ($this->establecimiento)
                        · {{ $this->establecimiento->nombre }}
                    @endif
                </p>
            </div>

            <span class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-bold tracking-wide uppercase
                {{ $transmitiendo ? 'border-success/40 bg-success-dim text-success' : 'border-border bg-surface text-text-tertiary' }}">
                <span class="size-1.5 rounded-full {{ $transmitiendo ? 'bg-success' : 'bg-text-tertiary' }}"></span>
                {{ $transmitiendo ? 'Transmitiendo' : ($nodo->activo ? 'Sin señal' : 'Inactivo') }}
            </span>
        </div>

        @if ($this->ultimasLecturas->isEmpty())
            <p class="rounded-[10px] border border-border bg-panel px-4 py-4 text-sm text-text-tertiary">
                Sin lecturas registradas para este nodo.
            </p>
        @else
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($this->ultimasLecturas as $var)
                    @php $lectura = $var['lectura']; @endphp

                    <div wire:key="var-{{ $lectura->variable_id }}" class="rounded-[10px] border border-border bg-panel p-4">
                        <div class="flex items-start justify-between gap-2">
                            <p class="text-xs font-medium tracking-wide text-text-tertiary uppercase">{{ $lectura->variable->nombre }}</p>

                            @if ($var['enAlerta'])
                                <span class="rounded-md bg-warning-dim px-2 py-0.5 text-[10px] font-bold tracking-wide text-warning uppercase">Alerta</span>
                            @endif
                        </div>

                        <div class="mt-2 font-mono">
                            <span class="text-3xl font-bold text-text-primary">{{ \Illuminate\Support\Number::format((float) $lectura->valor, precision: 2) }}</span>
                            @if ($lectura->variable->unidad)
                                <span class="ml-1 text-sm text-text-secondary">{{ $lectura->variable->unidad }}</span>
                            @endif
                        </div>

                        <p class="mt-2 font-mono text-[11px] text-text-tertiary">
                            {{ $lectura->medido_en->diffForHumans() }}
                        </p>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="rounded-[10px] border border-border bg-panel p-4">
            <h3 class="mb-2 text-sm font-bold text-text-primary">Información del nodo</h3>

            <dl class="grid grid-cols-1 gap-x-8 md:grid-cols-2">
                @foreach ([
                    'Código del nodo' => $nodo->codigo,
                    'Zona asignada' => $nodo->potrero?->nombre ?? 'Sin zona asignada',
                    'Última lectura' => $this->ultimaLectura?->medido_en->diffForHumans() ?? 'Sin lecturas',
                    'Batería' => $this->bateria !== null ? \Illuminate\Support\Number::format($this->bateria, precision: 2).'%' : '—',
                    'Intervalo de transmisión' => 'cada '.$nodo->intervalo_transmision_minutos.' min',
                    'Instalado' => $nodo->instalado_en?->translatedFormat('d/m/Y') ?? '—',
                ] as $etiqueta => $valor)
                    <div class="flex items-center justify-between gap-3 border-b border-border py-2.5">
                        <dt class="text-sm text-text-tertiary">{{ $etiqueta }}</dt>
                        <dd class="font-mono text-sm font-medium text-text-primary">{{ $valor }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>

        <div class="rounded-[10px] border border-border bg-panel p-4">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h3 class="text-sm font-bold text-text-primary">
                    Historial de lecturas — últimas {{ \App\Livewire\Pages\DetalleNodo::HORAS_HISTORIAL }} horas
                </h3>

                @if ($this->historial->isNotEmpty())
                    <div class="w-full sm:w-64">
                        <x-select.native wire:model.live="variableHistorial" :options="$this->variableHistorialOptions" />
                    </div>
                @endif
            </div>

            @if ($this->serieHistorial->isEmpty())
                <p class="py-6 text-center text-sm text-text-tertiary">
                    Sin lecturas en las últimas {{ \App\Livewire\Pages\DetalleNodo::HORAS_HISTORIAL }} horas.
                </p>
            @else
                @php $variable = $this->serieHistorial->first()->variable; @endphp

                <x-chart
                    wire:key="historial-{{ $variable->id }}"
                    :labels="$this->serieHistorial->map(fn ($lectura) => $lectura->medido_en->format('H:i'))->all()"
                    :series="[['name' => $variable->nombre, 'data' => $this->serieHistorial->map(fn ($lectura) => (float) $lectura->valor)->all()]]"
                    :formatter="fn (float $valor) => \Illuminate\Support\Number::format($valor, maxPrecision: 2).($variable->unidad ? ' '.$variable->unidad : '')"
                    color="cyan"
                    :height="220"
                    grid
                    tooltip
                    markers
                />
            @endif
        </div>
    </div>
</div>