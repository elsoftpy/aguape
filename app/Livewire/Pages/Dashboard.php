<?php

namespace App\Livewire\Pages;

use App\Enums\EstadoAlerta;
use App\Models\Alerta;
use App\Models\Establecimiento;
use App\Models\Lectura;
use App\Models\Nodo;
use App\Models\Variable;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts::app')]
#[Title('Panel general')]
class Dashboard extends Component
{
    /**
     * Ícono decorativo por variable (heroicon, no hay match 1:1 para todas).
     *
     * @var array<string, string>
     */
    private const ICONOS = [
        'nivel_agua' => 'beaker',
        'ph_agua' => 'eye-dropper',
        'temperatura_agua' => 'fire',
        'turbidez_agua' => 'cloud',
        'conductividad_agua' => 'bolt',
        'temperatura_aire' => 'sun',
        'humedad_aire' => 'cloud-arrow-down',
        'humedad_suelo' => 'square-3-stack-3d',
        'altura_pastura' => 'arrow-trending-up',
        'radiacion_solar' => 'presentation-chart-line',
        'precipitacion' => 'cloud-arrow-down',
        'ith' => 'variable',
    ];

    /**
     * Nodos agrupados por potrero (los sin potrero van en un grupo aparte,
     * al final), cada uno con la última lectura de cada variable que ese
     * nodo reporta y el último nivel de batería (RF02, RF06). Agrupar por
     * potrero mantiene el panel legible cuando hay muchos nodos: cada tab
     * muestra solo los nodos de una zona.
     *
     * @return Collection<int, array{clave: int|string, nombre: string, nodos: Collection}>
     */
    #[Computed]
    public function grupos(): Collection
    {
        $variableBateria = Variable::query()->where('clave', 'nivel_bateria')->first();

        $nodos = Nodo::query()
            ->with(['establecimiento', 'potrero'])
            ->orderBy('nombre')
            ->get();

        $nodoIds = $nodos->pluck('id');

        $ultimasLecturasPorNodoVariable = Lectura::query()
            ->whereIn('id', function ($query) use ($nodoIds, $variableBateria) {
                $query->selectRaw('MAX(id)')
                    ->from('lecturas')
                    ->whereIn('nodo_id', $nodoIds)
                    ->when($variableBateria, fn ($q) => $q->where('variable_id', '!=', $variableBateria->id))
                    ->groupBy('nodo_id', 'variable_id');
            })
            ->with('variable')
            ->get()
            ->groupBy('nodo_id');

        $lecturaIdsEnAlerta = Alerta::query()
            ->where('estado', EstadoAlerta::Pendiente)
            ->pluck('lectura_id')
            ->flip();

        $ultimasBateriasPorNodo = $variableBateria
            ? Lectura::query()
                ->where('variable_id', $variableBateria->id)
                ->whereIn('id', function ($query) use ($nodoIds, $variableBateria) {
                    $query->selectRaw('MAX(id)')
                        ->from('lecturas')
                        ->whereIn('nodo_id', $nodoIds)
                        ->where('variable_id', $variableBateria->id)
                        ->groupBy('nodo_id');
                })
                ->pluck('valor', 'nodo_id')
            : collect();

        /* Nodos con sus últimas lecturas y estado de alerta */
        $nodosConDatos = $nodos->map(function (Nodo $nodo) use ($ultimasLecturasPorNodoVariable, $lecturaIdsEnAlerta, $ultimasBateriasPorNodo) {
            $variables = $ultimasLecturasPorNodoVariable->get($nodo->id, collect())
                ->map(fn (Lectura $lectura) => [
                    'lectura' => $lectura,
                    'icono' => self::ICONOS[$lectura->variable->clave] ?? 'variable',
                    'enAlerta' => $lecturaIdsEnAlerta->has($lectura->id),
                ])
                ->sortBy(fn (array $item) => $item['lectura']->variable->nombre)
                ->values();

            return [
                'nodo' => $nodo,
                'variables' => $variables,
                'ultimaLectura' => $variables->max(fn (array $item) => $item['lectura']->medido_en),
                'bateria' => $ultimasBateriasPorNodo->get($nodo->id),
            ];
        });

        /* Agrupación de nodos por potrero */
        $tabs = $nodosConDatos
            ->groupBy(fn (array $item) => $item['nodo']->potrero_id ?? 'sin-potrero')
            ->map(fn (Collection $nodosDelGrupo, int|string $potreroId) => [
                'clave' => $potreroId,
                'nombre' => $nodosDelGrupo->first()['nodo']->potrero->nombre ?? 'Sin potrero',
                'nodos' => $nodosDelGrupo->values(),
            ])
            ->values();

        /* Separar los nodos con potreros de los sin protreros definidos */
        $conPotrero = $tabs->reject(fn (array $tab) => $tab['clave'] === 'sin-potrero')->sortBy('nombre')->values();
        $sinPotrero = $tabs->firstWhere('clave', 'sin-potrero');

        /* Devolver los nodos sin potreros al final de la colección */
        return $sinPotrero ? $conPotrero->push($sinPotrero) : $conPotrero;
    }

    /**
     * Alertas pendientes más recientes, visibles en el dashboard (RF09).
     * Limitado a 8 para la lista — para el conteo total real (usado en el
     * pill del header) ver `totalAlertasPendientes()`, que no tiene el límite
     *
     * @return Collection<int, Alerta>
     */
    #[Computed]
    public function alertasPendientes(): Collection
    {
        return Alerta::query()
            ->where('estado', EstadoAlerta::Pendiente)
            ->with(['nodo', 'variable', 'lectura', 'umbral'])
            ->latest('created_at')
            ->limit(8)
            ->get();
    }

    #[Computed]
    public function totalAlertasPendientes(): int
    {
        return Alerta::query()->where('estado', EstadoAlerta::Pendiente)->count();
    }

    #[Computed]
    public function establecimiento(): ?Establecimiento
    {
        return Establecimiento::query()->first();
    }

    public function render()
    {
        return view('livewire.pages.dashboard');
    }
}