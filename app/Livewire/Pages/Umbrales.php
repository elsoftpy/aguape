<?php

namespace App\Livewire\Pages;

use App\Enums\PermissionsEnum;
use App\Enums\TipoUmbral;
use App\Models\Establecimiento;
use App\Models\Umbral;
use App\Models\Variable;
use Illuminate\Support\Collection;
use Illuminate\Support\Number;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use TallStackUi\Traits\Interactions;

#[Layout('layouts::app')]
#[Title('Configuración de umbrales')]
class Umbrales extends Component
{
    use Interactions;

    /**
     * Estado editable por variable, indexado por `variable_id`. `minimo`/
     * `maximo` se muestran ya formateados en es-PY (coma decimal, punto de
     * miles) — se parsean de vuelta con `Number::parseFloat()` al guardar,
     * nunca se bindea el `decimal:4` crudo de `Umbral::valor` (confunde,
     * ver docs/dev-guide.md). Solo maneja umbrales globales (`nodo_id`
     * nulo) — un override por nodo sigue editándose a mano (tinker/seeder)
     * por ahora, decisión explícita para no sumarle un selector de nodo a
     * esta pantalla todavía.
     *
     * @var array<int, array{minimo: string|null, maximo: string|null, activo: bool}>
     */
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

        $minimo = $this->analizarValor($fila['minimo']);
        $maximo = $this->analizarValor($fila['maximo']);

        if ($minimo === false || $maximo === false) {
            $this->toast()->error('Valor inválido', 'Revisá el formato de los números ingresados.')->send();

            return;
        }

        if ($minimo !== null && $maximo !== null && $minimo >= $maximo) {
            $this->toast()->error('Rango inválido', 'El mínimo debe ser menor que el máximo.')->send();

            return;
        }

        foreach ([TipoUmbral::Minimo->value => $minimo, TipoUmbral::Maximo->value => $maximo] as $tipo => $valor) {
            if ($valor === null) {
                Umbral::query()
                    ->where('variable_id', $variableId)
                    ->whereNull('nodo_id')
                    ->where('tipo', $tipo)
                    ->delete();

                continue;
            }

            Umbral::query()->updateOrCreate(
                ['variable_id' => $variableId, 'nodo_id' => null, 'tipo' => $tipo],
                ['valor' => $valor, 'activo' => $fila['activo']],
            );
        }

        $this->filas[$variableId] = $this->cargarFila(Variable::query()->findOrFail($variableId));

        $this->toast()->success('Guardado', 'Umbrales actualizados.')->send();
    }

    /**
     * Interpreta un valor tipeado en es-PY ("1.234,56") a float. `null`
     * significa "campo vacío" (borrar el umbral de ese tipo si existía),
     * `false` significa "no se pudo interpretar" (valor inválido).
     */
    private function analizarValor(?string $valor): float|false|null
    {
        if ($valor === null || trim($valor) === '') {
            return null;
        }

        return Number::parseFloat($valor);
    }

    /**
     * @return array{minimo: string|null, maximo: string|null, activo: bool}
     */
    private function cargarFila(Variable $variable): array
    {
        $umbrales = Umbral::query()
            ->where('variable_id', $variable->id)
            ->whereNull('nodo_id')
            ->get()
            ->keyBy(fn (Umbral $umbral) => $umbral->tipo->value);

        $minimo = $umbrales->get(TipoUmbral::Minimo->value);
        $maximo = $umbrales->get(TipoUmbral::Maximo->value);

        return [
            'minimo' => $minimo ? Number::format((float) $minimo->valor, precision: 2) : null,
            'maximo' => $maximo ? Number::format((float) $maximo->valor, precision: 2) : null,
            'activo' => (bool) ($minimo?->activo ?? $maximo?->activo ?? false),
        ];
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
