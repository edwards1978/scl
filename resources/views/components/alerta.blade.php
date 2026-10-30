@props([
    'tipo' => 'info',
    'mensaje' => null,
    'duracion' => null,
])

@php
    $variantes = [
        'success' => 'bg-green-50 border-green-200 text-green-800',
        'error' => 'bg-red-50 border-red-200 text-red-800',
        'warning' => 'bg-amber-50 border-amber-200 text-amber-800',
        'info' => 'bg-blue-50 border-blue-200 text-blue-800',
    ];

    $clases = $variantes[$tipo] ?? $variantes['info'];
    $duracion = $duracion ?? (in_array($tipo, ['error', 'warning'], true) ? 12000 : 8000);
@endphp

<div
    data-alerta
    data-duracion="{{ $duracion }}"
    role="alert"
    aria-live="polite"
    {{ $attributes->merge(['class' => "border p-3 text-sm {$clases}"]) }}
>
    <div class="flex items-start justify-between gap-3">
        <div class="flex-1">
            @if ($mensaje !== null)
                {{ $mensaje }}
            @else
                {{ $slot }}
            @endif
        </div>
        <button
            type="button"
            data-cerrar-alerta
            class="-mr-1 -mt-1 shrink-0 px-2 py-0.5 text-lg leading-none opacity-60 hover:opacity-100"
            aria-label="Cerrar mensaje"
            title="Cerrar mensaje"
        >×</button>
    </div>
</div>

@once
    <script>
        (() => {
            const cerrar = (alerta) => {
                if (!alerta || alerta.dataset.cerrando === '1') {
                    return;
                }

                alerta.dataset.cerrando = '1';
                alerta.style.transition = 'opacity .15s ease';
                alerta.style.opacity = '0';

                window.setTimeout(() => alerta.remove(), 160);
            };

            document.addEventListener('click', (evento) => {
                const boton = evento.target.closest('[data-cerrar-alerta]');
                if (boton) {
                    cerrar(boton.closest('[data-alerta]'));
                }
            });

            const iniciarCierreAutomatico = () => {
                document.querySelectorAll('[data-alerta][data-duracion]').forEach((alerta) => {
                    const duracion = Number.parseInt(alerta.dataset.duracion, 10);

                    if (Number.isFinite(duracion) && duracion > 0) {
                        window.setTimeout(() => {
                            if (alerta.isConnected) {
                                cerrar(alerta);
                            }
                        }, duracion);
                    }
                });
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', iniciarCierreAutomatico);
            } else {
                iniciarCierreAutomatico();
            }
        })();
    </script>
@endonce
