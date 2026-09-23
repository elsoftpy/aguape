<?php

namespace App\Livewire\Pages;

use App\Enums\PermissionsEnum;
use App\Enums\TipoUmbral;
use App\Livewire\Concerns\AnalizaDecimales;
use App\Models\Establecimiento;
use App\Models\Umbral;
use App\Models\Variable;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use TallStackUi\Traits\Interactions;

#[Layout('layouts::app')]
#[Title('Configuración de umbrales')]
class Umbrales extends Component
{
    use AnalizaDecimales, Interactions;

    public array $filas = [];

    public function mount(): void
    {
        foreach (Variable::query()->orderBy('id')->get() as $variable) {
            $this->filas[$variable->id] = $this->cargarFila($variable);
        }
    }

    public function guardar(int $variableId): void
    {
        if (! auth()->user()->can(PermissionsEnum::GestionarUmbrales->value)) {
            $this->toast()->error('Sin permiso', 'No tenés permiso para configurar umbrales.')->send();

            return;
        }

        $fila = $this->filas[$variableId];

        $minimo = $this->analizarDecimal($fila['minimo']);
        $maximo = $this->analizarDecimal($fila['maximo']);

        if ($minimo === null && $maximo === null) {
            $this->toast()->error('Sin límites', 'Ingresá al menos un valor mínimo o máximo.')->send();

            return;
        }

        if ($minimo === false || $maximo === false) {
            $this->toast()->error('Valor inválido', 'Revisá el formato de los números ingresados.')->send();

            return;
        }

        if ($minimo !== null && $maximo !== null && $minimo >= $maximo) {
            $this->toast()->error('Rango inválido', 'El mínimo debe ser menor que el máximo.')->send();

            return;
        }

        $limites = [
            TipoUmbral::Minimo->value => ['valor' => $minimo, 'activo' => $fila['minimoActivo']],
            TipoUmbral::Maximo->value => ['valor' => $maximo, 'activo' => $fila['maximoActivo']],
        ];

        $existentes = $this->umbralesGlobales($variableId);

        foreach ($limites as $tipo => $limite) {
            if ($limite['valor'] === null && $existentes->has($tipo)) {
                $this->toast()->error(
                    'No se puede quitar el límite',
                    'El límite '.($tipo === TipoUmbral::Minimo->value ? 'mínimo' : 'máximo').' ya está guardado. Para dejar de usarlo, desactivalo.',
                )->send();

                $this->filas[$variableId][$tipo] = $this->formatearDecimal($existentes->get($tipo)->valor);
                $this->filas[$variableId][$tipo.'Activo'] = $existentes->get($tipo)->activo;

                return;
            }
        }

        foreach ($limites as $tipo => $limite) {
            if ($limite['valor'] === null) {
                continue;
            }

            Umbral::query()->updateOrCreate(
                ['variable_id' => $variableId, 'nodo_id' => null, 'tipo' => $tipo],
                ['valor' => $limite['valor'], 'activo' => $limite['activo']],
            );
        }

        $this->filas[$variableId] = $this->cargarFila(Variable::query()->findOrFail($variableId));

        $this->toast()->success('Guardado', 'Umbrales actualizados.')->send();
    }

    /**
     * @return array{minimo: string|null, maximo: string|null, minimoActivo: bool, maximoActivo: bool}
     */
    private function cargarFila(Variable $variable): array
    {
        $umbrales = $this->umbralesGlobales($variable->id);

        $minimo = $umbrales->get(TipoUmbral::Minimo->value);
        $maximo = $umbrales->get(TipoUmbral::Maximo->value);

        return [
            'minimo' => $this->formatearDecimal($minimo?->valor),
            'maximo' => $this->formatearDecimal($maximo?->valor),
            'minimoActivo' => $minimo?->activo ?? true,
            'maximoActivo' => $maximo?->activo ?? true,
        ];
    }

    /**
     * @return Collection<string, Umbral>
     */
    private function umbralesGlobales(int $variableId): Collection
    {
        return Umbral::query()
            ->where('variable_id', $variableId)
            ->whereNull('nodo_id')
            ->get()
            ->keyBy(fn (Umbral $umbral) => $umbral->tipo->value);
    }

    /**
     * @return Collection<int, Variable>
     */
    #[Computed]
    public function variables(): Collection
    {
        return Variable::query()->orderBy('id')->get();
    }

    #[Computed]
    public function establecimiento(): ?Establecimiento
    {
        return Establecimiento::query()->first();
    }

    public function render()
    {
        return view('livewire.pages.umbrales');
    }
}
