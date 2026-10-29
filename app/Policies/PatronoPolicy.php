<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Patrono;
use App\Models\User;

class PatronoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->esAdministrador();
    }

    public function view(User $user, Patrono $patrono): bool
    {
        return $user->tieneAcceso($patrono);
    }

    public function create(User $user): bool
    {
        return $user->esAdministrador();
    }

    public function update(User $user, Patrono $patrono): bool
    {
        return $user->esAdministrador();
    }

    public function delete(User $user, Patrono $patrono): bool
    {
        return $user->esAdministrador();
    }
}
