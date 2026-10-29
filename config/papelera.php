<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Retención de la papelera
    |--------------------------------------------------------------------------
    |
    | Días que un registro permanece en la papelera antes de la purga
    | automática. Un valor de cero desactiva la retención; la purga manual
    | desde la papelera sigue disponible para los administradores.
    |
    */
    'retencion_dias' => (int) env('PAPELERA_RETENCION_DIAS', 90),
];
