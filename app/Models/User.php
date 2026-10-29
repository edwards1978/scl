<?php

namespace App\Models;

use App\Enums\RolUsuario;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'rol',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'rol' => RolUsuario::class,
        ];
    }

    public function patronos(): BelongsToMany
    {
        return $this->belongsToMany(Patrono::class)->withTimestamps();
    }

    public function esAdministrador(): bool
    {
        return $this->rol === RolUsuario::Administrador;
    }

    public function puedeGestionar(): bool
    {
        return (bool) $this->rol?->puedeGestionar();
    }

    public function puedeAprobar(): bool
    {
        return (bool) $this->rol?->puedeAprobar();
    }

    public function puedeEliminar(): bool
    {
        return (bool) $this->rol?->puedeEliminar();
    }

    /** Indica si el usuario puede operar sobre el patrono indicado. */
    public function tieneAcceso(Patrono|int $patrono): bool
    {
        if ($this->esAdministrador()) {
            return true;
        }

        $patronoId = $patrono instanceof Patrono ? $patrono->id : $patrono;

        return $this->patronos()->whereKey($patronoId)->exists();
    }

    /**
     * Patronos activos que el usuario puede seleccionar como contexto.
     *
     * @return Collection<int, Patrono>
     */
    public function patronosDisponibles(): Collection
    {
        $query = Patrono::query()->where('estado', 'Activo')->orderBy('razon_social');

        if (! $this->esAdministrador()) {
            $query->whereIn('id', $this->patronos()->select('patronos.id'));
        }

        return $query->get();
    }

    /**
     * Identificadores de patronos sobre los que el usuario puede operar.
     *
     * @return list<int>
     */
    public function idsDePatronosPermitidos(): array
    {
        if ($this->esAdministrador()) {
            return Patrono::query()->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        return $this->patronos()->pluck('patronos.id')->map(fn ($id) => (int) $id)->all();
    }
}
