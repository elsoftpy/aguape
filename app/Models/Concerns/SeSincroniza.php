<?php

namespace App\Models\Concerns;

use App\Models\SincronizacionPendiente;
use Illuminate\Database\Eloquent\Model;

trait SeSincroniza
{
    public static function bootSeSincroniza(): void
    {
        static::saved(fn (Model $modelo) => SincronizacionPendiente::registrar($modelo->getTable(), $modelo->getKey()));
        static::deleted(fn (Model $modelo) => SincronizacionPendiente::registrar($modelo->getTable(), $modelo->getKey(), SincronizacionPendiente::ELIMINAR));
    }
}
