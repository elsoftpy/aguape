<?php

namespace App\Exceptions;

use RuntimeException;

class NodoInactivoException extends RuntimeException
{
    public function __construct(public readonly string $codigoNodo)
    {
        parent::__construct("El nodo '{$codigoNodo}' está inactivo y no admite ingesta de lecturas");
    }
}
