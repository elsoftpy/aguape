<?php

namespace App\Observers;

use App\Actions\GenerarAlertasLectura;
use App\Models\Lectura;

class LecturaObserver
{
    /**
     * Evalúa alertas (RF09) cada vez que se guarda una lectura, ya sea
     * nueva o reenviada (el `updateOrCreate` idempotente de
     * `ProcesarIngestaNodo` también dispara `saved`). Deliberadamente
     * desacoplado de `ProcesarIngestaNodo` (ver `dev-guide.md`): guardar la
     * lectura y evaluar si dispara una alerta son responsabilidades
     * separadas, y así cualquier otro camino futuro que cree/actualice una
     * `Lectura` también queda cubierto.
     */
    public function saved(Lectura $lectura): void
    {
        app(GenerarAlertasLectura::class)($lectura);
    }
}
