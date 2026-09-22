<?php

namespace App\Livewire\Concerns;

use Illuminate\Support\Number;

trait AnalizaDecimales
{
    protected function analizarDecimal(?string $valor): float|false|null
    {
        if ($valor === null || trim($valor) === '') {
            return null;
        }

        return Number::parseFloat($valor);
    }

    protected function formatearDecimal(int|float|string|null $valor): ?string
    {
        return $valor !== null ? Number::format((float) $valor, precision: 2) : null;
    }
}
