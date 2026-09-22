<?php

namespace App\Enums;

enum PermissionsEnum: string
{
    case VerDashboard = 'ver-dashboard';
    case GestionarUmbrales = 'gestionar-umbrales';
    case GestionarNodos = 'gestionar-nodos';
    case GestionarPotreros = 'gestionar-potreros';
    case GestionarAlertas = 'gestionar-alertas';
    case VerHistorialAlertas = 'ver-historial-alertas';
    case GestionarUsuarios = 'gestionar-usuarios';
    case GestionarFacturacion = 'gestionar-facturacion';
}
