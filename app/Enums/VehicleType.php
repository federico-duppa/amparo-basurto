<?php

namespace App\Enums;

/**
 * Qué clase de vehículo es: el módulo Auto sirve igual para una moto.
 *
 * Es el contrato para diferenciar comportamiento por tipo (presets de
 * mantenimiento propios, textos): hoy el tipo solo se guarda y se muestra,
 * lo demás vive en TODO.md.
 */
enum VehicleType: string
{
    case Auto = 'auto';
    case Moto = 'moto';

    public function label(): string
    {
        return match ($this) {
            self::Auto => 'Auto',
            self::Moto => 'Moto',
        };
    }
}
