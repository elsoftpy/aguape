<?php

namespace App\Actions;

use App\Enums\PermissionsEnum;
use App\Enums\TipoUmbral;
use App\Models\Alerta;
use App\Models\DestinatarioAlerta;
use App\Models\Lectura;
use App\Models\Umbral;
use App\Models\User;
use App\Notifications\AlertaGenerada;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class GenerarAlertasLectura
{
    /**
     * Invoke the class instance.
     */
    public function __invoke(Lectura $lectura): void
    {
        $alertasGeneradas = [];

        foreach (TipoUmbral::cases() as $tipo) {
            $umbral = $this->resolverUmbral(lectura: $lectura, tipo: $tipo);

            if ($umbral === null || ! $this->incumple(
                lectura: $lectura,
                umbral: $umbral,
                tipo: $tipo
            )) {
                continue;
            }

            $alerta = Alerta::query()->firstOrCreate(
                ['lectura_id' => $lectura->id, 'umbral_id' => $umbral->id],
                ['nodo_id' => $lectura->nodo_id, 'variable_id' => $lectura->variable_id],
            );

            if ($alerta->wasRecentlyCreated) {
                $alertasGeneradas[] = $alerta;
            }
        }

        if ($alertasGeneradas === []) {
            return;
        }

        $correos = $this->destinatarios();

        if ($correos === []) {
            Log::warning('Alerta sin destinatarios: no se envió por correo.', [
                'alerta_ids' => array_map(fn (Alerta $alerta) => $alerta->id, $alertasGeneradas),
            ]);

            return;
        }

        foreach ($alertasGeneradas as $alerta) {
            Notification::route('mail', $correos)->notify(new AlertaGenerada($alerta));
        }

    }

    private function resolverUmbral(Lectura $lectura, TipoUmbral $tipo): ?Umbral
    {
        $umbralPorNodo = Umbral::query()
            ->where('variable_id', $lectura->variable_id)
            ->where('nodo_id', $lectura->nodo_id)
            ->where('tipo', $tipo)
            ->where('activo', true)
            ->first();

        return $umbralPorNodo ?? Umbral::query()
            ->where('variable_id', $lectura->variable_id)
            ->whereNull('nodo_id')
            ->where('tipo', $tipo)
            ->where('activo', true)
            ->first();
    }

    private function incumple(Lectura $lectura, Umbral $umbral, TipoUmbral $tipo): bool
    {
        return match ($tipo) {
            TipoUmbral::Minimo => $lectura->valor < $umbral->valor,
            TipoUmbral::Maximo => $lectura->valor > $umbral->valor,
        };
    }

    /**
     * Si no hay destinatarios configurados se usan los usuarios con permiso para gestionar alertas.
     *
     * @return array<int|string, string>
     */
    private function destinatarios(): array
    {
        $configurados = DestinatarioAlerta::query()->orderBy('email')->pluck('email');

        if ($configurados->isNotEmpty()) {
            return $configurados->all();
        }

        return User::query()
            ->permission(PermissionsEnum::GestionarAlertas->value)
            ->get()
            ->mapWithKeys(fn (User $user) => [$user->email => $user->name])
            ->all();
    }
}
