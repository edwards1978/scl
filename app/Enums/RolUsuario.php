<?php

declare(strict_types=1);

namespace App\Enums;

enum RolUsuario: string
{
    case Administrador = 'administrador';
    case Analista = 'analista';
    case Consulta = 'consulta';

    public function label(): string
    {
        return match ($this) {
            self::Administrador => 'Administrador',
            self::Analista => 'Analista',
            self::Consulta => 'Consulta',
        };
    }

    public function descripcion(): string
    {
        return match ($this) {
            self::Administrador => 'Acceso total, configuración global y gestión de usuarios.',
            self::Analista => 'Gestiona la nómina de los patronos que tenga asignados.',
            self::Consulta => 'Solo lectura de los patronos que tenga asignados.',
        };
    }

    public function puedeGestionar(): bool
    {
        return $this !== self::Consulta;
    }

    public function esAdministrador(): bool
    {
        return $this === self::Administrador;
    }

    /**
     * @return array<string, string>
     */
    public static function opciones(): array
    {
        $opciones = [];

        foreach (self::cases() as $caso) {
            $opciones[$caso->value] = $caso->label();
        }

        return $opciones;
    }
}
