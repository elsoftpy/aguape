<?php

namespace App\Enums;

enum EstadoAlerta: string
{
    case Pendiente = 'pendiente';
    case Atendida = 'atendida';
}
