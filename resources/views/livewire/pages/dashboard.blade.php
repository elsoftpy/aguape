<div wire:poll.30s>
    <x-layouts.page-header :establecimiento="$this->establecimiento" title="Panel general">
        <x-slot:actions>
            @php $hayAlertasPendientes = $this->totalAlertasPendientes > 0; @endphp

            <span
                class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-medium text-text-primary
                    {{ $hayAlertasPendientes ? 'border-warning/40 bg-warning-dim' : 'border-success/40 bg-success-dim' }}"
            >
                <span class="size-1.5 rounded-full {{ $hayAlertasPendientes ? 'bg-warning' : 'bg-success' }}"></span>
                {{ $this->totalAlertasPendientes }} {{ Str::plural('alerta', $this->totalAlertasPendientes) }} pendiente{{ $this->totalAlertasPendientes === 1 ? '' : 's' }}
            </span>
        </x-slot:actions>
    </x-layouts.page-header>

    <div class="space-y-8 px-6 py-6 md:px-8">
        <section>
            <h2 class="mb-3 text-xs font-semibold tracking-wide text-text-tertiary uppercase">
                Nodos por potrero
            </h2>

            @if ($this->grupos->isEmpty())
                <div class="rounded-[10px] border border-border bg-panel px-4 py-6 text-center text-sm text-text-tertiary">
                    No hay nodos registrados todavía.
                </div>
            @else
                @php $tabInicial = $this->grupos->first()['clave']; @endphp

                <div x-data="{ tab: {{ Illuminate\Support\Js::from($tabInicial) }} }">
                    <div class="flex flex-wrap gap-2 border-b border-border pb-3">
                        @foreach ($this->grupos as $grupo)
                            <button
                                type="button"
                                x-on:click="tab = {{ Illuminate\Support\Js::from($grupo['clave']) }}"
                                x-bind:class="tab === {{ Illuminate\Support\Js::from($grupo['clave']) }} ? 'bg-accent/15 text-accent' : 'text-text-secondary hover:bg-surface hover:text-text-primary'"
                                class="rounded-full px-3.5 py-1.5 text-sm font-medium transition"
                            >
                                {{ $grupo['nombre'] }}
                                <span class="ml-1 font-mono text-xs text-text-tertiary">{{ $grupo['nodos']->count() }}</span>
                            </button>
                        @endforeach
                    </div>

                    @foreach ($this->grupos as $grupo)
                        <div
                            x-show="tab === {{ Illuminate\Support\Js::from($grupo['clave']) }}"
                            x-cloak
                            class="space-y-6 pt-5"
                        >
                            @foreach ($grupo['nodos'] as $item)
                                @php $nodo = $item['nodo']; @endphp

                                <div wire:key="nodo-{{ $nodo->id }}">
                                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                                        <div class="flex items-center gap-2.5">
                                            <span class="size-2 rounded-full {{ $nodo->activo ? 'bg-success' : 'bg-text-tertiary' }}" title="{{ $nodo->activo ? 'Activo' : 'Inactivo' }}"></span>
                                            <span class="text-sm font-semibold text-text-primary">{{ $nodo->nombre }}</span>
                                            <span class="font-mono text-xs text-text-tertiary">
                                                {{ $item['ultimaLectura']?->diffForHumans() ?? 'Sin lecturas' }}
                                            </span>
                                        </div>

                                        <span class="font-mono text-xs text-text-tertiary">
                                            Batería: {{ $item['bateria'] !== null ? rtrim(rtrim(number_format((float) $item['bateria'], 1), '0'), '.').'%' : '—' }}
                                        </span>
                                    </div>

                                    @if ($item['variables']->isEmpty())
                                        <p class="rounded-[10px] border border-border bg-panel px-4 py-4 text-sm text-text-tertiary">
                                            Sin lecturas registradas para este nodo.
                                        </p>
                                    @else
                                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                            @foreach ($item['variables'] as $var)
                                                @php $lectura = $var['lectura']; @endphp

                                                <div wire:key="var-{{ $nodo->id }}-{{ $lectura->variable_id }}" class="rounded-[10px] border border-border bg-panel p-4">
                                                    <div class="flex items-start justify-between gap-2">
                                                        <div class="flex items-center gap-2.5">
                                                            <span class="flex size-8 items-center justify-center rounded-lg bg-surface text-accent">
                                                                <x-icon :name="$var['icono']" sm />
                                                            </span>
                                                            <span class="text-sm font-semibold text-text-primary">{{ $lectura->variable->nombre }}</span>
                                                        </div>

                                                        @if ($var['enAlerta'])
                                                            <span class="rounded-md bg-warning-dim px-2 py-0.5 text-[10px] font-bold tracking-wide text-warning uppercase">Alerta</span>
                                                        @else
                                                            <span class="rounded-md bg-success-dim px-2 py-0.5 text-[10px] font-bold tracking-wide text-success uppercase">Normal</span>
                                                        @endif
                                                    </div>

                                                    <div class="mt-3 font-mono">
                                                        <span class="text-2xl font-bold text-text-primary">{{ rtrim(rtrim(number_format((float) $lectura->valor, 2), '0'), '.') }}</span>
                                                        @if ($lectura->variable->unidad)
                                                            <span class="ml-1 text-sm text-text-secondary">{{ $lectura->variable->unidad }}</span>
                                                        @endif
                                                    </div>

                                                    <div class="mt-2.5 h-1 overflow-hidden rounded-full bg-surface">
                                                        <div class="h-full {{ $var['enAlerta'] ? 'bg-warning' : 'bg-success' }}" style="width: 75%"></div>
                                                    </div>

                                                    <p class="mt-2 font-mono text-[11px] text-text-tertiary">
                                                        {{ $lectura->medido_en->diffForHumans() }}
                                                    </p>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        <section>
            <h2 class="mb-3 text-xs font-semibold tracking-wide text-text-tertiary uppercase">
                Alertas pendientes
            </h2>

            <div class="overflow-hidden rounded-[10px] border border-border bg-panel">
                @forelse ($this->alertasPendientes as $alerta)
                    <div wire:key="alerta-{{ $alerta->id }}" class="flex items-center justify-between gap-3 border-b border-border px-4 py-3 last:border-b-0">
                        <div class="flex items-center gap-3 min-w-0">
                            <x-icon name="exclamation-triangle" class="size-4 shrink-0 text-warning" />

                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-text-primary">
                                    {{ $alerta->variable->nombre }} · {{ $alerta->nodo->nombre }}
                                </p>
                                <p class="truncate font-mono text-[11px] text-text-tertiary">
                                    {{ rtrim(rtrim(number_format((float) $alerta->lectura->valor, 2), '0'), '.') }}{{ $alerta->variable->unidad }}
                                    ({{ $alerta->umbral->tipo->value }} {{ rtrim(rtrim(number_format((float) $alerta->umbral->valor, 2), '0'), '.') }})
                                </p>
                            </div>
                        </div>

                        <span class="shrink-0 font-mono text-[11px] text-text-tertiary">{{ $alerta->created_at->diffForHumans() }}</span>
                    </div>
                @empty
                    <p class="px-4 py-6 text-center text-sm text-text-tertiary">No hay alertas pendientes.</p>
                @endforelse
            </div>
        </section>
    </div>
</div>