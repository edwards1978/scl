<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Empleado;
use App\Models\User;

class EmpleadoPolicy
{
    /** El listado siempre se filtra por el patrono del contexto. */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Empleado $empleado): bool
    {
        return $user->tieneAcceso($empleado->patrono_id);
    }

    public function create(User $user): bool
    {
        return $user->puedeGestionar();
    }

    public function update(User $user, Empleado $empleado): bool
    {
        return $user->puedeGestionar() && $user->tieneAcceso($empleado->patrono_id);
    }

    public function delete(User $user, Empleado $empleado): bool
    {
        return $user->puedeGestionar() && $user->tieneAcceso($empleado->patrono_id);
    }
}
