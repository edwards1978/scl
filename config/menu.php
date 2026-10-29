<?php

// Definición única del menú de navegación.
// Consumida por el sidebar (partials/menu-sidebar) y por el dashboard.
// Cada opción: label, icono (clave de partials/icono), ruta (route name),
// activo (array de prefijos de ruta para resaltar), excluir (substrings que
// desactivan el resaltado, opcional), create (ruta del botón "+", opcional),
// desc (descripción corta para el dashboard).

return [
    'entidades' => [
        'titulo' => 'Entidades',
        'opciones' => [
            [
                'label' => 'Patronos',
                'icono' => 'patronos',
                'ruta' => 'patronos.index',
                'activo' => ['patronos'],
                'create' => 'patronos.create',
                'desc' => 'Gestión de empresas empleadoras',
            ],
            [
                'label' => 'Empleados',
                'icono' => 'empleados',
                'ruta' => 'empleados.index',
                'activo' => ['empleados'],
                'create' => 'empleados.create',
                'desc' => 'Gestión de trabajadores',
            ],
            [
                'label' => 'Sueldos',
                'icono' => 'sueldos',
                'ruta' => 'sueldos.index',
                'activo' => ['sueldos'],
                'desc' => 'Sueldos mensuales y variaciones salariales',
            ],
        ],
    ],
    'calculos' => [
        'titulo' => 'Cálculos Laborales',
        'opciones' => [
            [
                'label' => 'Prestaciones Sociales',
                'icono' => 'prestaciones',
                'ruta' => 'prestaciones.index',
                'activo' => ['prestaciones'],
                'desc' => 'Garantía, intereses y estado de cuenta (Art. 142-143 LOTTT)',
            ],
            [
                'label' => 'Anticipos y préstamos',
                'icono' => 'anticipos',
                'ruta' => 'movimientos-prestaciones.index',
                'activo' => ['movimientos-prestaciones'],
                'create' => 'movimientos-prestaciones.create',
                'desc' => 'Anticipos y préstamos sobre prestaciones (Art. 144 LOTTT)',
            ],
            [
                'label' => 'Intereses sobre prest.',
                'icono' => 'intereses',
                'ruta' => 'intereses-prestaciones.index',
                'activo' => ['intereses-prestaciones'],
                'create' => 'intereses-prestaciones.create',
                'desc' => 'Pagos de intereses sobre prestaciones (Art. 143 LOTTT)',
            ],
            [
                'label' => 'Vacaciones',
                'icono' => 'vacaciones',
                'ruta' => 'vacaciones.index',
                'activo' => ['vacaciones'],
                'create' => 'vacaciones.create',
                'desc' => 'Cálculo y control de vacaciones (LOTTT)',
            ],
            [
                'label' => 'Bono de fin de año',
                'icono' => 'utilidades',
                'ruta' => 'utilidades.index',
                'activo' => ['utilidades'],
                'desc' => 'Bonificación de fin de año (Art. 132 LOTTT)',
            ],
            [
                'label' => 'Liquidaciones',
                'icono' => 'liquidaciones',
                'ruta' => 'liquidaciones.index',
                'activo' => ['liquidaciones'],
                'create' => 'liquidaciones.create',
                'desc' => 'Liquidación de prestaciones sociales (Art. 142 lit. c)',
            ],
        ],
    ],
    'reportes' => [
        'titulo' => 'Reportes',
        'opciones' => [
            [
                'label' => 'Reporte de Vacaciones',
                'icono' => 'reportes',
                'ruta' => 'reportes.vacaciones.index',
                'activo' => ['reportes'],
                'desc' => 'Historial y estadísticas de vacaciones',
            ],
        ],
    ],
    'sistema' => [
        'titulo' => 'Sistema',
        'opciones' => [
            [
                'label' => 'Configuración',
                'icono' => 'configuracion',
                'ruta' => 'configuracion.index',
                'activo' => ['configuracion'],
                'desc' => 'Utilidades, deducciones, recibos y prestaciones del patrono',
            ],
            [
                'label' => 'Días Feriados',
                'icono' => 'feriados',
                'ruta' => 'feriados.index',
                'activo' => ['feriados'],
                'desc' => 'Calendario laboral y días no laborables',
            ],
            [
                'label' => 'Tasas BCV',
                'icono' => 'tasas',
                'ruta' => 'tasas.index',
                'activo' => ['tasas.', 'tasas-bcv'],
                'desc' => 'Tasa de cambio del dólar publicada por el BCV',
            ],
            [
                'label' => 'Tasas de Interés',
                'icono' => 'tasas-interes',
                'ruta' => 'tasas-interes.index',
                'activo' => ['tasas-interes'],
                'desc' => 'Tasa de interés sobre prestaciones sociales (BCV)',
            ],
            [
                'label' => 'Salarios Mínimos',
                'icono' => 'salarios-minimos',
                'ruta' => 'salarios-minimos.index',
                'activo' => ['salarios-minimos'],
                'desc' => 'Salarios mínimos legales vigentes',
            ],
        ],
    ],
];
