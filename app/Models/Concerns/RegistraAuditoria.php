<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\HistorialEstado;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

/**
 * Registra quién crea y modifica un modelo, y deja rastro de los cambios de
 * estado en `historial_estados`.
 *
 * Los modelos que la usan declaran qué campos son de estado y qué valores
 * dejan sello de aprobación o anulación.
 */
trait RegistraAuditoria
{
    public static function bootRegistraAuditoria(): void
    {
        static::creating(function (Model $modelo): void {
            $modelo->registrarAuditoriaDeCreacion();
        });

        static::updating(function (Model $modelo): void {
            $modelo->registrarAuditoriaDeActualizacion();
        });

        static::created(function (Model $modelo): void {
            $modelo->registrarHistorialDeCreacion();
        });

        static::updated(function (Model $modelo): void {
            $modelo->registrarHistorialDeCambios();
        });

        // Deja rastro de quién envía el registro a la papelera y quién lo
        // restaura. Solo aplica a modelos con borrado lógico.
        static::deleting(function (Model $modelo): void {
            if (! in_array(SoftDeletes::class, class_uses_recursive($modelo), true)) {
                return;
            }

            if (method_exists($modelo, 'isForceDeleting') && $modelo->isForceDeleting()) {
                return;
            }

            $modelo->registrarHistorial('papelera', null, 'eliminado');
        });

        if (in_array(SoftDeletes::class, class_uses_recursive(static::class), true)) {
            static::restored(function (Model $modelo): void {
                $modelo->registrarHistorial('papelera', 'eliminado', 'restaurado');
            });
        }
    }

    /**
     * Campos cuyo cambio se guarda en el historial de estados.
     *
     * @return list<string>
     */
    protected function camposDeEstado(): array
    {
        return [];
    }

    /**
     * Estados que dejan sello, con las columnas [usuario, fecha] a completar.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    protected function sellosDeEstado(): array
    {
        return [];
    }

    /**
     * Estados que invalidan una aprobación previa y limpian sus sellos.
     *
     * @return array<string, list<string>>
     */
    protected function sellosQueSeReinician(): array
    {
        return [];
    }

    public function historialEstados(): MorphMany
    {
        return $this->morphMany(HistorialEstado::class, 'auditable')->latest('id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function annulledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'annulled_by');
    }

    protected function registrarAuditoriaDeCreacion(): void
    {
        $usuarioId = Auth::id();

        if ($usuarioId === null) {
            return;
        }

        if ($this->getAttribute('created_by') === null) {
            $this->setAttribute('created_by', $usuarioId);
        }

        if ($this->getAttribute('updated_by') === null) {
            $this->setAttribute('updated_by', $usuarioId);
        }
    }

    protected function registrarAuditoriaDeActualizacion(): void
    {
        $usuarioId = Auth::id();

        if ($usuarioId !== null) {
            $this->setAttribute('updated_by', $usuarioId);
        }

        foreach ($this->camposDeEstado() as $campo) {
            if (! $this->isDirty($campo)) {
                continue;
            }

            $nuevo = $this->getAttribute($campo);

            foreach ($this->sellosQueSeReinician()[$nuevo] ?? [] as $columna) {
                $this->setAttribute($columna, null);
            }

            $sello = $this->sellosDeEstado()[$nuevo] ?? null;

            if ($sello === null || $usuarioId === null) {
                continue;
            }

            [$columnaUsuario, $columnaFecha] = $sello;
            $this->setAttribute($columnaUsuario, $usuarioId);
            $this->setAttribute($columnaFecha, now());
        }
    }

    protected function registrarHistorialDeCreacion(): void
    {
        foreach ($this->camposDeEstado() as $campo) {
            $valor = $this->getAttribute($campo);

            if ($valor === null) {
                continue;
            }

            $this->registrarHistorial($campo, null, $valor);
        }
    }

    protected function registrarHistorialDeCambios(): void
    {
        foreach ($this->camposDeEstado() as $campo) {
            if (! $this->wasChanged($campo)) {
                continue;
            }

            $anterior = $this->getOriginal($campo);
            $nuevo = $this->getAttribute($campo);

            if ((string) $anterior === (string) $nuevo) {
                continue;
            }

            $this->registrarHistorial($campo, $anterior, $nuevo);
        }
    }

    protected function registrarHistorial(string $campo, mixed $anterior, mixed $nuevo): void
    {
        HistorialEstado::create([
            'patrono_id' => $this->getAttribute('patrono_id'),
            'auditable_type' => static::class,
            'auditable_id' => $this->getKey(),
            'campo' => $campo,
            'valor_anterior' => $anterior === null ? null : (string) $anterior,
            'valor_nuevo' => $nuevo === null ? null : (string) $nuevo,
            'user_id' => Auth::id(),
        ]);
    }
}
