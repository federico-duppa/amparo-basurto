<?php

namespace App\Enums;

/**
 * Qué clase de titular tiene una historia clínica: una persona (uno mismo,
 * un familiar, un paciente), una mascota, o un documento — una ficha de
 * paciente genérica, sin persona real detrás (material de estudio, plantillas).
 *
 * Es el contrato para diferenciar comportamiento por tipo (campos propios de
 * mascota, ficha pensada para documento): hoy el tipo solo se guarda y se
 * muestra, lo demás vive en TODO.md.
 */
enum HealthSubjectType: string
{
    case Persona = 'persona';
    case Mascota = 'mascota';
    case Documento = 'documento';

    public function label(): string
    {
        return match ($this) {
            self::Persona => 'Persona',
            self::Mascota => 'Mascota',
            self::Documento => 'Documento',
        };
    }
}
