# Análisis técnico del proyecto SCL

**Sistema:** SCL — Sistema de Cálculos Laborales (LOTTT)
**Ruta analizada:** `/var/www/html/scl`
**Fecha del análisis:** 23/10/2026
**Alcance:** arquitectura, módulos, lógica de cálculo, base de datos, seguridad, despliegue, pruebas y mantenibilidad.
**Naturaleza:** auditoría técnica. No sustituye la validación de un abogado laboral ni de un contador público; las fórmulas legales deben firmarse con casos de prueba antes de usarse para pagos reales.

> No se modificó información de producción. Las comprobaciones que requerían escribir datos se ejecutaron dentro de transacciones y se revirtieron; se verificó que no quedaran registros de prueba.

---

## 1. Resumen ejecutivo

SCL es una aplicación **Laravel 12 + Livewire 4 + Blade/Tailwind + MySQL/MariaDB** para administrar patronos, empleados, sueldos, vacaciones, prestaciones sociales, anticipos/préstamos, liquidaciones, utilidades, tasas BCV, tasas de interés, feriados y salarios mínimos, con generación de PDF.

**Lo que está bien:**

- La aplicación **funciona**: las 26 pantallas GET probadas con sesión autenticada respondieron `200`.
- Hay una separación razonable en capas: controladores, componentes Livewire, 11 servicios de cálculo, 19 modelos, 34 migraciones y 108 rutas.
- Existe aislamiento por patrono en la mayoría de consultas (a través de `patrono_id` de sesión) y se validan cédula/RIF, duplicados y períodos.
- Buenas prácticas presentes: `APP_DEBUG=false`, registro público deshabilitado, throttling de login, CSRF, regeneración de sesión, cabeceras `X-Content-Type-Options`, `X-Frame-Options` y `Referrer-Policy`.
- Documentación de despliegue (`DEPLOY.md`) y pruebas de humo de módulos.

**Riesgos principales (en orden de impacto):**

1. **Tres fórmulas distintas para las prestaciones sociales** producen saldos incompatibles para el mismo trabajador. La liquidación final no compara ni integra la garantía acumulada. Es el riesgo legal/económico más alto.
2. **No hay roles, permisos ni aislamiento por usuario.** Cualquier usuario autenticado puede cambiar de patrono y ver/editar toda la nómina, aprobar liquidaciones y borrar historial. No hay auditoría (`created_by`, `approved_by`, historial de estados).
3. **Credenciales y datos sensibles:** se rotó la contraseña del administrador y se retiraron del árbol actual los scripts y el backup SQL; el backup todavía está en el historial de Git y debe purgarse/rotarse si el repositorio se compartió.
4. **Despliegue frágil:** se agregó `deploy/fix-permissions.sh` para que `www-data` lea `.env` y escriba en `storage/`/`bootstrap/cache`; requiere ejecutarse como root. Mientras no se ejecute, el sitio sigue dependiendo de la configuración y las vistas precompiladas.
5. **Pruebas:** la suite se aisló de la configuración de producción y ahora pasa **28/28** con tablas `test_`; sigue faltando cobertura de los servicios de cálculo.
6. **Lógica de negocio:** se corrigieron los fallos inequívocos (cambio de patrono, estado de liquidaciones, recibos duplicados, borrados destructivos). Las discrepancias de fórmulas (garantía vs. liquidación, intereses capitalizados, días adicionales) siguen pendientes de decisión legal.
7. **Dependencias con avisos de seguridad:** `composer audit` reporta 17 avisos en 6 paquetes (16 en 5 paquetes sin dependencias de desarrollo), incluido un XSS de Livewire y varios DoS de `league/commonmark`.

---

## 2. Ficha técnica

| Elemento | Valor |
|---|---|
| Framework | Laravel 12.64.0 |
| PHP | 8.3.6 |
| Frontend | Blade, Tailwind CSS 3, Alpine.js, Livewire 4.3 |
| Base de datos | MariaDB/MySQL (`scl`) |
| PDF | `barryvdh/laravel-dompdf` |
| Excel | `phpoffice/phpspreadsheet` |
| Rutas | 108 |
| Migraciones | 34 |
| Modelos | 19 |
| Servicios de dominio | 11 |
| Controladores de módulos | 15 |
| Vistas Blade | 72 |
| Líneas PHP en `app/` | 8.363 |
| Líneas Blade | 5.065 |
| Líneas de pruebas | 462 |
| Estado de Git | 2 commits; árbol de trabajo con cambios sin commitear |

### Datos actuales en la base

| Tabla | Registros |
|---|---:|
| `patronos` | 8 (8 activos) |
| `empleados` | 21 (15 activos, 6 inactivos) |
| `users` | 2 |
| `vacaciones` / `vacaciones_recibos` | 32 / 14 |
| `liquidaciones` | 7 (7 aprobadas) |
| `prestaciones_sociales` | 114 períodos |
| `movimientos_prestaciones` | 0 |
| `sueldos` / `cambios_salarios` | 571 / 0 |
| `bonificaciones_fin_ano` | 3 |
| `tasas_bcv` / `tasas_interes_prestaciones` | 622 / 348 |
| `feriados` / `salarios_minimos` | 266 / 10 |

**Anomalía de datos:** hay 7 liquidaciones aprobadas pero solo 6 empleados inactivos. El empleado 10 tiene una liquidación aprobada y sigue `Activo`, por lo que puede generar nuevos cálculos y otra liquidación. Puede ser una reincorporación no modelada, pero debe revisarse.

---

## 3. Arquitectura y funcionamiento

### 3.1 Capas

```
routes/web.php
  └── Controladores Web (15)  ──┐
  └── Componentes Livewire (4) ─┼── Servicios de dominio (11) ── Modelos Eloquent (19)
  └── Form Requests (7)        ─┘
                                  └── Vistas Blade + PDF (Dompdf)
```

**Servicios principales:**

- `VacacionesService`: días, fechas reales, feriados/descansos, deducciones y recibo.
- `PrestacionesSocialesService`: garantía trimestral, días adicionales, intereses y estado de cuenta.
- `MovimientosPrestacionesService`: anticipos/préstamos, límites y saldos.
- `LiquidacionService`: liquidación final, fracciones, indemnización y deducciones.
- `UtilidadesService`: bonificación de fin de año.
- `SueldosService`: nómina mensual, variaciones y regeneración.
- `SalarioIntegralService`: salario diario integral y alícuotas.
- `TasasBcvService` / `TasasInteresService`: importación y consulta de tasas.
- `CalendarioLaboral` / `DiasFeriadosService`: días hábiles y feriados.
- `MovimientosPrestacionesService`: préstamos y anticipos.

### 3.2 Flujo de contexto (multi-patrono)

1. El usuario inicia sesión (`auth`).
2. `ValidarContextoPatrono` exige `session('patrono_id')`; si no existe, intenta restaurarlo desde la cookie `scl_ctx` y, si falla, redirige a `entorno.seleccionar`.
3. `EntornoController@seleccionar` y `guardar` permiten escoger **cualquier patrono activo** y lo guardan en sesión/cookie.
4. Los controladores leen `session('patrono_id')` y filtran por `patrono_id` en sus consultas.

**Fortaleza:** el filtrado por patrono está aplicado en la mayoría de controladores y en los componentes Livewire; no se encontraron consultas evidentes sin ese filtro en los módulos de negocio.

**Debilidad estructural:** el contexto no está ligado al usuario. No existe `users.patrono_id`, tabla pivote, roles ni políticas. Cualquier usuario autenticado puede seleccionar cualquier patrono activo. La cookie `scl_ctx` es JSON sin firmar; no es una escalada por sí sola, pero confirma que el modelo de autorización es solo “usuario autenticado”.

### 3.3 Módulos funcionales

- **Entidades:** patronos, empleados, sueldos.
- **Cálculos:** prestaciones, anticipos/préstamos, vacaciones, liquidaciones, utilidades.
- **Configuración:** tasas BCV, tasas de interés, salarios mínimos, feriados, configuración del patrono.
- **Reportes:** reporte de vacaciones en pantalla y PDF; recibos PDF de vacaciones, prestaciones, liquidaciones y utilidades.

---

## 4. Estado de ejecución verificado

### 4.1 Prueba de humo autenticada

Con el usuario administrador y el patrono 4 seleccionado, se probaron estas rutas; **todas respondieron 200**:

```
/dashboard
/patronos
/empleados
/empleados/8/edit
/vacaciones
/vacaciones/crear
/vacaciones/24
/prestaciones
/prestaciones/8
/liquidaciones
/liquidaciones/crear
/utilidades
/utilidades/6
/movimientos-prestaciones
/movimientos-prestaciones/crear
/sueldos
/sueldos/variaciones
/configuracion
/feriados
/tasas-bcv
/tasas-bcv/verificar
/tasas-interes
/salarios-minimos
/reportes/vacaciones
/profile
```

También se comprobó:

- `GET /scl/login` → `200`.
- `/scl/register` → `404` (registro deshabilitado, correcto).
- `/scl/.env` → `403` y `/scl/database_scl_backup.sql` → `404` (no expuestos con el `DocumentRoot`/Alias actual, correcto).
- Cabeceras de seguridad presentes: `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`; cookies de sesión `HttpOnly` y `SameSite=Lax`.

### 4.2 Pruebas automatizadas

**Estado inicial (diagnóstico):**

```
php artisan test
Tests: 23 failed, 3 passed (30 assertions)
```

La causa raíz era que existía `bootstrap/cache/config.php` generado en producción con `app.url = http://localhost/scl`. En el entorno de pruebas, `$this->get('/login')` se resolvía contra `http://localhost/scl/login` y devolvía 404 porque la ruta real es `/login`. Se comprobó retirando temporalmente la caché de configuración: la prueba de login pasó. No era un fallo del código de login, sino de aislamiento del entorno.

Además, las líneas de SQLite estaban comentadas en `phpunit.xml`, por lo que las pruebas usaban la base de datos de producción.

**Corrección aplicada:**

- `phpunit.xml` apunta las cachés de configuración, rutas, eventos, servicios y paquetes a `storage/framework/testing/*`, de modo que las pruebas no leen `bootstrap/cache` de producción.
- `config/database.php` permite un prefijo de base por `DB_PREFIX`.
- `tests/bootstrap.php` crea el esquema de pruebas con prefijo `test_` la primera vez.
- `tests/TestCase.php` usa `DatabaseTransactions`; todas las pruebas hacen rollback.
- Las pruebas ya no tocan las tablas reales: usan `test_*` dentro de la misma instancia MySQL.

**Resultado actual:**

```
php artisan test
Tests: 28 passed (74 assertions)
```

**Limitación:** este servidor no tiene `pdo_sqlite` y el usuario `scl` solo tiene permisos sobre `scl.*`, así que no se puede crear una base de pruebas separada. Se usa el prefijo `test_` como aislamiento. Si más adelante se dispone de otra base, basta cambiar `DB_DATABASE` y dejar `DB_PREFIX` vacío.

**Cobertura pendiente:** sigue sin haber pruebas de los servicios de cálculo (vacaciones, prestaciones, liquidaciones, utilidades); solo se cubren autenticación, perfil, ejemplo y el guardián de feriados.

### 4.3 Calidad y dependencias

| Comprobación | Resultado |
|---|---|
| `php -l` en `app`, `routes`, `database` | Sin errores de sintaxis |
| `composer validate` | Correcto |
| `vendor/bin/pint --test` | 92 archivos/líneas con problemas de estilo |
| `composer audit --locked` | 17 avisos en 6 paquetes |
| `composer audit --locked --no-dev` | 16 avisos en 5 paquetes |
| `npm audit --omit=dev` | 0 vulnerabilidades |
| CI/CD | No existe `.github`, GitLab CI ni equivalente |

**Avisos de dependencias relevantes:**

- `livewire/livewire` — CVE-2026-81887, XSS DOM-based (medio).
- `league/commonmark` — varios DoS de severidad alta y bypass de XSS.
- `symfony/http-foundation` — ReDoS.
- `symfony/yaml` — recursión no acotada.
- `laravel/framework` — XSS en página de debug (bajo; `APP_DEBUG=false`).
- `league/flysystem` — normalización de rutas (bajo).

No se evaluó la explotabilidad concreta en esta aplicación; se recomienda actualizar y volver a ejecutar `composer audit`.

### 4.4 Estado del repositorio

- Solo 2 commits (`Initial commit` y actualización de README).
- Hay cambios sin commitear: `config/app.php`, `routes/auth.php`, `phpunit.xml`, `public/index.php`, pruebas, `.env.example`, README.
- Archivos no versionados críticos: `app/Http/Request.php`, `deploy/apache-scl.conf`, `DEPLOY.md`.
- **`public/index.php` (modificado) referencia `App\Http\Request`, pero `app/Http/Request.php` no está en Git.** El sitio actual funciona porque el archivo existe en disco; un despliegue desde un commit que incluya `public/index.php` sin ese archivo fallará con “Class App\Http\Request not found”.
- `bootstrap/cache/packages.php` y `services.php` aparecen eliminados del índice; hay un archivo extraño `%CK%` eliminado.

---

## 5. Hallazgos de lógica de negocio

> Esta sección es la más importante del informe. Los importes son ilustrativos del estado actual y deben validarse con un especialista laboral antes de tomar decisiones de pago.

### 5.1 Tres fórmulas distintas para prestaciones sociales — **CRÍTICO**

El sistema calcula el mismo concepto de tres maneras diferentes:

| Componente | Fórmula implementada | Archivo |
|---|---|---|
| Garantía / estado de cuenta | 15 días por trimestre + 2 días por año, con intereses capitalizados | `PrestacionesSocialesService::calcularMes()` (`app/Services/PrestacionesSocialesService.php:144`) |
| Saldo para límite de anticipos | 30 días el primer año + 32 días por cada año adicional | `MovimientosPrestacionesService::saldoPrestaciones()` (`app/Services/MovimientosPrestacionesService.php:83`) |
| Liquidación final | 30 días por año + 30 días si la fracción supera 6 meses | `LiquidacionService::diasPrestaciones()` (`app/Services/LiquidacionService.php:89`) |

**Resultado medido con los datos actuales** (cada fila en la moneda del trabajador; no comparar importes entre filas):

| Empleado | Garantía acumulada (lit. a+b, con intereses) | Saldo usado para anticipos | Prestaciones en liquidación (lit. c) |
|---|---:|---:|---:|
| 8 | 1.475,07 | 373,86 | 361,80 |
| 9 | 6.053,11 | 763,56 | 909,00 |
| 10 | 11.065,92 | 2.803,64 | 4.069,80 |

La diferencia no es un detalle de redondeo: para el empleado 9, la garantía es **6,7 veces** el saldo que usa el módulo de anticipos y **6,7 veces** la prestación de la liquidación. Para el empleado 10, la garantía es **2,7 veces** la liquidación.

**Implicaciones:**

- El límite de anticipos se calcula sobre una base mucho menor que el saldo mostrado en el estado de cuenta. El usuario ve un saldo y el sistema le permite anticipar otro.
- La liquidación final no compara la garantía con el cálculo de 30 días/año. Si la garantía es mayor, el trabajador podría cobrar de menos; si se paga la garantía por otro canal y además se paga la liquidación, se pagaría dos veces.
- No existe una fuente única de verdad para “prestaciones sociales”.

**Recomendación:** crear un único `PrestacionesCalculator` (o consolidar en un servicio) con una fórmula legal aprobada, casos de prueba firmados y una comparación explícita en la liquidación: “garantía vs. 30 días/año, se paga el mayor, con desglose de capital e intereses”. Bloquear pagos hasta resolverlo.

### 5.2 Intereses capitalizados — **ALTO / requiere validación legal**

`PrestacionesSocialesService::calcularMes()` calcula el interés del mes sobre `saldo_inicial` y lo suma a `saldo_final` (`app/Services/PrestacionesSocialesService.php:208` y siguientes). El mes siguiente usa ese `saldo_final` como `saldo_inicial`, por lo que **el interés se capitaliza mensualmente**.

Datos actuales:

| Empleado | Depósitos acumulados | Intereses acumulados | Saldo final |
|---|---:|---:|---:|
| 8 | 811,50 | 651,51 | 1.475,07 |
| 9 | 1.718,40 | 4.262,13 | 6.053,11 |
| 10 | 6.088,20 | 4.887,28 | 11.065,92 |

En el empleado 9 los intereses son el **70% del saldo**. Si la norma/interpretación aplicable exige interés simple o pago anual separado sin capitalizar, el saldo está sobrestimado. El propio docblock dice “mensual simple” pero luego “capitalizados”, lo que es contradictorio. Validar con asesoría y, si aplica, separar capital e intereses en el estado de cuenta y en la liquidación.

### 5.3 Días adicionales posiblemente duplicados — **ALTO**

- `diasAdicionalesAcumulados()` devuelve `2 × (años − 1)` con tope 30 (total acumulado) (`app/Services/PrestacionesSocialesService.php:103`).
- `calcularMes()`, en cada mes de aniversario, suma **el total acumulado completo** (`diasAdicionalesCont`), no el incremento de 2 días (`app/Services/PrestacionesSocialesService.php:144`).

Ejemplo real del empleado 9: en 2024-01 suma 12,06; en 2025-01 suma 24,16; en 2026-01 suma 36,36. Si el derecho es “2 días por año, acumulativos”, al año 4 el total debería ser 6 días y se habrían depositado 12. Si la interpretación correcta es “monto anual creciente”, entonces el nombre y el docblock del método están mal. En cualquier caso, **código y documentación no coinciden** y el saldo se ve afectado. Requiere definición legal y prueba de regresión.

### 5.4 La liquidación ignora la garantía y los intereses — **CRÍTICO**

`LiquidacionService::calcular()` (`app/Services/LiquidacionService.php:193`) construye la línea “Prestaciones sociales” como `diasPrestaciones × salario integral diario` y agrega “Intereses sobre prestaciones” con importe `0.0` por defecto. No consulta `PrestacionSocial`, no usa `PrestacionesSocialesService::saldoEn()` y no compara la garantía con el cálculo de 30 días/año.

Además, `deducciones()` (`app/Services/LiquidacionService.php:160`) descuenta:

- **Anticipos:** `sum('monto')` de todos los movimientos no anulados, sin filtrar por fecha de egreso ni por saldo pendiente.
- **Intereses:** todos los intereses registrados en movimientos.
- **Préstamos:** `saldo` de los préstamos activos.

Esto puede producir **doble descuento** (el anticipo ya se restó del saldo de prestaciones y se vuelve a restar en la liquidación) y **descuentos de movimientos posteriores al egreso**. También deja fuera los intereses de la garantía que no estén registrados como movimientos.

**Recomendación:** rediseñar la liquidación como un estado de cuenta final: capital garantizado + intereses devengados − anticipos/préstamos con saldo pendiente, comparado con el cálculo de 30 días/año, y pagar el mayor. Cada deducción debe tener trazabilidad y fecha de corte.

### 5.5 Anticipos y préstamos — **ALTO**

- `saldoPrestaciones()` usa 30/32 días por año y **no descuenta anticipos ni préstamos previos**; `maximoAnticipo()` calcula el porcentaje sobre ese saldo bruto (`app/Services/MovimientosPrestacionesService.php:83` y `:110`). Con dos anticipos permitidos por año, se puede superar el límite real acumulado.
- `PrestacionesSocialesService::anticiposDelMes()` resta el `monto` completo de anticipos **y préstamos** en el mes en que se otorgan (`app/Services/PrestacionesSocialesService.php:114`). Un préstamo se resta del saldo de prestaciones, pero cuando se paga (`pagarPrestamo()`, `app/Services/MovimientosPrestacionesService.php:236`) **no se repone** ese saldo; el préstamo queda descontado para siempre.
- Los anticipos no admiten abonos (`MovimientosPrestacionesController@abonar` bloquea anticipos), pero la liquidación descuenta el monto original completo; no hay forma de reflejar devoluciones parciales.
- `MovimientoPrestaciones` no tiene una tabla de abonos ni historial de pagos; solo `saldo` en el préstamo.

**Recomendación:** modelar abonos/pagos como movimientos con fecha, calcular el saldo pendiente como `monto − abonos`, y usar ese saldo tanto para el límite como para la liquidación. Separar “garantía”, “intereses” y “préstamos” en el estado de cuenta.

### 5.6 Liquidación: inactiva al trabajador antes de aprobar y no lo reactiva al anular — **ALTO (confirmado)**

`LiquidacionService::crear()` marca al empleado como `Inactivo` al crear la liquidación, aunque quede en `por_aprobar` (`app/Services/LiquidacionService.php:468`).

Se comprobó en una transacción revertida:

```
emp 8 antes = Activo
liq 38 creada (por_aprobar) → emp = Inactivo
liq 38 anulada → emp = Inactivo
```

`LiquidacionController@anular()` solo cambia el estatus de la liquidación (`app/Http/Controllers/Web/LiquidacionController.php:104`); no reactiva al empleado. En cambio, `destroy()` sí lo reactiva (`:170`). Consecuencias:

- Si se anula una liquidación, el trabajador queda bloqueado para nuevos cálculos y hay que **borrar** la liquidación para reactivarlo.
- Si se crea una liquidación “de prueba” y no se aprueba, el trabajador queda inactivo.
- `aprobar()` permite aprobar una liquidación anulada (solo verifica que no esté ya aprobada).
- `destroy()` permite borrar una liquidación **aprobada**, perdiendo el registro y reactivando al trabajador sin control.

**Recomendación:** mover el cambio a `Inactivo` al momento de **aprobar**; reactivar al anular; impedir borrar aprobadas (usar anulación con motivo y auditoría); agregar una máquina de estados con transiciones válidas.

> **Corregido el 23/10/2026:** el trabajador se marca inactivo al aprobar; se reactiva al anular/eliminar si no queda otra liquidación vigente; se bloquea aprobar una anulada, borrar una aprobada y crear dos liquidaciones vigentes.

### 5.7 Cambio de patrono no mueve el historial — **ALTO (confirmado)**

`EmpleadoController@cambiarPatrono()` actualiza el empleado y luego mueve vacaciones, movimientos, prestaciones, liquidaciones y bonificaciones usando:

```php
->where('patrono_id', $empleado->getOriginal('patrono_id'))
```

Pero `getOriginal()` se consulta **después** del `update()` y Eloquent ya sincronizó el original (`app/Http/Controllers/Web/EmpleadoController.php:106-151`). Se comprobó en una transacción revertida:

```
emp 8 old patrono=4, vacaciones=1
getOriginal después del update = 12
filas de vacaciones movidas = 0 (se esperaba 1)
```

Resultado: el empleado cambia de patrono, pero su historial (vacaciones, prestaciones, liquidaciones, bonificaciones) queda en el patrono anterior. Luego el nuevo patrono no ve el historial y el anterior conserva datos de un empleado que ya no tiene. Rompe la trazabilidad y los cálculos.

**Recomendación:** capturar `$patronoAnterior = $empleado->patrono_id` **antes** del update, mover todos los registros en una transacción, y validar que no existan liquidaciones aprobadas o períodos cerrados que impidan la transferencia. Alternativa más segura: no permitir cambiar de patrono; crear un nuevo empleado con historial propio.

> **Corregido el 23/10/2026:** se captura el patrono anterior antes del update y se mueve vacaciones, movimientos, prestaciones, liquidaciones y bonificaciones dentro de la misma transacción. Se verificó que 1 vacación se mueve correctamente.

### 5.8 Historial salarial insuficiente y edición de salario inconsistente — **ALTO**

- Hay **0 registros** en `cambios_salarios` y **19 de 21** empleados tienen `salario_inicial = NULL`.
- `SueldosService::sueldoDesdeFuente()` usa `salario_inicial ?? salario_base` (`app/Services/SueldosService.php:97`). Si no hay cambios, **todos los meses históricos se generan con el salario base actual**. Al regenerar sueldos o prestaciones, se aplica el salario de hoy a todo el pasado.
- El formulario de edición de empleado permite modificar `salario_base` (`resources/views/empleados/edit.blade.php:79`), pero `EmpleadoController@update()` no crea un `CambioSalario` ni regenera `sueldos`. Dependiendo de si `salario_inicial` es `NULL` o no, el cambio se refleja o se ignora en los cálculos.
- Hay **dos caminos de creación** con reglas duplicadas: el controlador (`EmpleadoController@store`, que asigna `salario_inicial`) y el componente Livewire (`CrearEmpleado`, que no lo asigna). Esto explica que el comportamiento sea distinto según cómo se creó el empleado.
- `regenerarSueldos()` y `regenerar()` de prestaciones **borran y recrean** sin transacción (`app/Services/SueldosService.php:238`, `app/Services/PrestacionesSocialesService.php:319`). Si falla a mitad, se pierde historial.

**Recomendación:** una sola vía de alta/edición; si se edita el salario, registrar automáticamente un cambio con fecha de vigencia y regenerar desde esa fecha dentro de una transacción. Cargar el historial salarial real antes de calcular prestaciones; mientras no exista, el sistema no debería presentar saldos históricos como definitivos.

### 5.9 Recibos duplicados y borrados destructivos — **ALTO**

- `VacacionesController@reciboPdf()` (`:121`) genera un recibo nuevo si no existe. Es una ruta **GET** con efecto de escritura: si el navegador/preview descarga el PDF varias veces, se crean recibos duplicados. Se comprobó llamando dos veces a `generarRecibo()`: la vacación 24 pasó de 1 a 3 recibos.
- `VacacionesController@destroy()` (`:152`) borra la vacación y sus recibos aunque esté pagada/disfrutada; `Vacacion::puedeBorrar()` siempre devuelve `true`.
- `VacacionesController@accionPagar()` (`:185`) marca `estatus_vacacion = 'disfrutada'` al pagar, no al disfrutar. Luego `puedeAnular()` ya no permite anular una vacación pagada. `accionAnular()` (`:175`) puede anular una vacación pagada sin revertir el recibo.
- Una vacación anulada queda con `estatus_calculo = 'no_aprobada'`, pero la restricción única `(patrono, empleado, periodo_desde, periodo_hasta)` sigue existiendo; **no se puede volver a calcular el mismo período sin borrar el registro**. El formulario de creación detecta duplicado y muestra un error, pero no ofrece “recalcular”.
- Las liquidaciones, bonificaciones y movimientos también tienen borrado duro sin auditoría.

**Recomendación:** hacer el PDF idempotente (generar solo al pagar, o `firstOrCreate`); separar “pagada” de “disfrutada”; permitir recalcular un período anulado (o reutilizar el registro); usar borrado lógico y bloquear la eliminación de registros pagados/aprobados; guardar `created_by`, `approved_by`, fechas y motivos.

> **Corregido parcialmente el 23/10/2026:** `generarRecibo()` devuelve el recibo existente; `puedeBorrar()` bloquea pagadas, disfrutadas y con recibo; solo se anulan vacaciones pendientes; se bloqueó el borrado de liquidaciones aprobadas, bonificaciones aprobadas y movimientos no anulados; se bloquearon borrados de patronos con empleados y empleados con historial. Sigue pendiente separar “pagada” de “disfrutada”, permitir recalcular períodos anulados y agregar auditoría.

### 5.10 Feriados: borrar y reinsertar en cada request — **MEDIO/ALTO**

`DiasFeriadosService::asegurarAnio()` llama a `materializarAnio()`, que **elimina todos los feriados del año y los reinserta** (`app/Services/DiasFeriadosService.php:100` y `:129`). Se ejecuta en la primera consulta de calendario de cada request (`CalendarioLaboral::esFeriado()`, `:52`). Consecuencias:

- Escrituras en cada cálculo de vacaciones (no es una consulta de solo lectura).
- Condiciones de carrera entre usuarios: dos requests pueden borrar/insertar a la vez; la tabla tiene `fecha` única, pero el patrón delete+insert no es atómico.
- Se pierden descripciones editadas manualmente de feriados materializados.
- Churn de IDs y de logs.

**Recomendación:** `firstOrCreate`/`updateOrCreate` por fecha, dentro de una transacción, y cachear el set por año. No borrar lo que no cambió.

> **Corregido el 23/10/2026:** se añadió validación de rango, se dejó de reescribir el año en cada lectura y se limpiaron los años inválidos. Ver **Anexo A**.

### 5.11 Utilidades / bonificación de fin de año — **MEDIO**

- `UtilidadesService` calcula una **bonificación fija** (`dias_base × meses/12`) y la rotula como “anticipo de utilidades”; no calcula utilidades sobre beneficios de la empresa. El README lo presenta como “participación en utilidades según LOTTT”. Aclarar el alcance legal y la base.
- `salarioBase()` con `tipo='ultimo'` usa el último sueldo registrado **sin importar el año solicitado** (`app/Services/UtilidadesService.php:97`). Recalcular un año anterior usa el salario actual. Con `tipo='promedio'`, divide entre todas las filas aunque algunas tengan `monto_usd`/`monto_bs` nulos, lo que subestima el promedio (`:125`).
- `calcularBonificacion()` siempre vuelve a `estatus = 'calculado'` (`:156`), así que recalcular una bonificación aprobada la desaprueba y cambia importes; el controlador lo permite.
- La tasa BCV se toma al momento del cálculo, no al cierre del período; recalcular un año pasado cambia los equivalentes.
- El INCES se aplica si hay 5+ empleados activos **en el momento del cálculo**, no en el período.

**Recomendación:** bloquear recálculo de aprobadas (o versionar), usar el salario del período, promediar solo valores no nulos, y congelar la tasa al cierre/emisión.

### 5.12 Vacaciones: otras observaciones — **MEDIO**

- `VacacionesService::calcular()` recibe `$anioDeServicio` pero lo ignora y usa el próximo período pendiente (`:216`). La firma engaña y puede producir resultados inesperados si el usuario cree que elige el año.
- `proximoVacacionesPendiente()` puede devolver un período **futuro** (aún no cumplido) cuando todos los anteriores están registrados (`:108`). La interfaz permite crear la vacación sin validar que el aniversario ya ocurrió.
- `calcularFechasReales()` tiene código muerto: asigna `$reintegro` y lo sobrescribe inmediatamente (`:150-158`).
- Además de los días de vacaciones y el bono, el cálculo paga **días de descanso y feriados** dentro del disfrute (`:245-248` y `:287-320`). Para trabajadores con salario mensual, esto puede duplicar días ya remunerados. Es una decisión de política laboral que debe documentarse y validarse; hoy no está justificada en el código más allá de la cita de artículos.
- Las deducciones IVSS/Paro/FAOV se calculan sobre el recibo de vacaciones. Si la nómina mensual ya las descuenta, podría haber doble descuento. Validar.
- El preview de vacaciones calcula deducciones, pero al guardar no se guardan; el recibo las recalcula con las tasas/flags del momento. El importe mostrado al usuario puede cambiar.
- `dias_descanso` existe en el modelo y la migración, pero **no hay campo en el formulario de empleado**; solo se puede configurar por base de datos. El README lo presenta como configurable.

### 5.13 Salario integral — **MEDIO**

`SalarioIntegralService::diario()` usa `dias_bono_vacacional` con el año en curso +1 (`:82`) y `dias_utilidades` = `patrono->dias_bonificacion` (`:91`). Esto mezcla “días de bonificación de fin de año” con “días de utilidades para la alícuota”; si el patrono configura 30 días de bonificación, la alícuota de utilidades puede no corresponder al valor legal/contable. Validar la base de la alícuota y separar ambos parámetros.

### 5.14 Otras observaciones de cálculo

- `LiquidacionService::fraccionUtilidades()` usa `max(1, round(días/30))` (`:136`): un egreso el 1 de enero otorga al menos 1 mes de utilidades. Revisar.
- `LiquidacionService::actualizarConceptos()` no está en transacción (`:535`) y acepta cualquier `importe` editado; no hay auditoría de quién cambió qué.
- `LiquidacionService::recalcular()` se invoca desde `LiquidacionController@repararSiEsNecesario()` al abrir `show`/`pdf` (`:189`). Aunque hoy ninguna liquidación aprobada cumple la condición, el mecanismo puede **recalcular y alterar una liquidación aprobada** al visualizarla. Debe restringirse a registros no aprobados y ejecutarse explícitamente.
- `dias_indemnizacion` almacena `1` como bandera booleana, no días; se usa con `esDobleIndemnizacion()`. Funciona, pero el nombre es engañoso para reportes/consultas.

---

## 6. Seguridad y cumplimiento

### 6.1 Contraseña por defecto — **corregido el 23/10/2026**

Se verificó con `Hash::check()` que el usuario administrador conservaba la contraseña por defecto. Además, `DefaultAdminSeeder` la restablecía con una clave fija en cada `db:seed`.

**Corrección aplicada:**

- Se rotó la contraseña del administrador existente.
- `DefaultAdminSeeder` ya no contiene una clave fija: usa `ADMIN_PASSWORD` si está definida o genera una aleatoria y la muestra una sola vez.
- El seeder usa `firstOrCreate`, por lo que volver a ejecutar `db:seed` no resetea la contraseña de un administrador existente.
- README y DEPLOY ya no publican la clave.

**Pendiente:** cambio forzado en el primer inicio, 2FA y política de contraseñas.

### 6.2 Secretos y artefactos — **corregido en el árbol actual**

Se retiraron del repositorio y se movieron a `storage/app/private/legacy/` (fuera del webroot):

- `database_scl_backup.sql` (datos de usuarios y empleados).
- `_dbstate.php`, `_err.php`, `_final.php`, `_login_probe.php`, `_patroncheck.php`.
- `stubs_routes.txt` y `%CK%`.

También se agregaron reglas a `.gitignore` para que no vuelvan a versionarse.

**Importante:** los archivos siguen existiendo en el **historial de Git** de commits anteriores. Si el repositorio se compartió o se publicó, hay que rotar las credenciales de la base y, si se requiere, purgar el historial con `git filter-repo`/BFG. `storage/app/private/` está restringido y no debe publicarse.

**Acción:** retirar los scripts y backups del repositorio (`git rm --cached`), agregar `*.sql`, `*.tar.gz`, `_*.php` y `stubs_routes.txt` a `.gitignore`, rotar credenciales de BD si el repo estuvo expuesto, y eliminar los respaldos después de restaurar. No volver a commitear datos de producción.

### 6.3 Crítico: autorización y auditoría

- No hay `app/Policies`, `Gate::` ni middleware de roles.
- `User` no tiene relación con patrono; el contexto es de sesión.
- Cualquier usuario autenticado puede:
  - seleccionar cualquier patrono activo (`EntornoController`);
  - ver/editar la nómina completa;
  - aprobar/anular/borrar liquidaciones, vacaciones y bonificaciones;
  - editar feriados, tasas BCV, tasas de interés y salarios mínimos (globales).
- No hay campos `created_by`, `updated_by`, `approved_by`, `approved_at`, `annulled_by`, `annulled_at` ni historial de estados.
- No hay borrado lógico: borrar un patrono elimina en cascada empleados, vacaciones, prestaciones, liquidaciones y bonificaciones. Un clic puede destruir años de historial.

**Acción:** definir roles (administrador, analista, consulta), asociar usuarios a patronos, implementar Policies, añadir auditoría y soft deletes, y restringir borrados físicos.

### 6.4 Alto: configuración de sesión, correo y HTTPS

- `SESSION_SECURE_COOKIE=false` y el sitio se sirve por HTTP. Si se publica en una red no confiable, las credenciales viajan sin cifrar.
- No hay HSTS ni CSP; las cabeceras actuales son básicas.
- `MAIL_MAILER=log`: la recuperación de contraseña no envía correos; los tokens quedan en el log (si el log es escribible). Configurar SMTP real antes de depender de “olvidé mi contraseña”.
- La cookie `scl_ctx` no está firmada/cifrada; solo contiene patrono/nombre, pero debe ligarse al usuario y firmarse.
- Si se despliega detrás de un proxy inverso, no hay `TrustProxies` configurado; las URLs HTTPS y la IP para el throttling pueden ser incorrectas.

### 6.5 Alto: permisos de despliegue y dependencia de caché

Configuración observada:

- Apache: `APACHE_RUN_USER=www-data`, `APACHE_RUN_GROUP=www-data` (`/etc/apache2/envvars`, `apache2.conf`).
- `.env`: `640 edwards:nogroup` → `www-data` no puede leerlo.
- `storage/` y `bootstrap/cache/`: `775 edwards:nogroup` → `www-data` no puede escribir.
- `storage/logs/laravel.log`: propietario `nobody:nogroup`; desde CLI, `php artisan tinker` falló al intentar escribir el log.

El sitio funciona porque:

- `bootstrap/cache/config.php` ya está generado y es legible;
- las 72 vistas ya están compiladas en `storage/framework/views` y son legibles;
- las sesiones y la caché usan la base de datos, no archivos.

Riesgos:

- Cualquier cambio de vista que requiera recompilación fallará con “Permission denied” (500).
- Cualquier error que intente escribir en el log fallará; se pierde diagnóstico.
- `php artisan config:clear` o `view:clear` puede dejar el sitio sin configuración/vistas y sin poder regenerarlas como `www-data`.
- El arranque depende de una caché que no está versionada ni se regenera de forma reproducible.

**Acción:** ejecutar como root el script incluido:

```bash
sudo bash deploy/fix-permissions.sh /var/www/html/scl
```

El script ajusta `.env` (`root:www-data 640`), `storage/framework`, `storage/logs`, `storage/app/public` y `bootstrap/cache`, y restringe `storage/app/private`. Este entorno no soporta ACL y el agente no tiene root, por lo que **no se pudo ejecutar aquí**; queda listo para el administrador. Las vistas se recompilaron con `php artisan view:cache` como `edwards` para que los cambios actuales sean legibles por el servidor web.

### 6.6 Medio: superficie web y validaciones

- No se encontraron `eval`, `exec`, `shell_exec`, `unserialize` ni SQL crudo vulnerable; las búsquedas usan bindings y la columna de orden está en lista blanca.
- El formulario de importación de Excel valida `mimes:xls,xlsx` pero no limita tamaño; un archivo grande puede consumir memoria/tiempo. Añadir `max:` y límites de filas.
- Las rutas GET con efectos secundarios (PDF de vacaciones, generación de períodos de prestaciones, materialización de feriados) permiten que un simple prefetch/descarga altere datos.
- Los formularios usan CSRF y Livewire; no se detectaron formularios POST sin protección.
- `APP_DEBUG=false` es correcto; el manejador de 419 solo redirige en `/login`; en otros POST el usuario ve la página 419 genérica.

### 6.7 Medio: mensajes de validación sin traducir

No existe directorio `lang/`. Se comprobó en la interfaz:

- Login vacío → muestra `validation.required`.
- Contraseña incorrecta → muestra `auth.failed`.

Es decir, los usuarios ven claves técnicas en lugar de mensajes en español. Solución: `php artisan lang:publish` y crear `lang/es/validation.php`, `auth.php`, `passwords.php`; configurar también el locale de Carbon si se usan fechas localizadas.

---

## 7. Rendimiento y mantenibilidad

### 7.1 Rendimiento

- `PrestacionesController@index()` ejecuta `asegurarPeriodos()` y `saldoEn()` por cada empleado de la página; `asegurarPeriodos()` puede insertar meses y hace consultas por período. En una nómina grande esto se vuelve lento y escribe en un GET.
- `ReportesController@vacacionesPdf()` y `VacacionesService::estadisticasVacaciones()` hacen una consulta de recibo por cada vacación (N+1).
- `DiasFeriadosService::feriadosEnPeriodo()` consulta la descripción de cada feriado dentro de un `map` (N+1) y `asegurarAnio()` reescribe el año.
- `SueldosService::verificarTasasFaltantes()` hace una consulta por mes desde el primer ingreso.
- Generar sueldos/prestaciones de toda la nómina es síncrono en la petición; no hay colas ni jobs.
- Faltan índices para filtros frecuentes: `vacaciones(patrono_id, fecha_calculo)`, `liquidaciones(patrono_id, fecha_liquidacion)`, `movimientos_prestaciones(patrono_id, fecha)`, `bonificaciones_fin_ano(patrono_id, anio)` (este último existe). Revisar con `EXPLAIN`.
- `Parametro::get`, `SalarioMinimo::vigenteEn` y `TasaBcv::tasaEn` se consultan repetidamente sin caché; son buenos candidatos a cache por request.

### 7.2 Mantenibilidad

- Duplicación de reglas de negocio: días de vacaciones, alícuotas, deducciones IVSS/Paro/FAOV y conversión de moneda están repetidos en varios servicios con pequeñas diferencias. Eso es lo que ha producido las tres fórmulas de prestaciones.
- Duplicación de formularios: alta de empleado por controlador (`EmpleadoController@store` + `StoreEmpleadoRequest`) y por Livewire (`CrearEmpleado` con reglas propias); alta de patrono por controlador y por Livewire. Las reglas ya divergen (`salario_inicial`).
- `patronoActual()` repetido en cada controlador; podría resolverse en un middleware/base controller y compartirse.
- Servicios instanciados con `new` como valor por defecto en constructores (`private VacacionesService $service = new VacacionesService`) y `new TasasBcvService` dentro de métodos: dificulta mocking y pruebas.
- Estados y tipos como cadenas (`'Activo'`, `'por_aprobar'`, `'USD'`) en lugar de enums de PHP 8.2; mayor riesgo de errores.
- Modelos con métodos de presentación (`labelEstatus`, `colorEstatus`) que mezclan dominio y UI.
- Uso de `float` para dinero y `decimal(15,2)` en BD; aceptable para este volumen, pero conviene una política de redondeo única y pruebas de casos límite.
- `Pint` reporta 92 archivos con problemas de estilo; no hay CI que impida degradación.
- `composer.json` usa `minimum-stability: dev`; para producción conviene `stable` y versiones fijadas.
- Dependencia `@tailwindcss/vite` 4 instalada pero no usada (el proyecto usa Tailwind 3 + PostCSS); limpiar.
- `public/index.php` hardcodea `/scl` y `str_starts_with($uri, '/scl')`, lo que también coincide con `/sclfoo`; el soporte de subdirectorio debería derivarse de `SCRIPT_NAME`/configuración.
- `app/Http/Request.php` personalizado no está versionado y es frágil; documentarlo o eliminarlo si no es imprescindible.

---

## 8. Matriz de riesgos

| # | Hallazgo | Probabilidad | Impacto | Prioridad |
|---|---|---|---|---|
| 1 | Tres fórmulas de prestaciones y liquidación sin comparar garantía | Alta | Muy alto (pagos incorrectos) | **P0** |
| 2 | Sin roles/permisos; cualquier usuario ve y edita toda la nómina | Alta | Muy alto | **P0** |
| 3 | Contraseña de administrador por defecto activa | Alta | Muy alto | **P0** |
| 4 | Backups/credenciales/scripts destructivos en el repo | Media | Muy alto | **P0** |
| 5 | Permisos de despliegue: `www-data` no lee `.env` ni escribe `storage` | Alta | Alto (caída en el próximo cambio) | **P0** |
| 6 | Intereses capitalizados / días adicionales duplicados | Alta | Alto | **P1** |
| 7 | Anticipos/préstamos mal descontados | Media | Alto | **P1** |
| 8 | Liquidación inactiva antes de aprobar y no reactiva al anular | Alta | Alto | **P1** |
| 9 | Cambio de patrono no mueve historial (confirmado) | Media | Alto | **P1** |
| 10 | Historial salarial vacío y edición de salario inconsistente | Alta | Alto | **P1** |
| 11 | Recibos duplicados / borrados destructivos | Media | Medio-Alto | **P1** |
| 12 | Feriados delete+insert en cada request | Alta | Medio | **P2** |
| 13 | Utilidades: salario histórico, recálculo de aprobadas | Media | Medio | **P2** |
| 14 | 17 avisos de dependencias | Media | Medio | **P2** |
| 15 | Pruebas rojas / sin cobertura de dominio | Alta | Medio-Alto | **P2** |

**Actualización 23/10/2026:** corregidas o mitigadas las filas 3 (clave rotada), 4 (artefactos fuera del árbol; historial pendiente), 8 y 9 (flujos), 11 (parcial), 12 (feriados) y 15 (suite 28/28; cobertura de dominio pendiente). La fila 5 queda lista para ejecutarse como root (`deploy/fix-permissions.sh`). Siguen abiertas 1, 2, 6, 7, 10, 13, 14 y las partes legales de 11.
| 16 | Mensajes `validation.required` / `auth.failed` | Alta | Bajo-Medio | **P3** |
| 17 | N+1 y generación síncrona | Media | Medio | **P3** |
| 18 | Estado de Git no reproducible | Alta | Medio | **P1** |

---

## 9. Plan de acción recomendado

### Fase 0 — Inmediato (hoy/esta semana)

1. **Cambiar la contraseña del administrador** y eliminar la contraseña fija de `DefaultAdminSeeder`; no volver a ejecutar seeders en producción.
2. **Retirar del repositorio** `database_scl_backup.sql`, `_dbstate.php`, `_login_probe.php`, `_err.php`, `_final.php`, `_patroncheck.php`, `stubs_routes.txt`, `%CK%`; agregar patrones a `.gitignore`. Si el repo se compartió, rotar credenciales de BD.
3. **Mover/eliminar** `storage/app/private/backups/*` después de verificar que no se necesitan; no dejarlos en el servidor sin cifrar.
4. **Corregir permisos**: `chown -R www-data:www-data storage bootstrap/cache`; `.env` `root:www-data 640`; verificar que `www-data` pueda escribir el log y compilar una vista nueva. Ejecutar `php artisan optimize` como el usuario correcto.
5. **Commitear** `app/Http/Request.php` junto con `public/index.php`, `deploy/apache-scl.conf`, `DEPLOY.md` y los cambios de configuración; o eliminar la clase personalizada. El estado actual no es reproducible desde Git.
6. **Aislar las pruebas**: descomentar SQLite en `phpunit.xml` o crear una base `scl_test`; añadir `RefreshDatabase`; ejecutar `config:clear`/`cache:clear` en el bootstrap de pruebas. No ejecutar la suite contra producción.
7. **Congelar pagos** de liquidaciones/prestaciones hasta validar las fórmulas con un especialista.

### Fase 1 — Corto plazo (2-4 semanas)

1. **Unificar la fórmula de prestaciones** en un solo servicio con casos de prueba firmados; al liquidar, comparar garantía (capital + intereses) vs. 30 días/año y pagar el mayor, con desglose.
2. **Definir el tratamiento de intereses** (simple/capitalizado) y de los días adicionales; corregir el doble conteo si aplica.
3. **Rediseñar anticipos/préstamos**: abonos con fecha, saldo pendiente real, límite sobre saldo neto, descuento por fecha de corte.
4. **Corregir la máquina de estados de liquidaciones**: inactivo al aprobar, reactivar al anular, bloquear borrado de aprobadas, impedir aprobar anuladas.
5. **Corregir cambio de patrono** capturando el patrono anterior antes del update y moviendo todo el historial en una transacción.
6. **Unificar alta/edición de empleados** en una sola ruta; registrar cambios salariales con fecha de vigencia; no usar el salario actual para meses históricos.
7. **Hacer idempotente el recibo de vacaciones** y separar “pagada” de “disfrutada”; permitir recalcular períodos anulados.
8. **Roles y Policies** mínimos: administrador, operador y consulta; asociar usuarios a patronos.
9. **Auditoría**: `created_by`, `updated_by`, `approved_by`, `approved_at`, `annulled_by`, `annulled_at`, historial de estados; borrado lógico.
10. **Actualizar dependencias** y añadir `composer audit`/`npm audit` a CI.

### Fase 2 — Mediano plazo (1-3 meses)

1. Añadir **pruebas de dominio** con fechas, tasas y salarios fijos: vacaciones (año 1, año 2, tope 30, feriados, descansos), prestaciones (trimestres, aniversarios, intereses), anticipos (límites, abonos), liquidaciones (fracciones, indemnización, deducciones), utilidades y sueldos.
2. **CI** con `php -l`, `pint --test`, `phpunit`, `composer audit` y `npm audit`.
3. **Tests de integración HTTP** para autorización por patrono/rol y para las rutas críticas.
4. **Cachear** feriados, tasas y parámetros; reemplazar N+1 por consultas agregadas y `with()`; mover generaciones masivas a jobs.
5. **Índices** y revisión de `EXPLAIN` para las consultas de listados/reportes.
6. **i18n**: publicar `lang/es` (validación, auth, passwords) y establecer el locale de Carbon.
7. **Endurecer despliegue**: HTTPS, `SESSION_SECURE_COOKIE=true`, HSTS, CSP, `TrustProxies`, SMTP real, límites de subida de Excel.
8. **Limpiar** duplicaciones (servicios, formularios), enums para estados/monedas y política única de redondeo.

---

## 10. Apéndice: evidencia y comandos

### 10.1 Comandos ejecutados

```bash
php -v
php artisan --version
php artisan route:list
php artisan test
php artisan migrate:status
composer validate --no-check-publish
composer audit --locked
composer audit --locked --no-dev
npm audit --omit=dev
vendor/bin/pint --test
php -l (todos los archivos de app/routes/database)
curl (login, selección de patrono y 26 rutas GET)
```

### 10.2 Comprobaciones de cálculos

Se ejecutaron cálculos de solo lectura dentro de una transacción y se revirtieron. Los importes de la tabla 5.1 y 5.2 son el estado actual de la base; no son una recomendación de pago.

### 10.3 Comprobaciones de comportamiento (revertidas)

- Crear una liquidación para el empleado 8 → empleado `Inactivo`; anular → sigue `Inactivo`.
- Cambiar de patrono al empleado 8 → 0 filas de vacaciones movidas (se esperaba 1).
- Llamar dos veces a `generarRecibo()` para la vacación 24 → 3 recibos (se esperaba 1).
- Se verificó que la transacción dejara el empleado 8 en `Activo`, la vacación 24 con 1 recibo y sin liquidación 38.

### 10.4 Lecturas recomendadas del código

| Tema | Archivo |
|---|---|
| Fórmula de prestaciones (garantía) | `app/Services/PrestacionesSocialesService.php` |
| Fórmula de anticipos | `app/Services/MovimientosPrestacionesService.php` |
| Fórmula de liquidación | `app/Services/LiquidacionService.php` |
| Cálculo de vacaciones | `app/Services/VacacionesService.php` |
| Salario integral | `app/Services/SalarioIntegralService.php` |
| Sueldos | `app/Services/SueldosService.php` |
| Utilidades | `app/Services/UtilidadesService.php` |
| Feriados | `app/Services/DiasFeriadosService.php` / `CalendarioLaboral.php` |
| Contexto patrono | `app/Http/Middleware/ValidarContextoPatrono.php` |
| Rutas | `routes/web.php` / `routes/auth.php` |
| Configuración de pruebas | `phpunit.xml` |
| Despliegue | `DEPLOY.md` / `deploy/apache-scl.conf` |

---

## 11. Conclusión

SCL es un proyecto **funcional y bien encaminado en arquitectura**, con una separación clara por módulos y una interfaz completa. El problema no es “que no funcione”, sino **la confiabilidad de los números y del entorno**:

- Hay tres definiciones distintas de prestaciones sociales y la liquidación no concilia la garantía.
- Los intereses y los días adicionales necesitan una decisión legal y una corrección.
- El sistema no tiene roles, auditoría ni protección contra borrados destructivos.
- El despliegue actual depende de una caché precompilada y de permisos inconsistentes.
- Las pruebas no cubren la lógica que más importa y hoy están en rojo por configuración.

La prioridad debe ser **no pagar con cálculos no validados**, **cerrar el acceso y los secretos**, **arreglar el entorno de despliegue** y **construir una suite de pruebas de dominio** antes de seguir agregando funcionalidades. Con esas correcciones, la base actual es aprovechable y el esfuerzo de refactor es acotado.

---

*Informe generado a partir de la revisión directa del código, la base de datos y la aplicación en ejecución. Las referencias a LOTTT son técnicas y deben ser validadas por un profesional del derecho laboral.*

---

## Anexo A — Incidente de feriados con año 8202 (verificado y corregido el 23/10/2026)

**Síntoma:** la pantalla `/feriados` mostraba 14 feriados con año **8202**. Eran los primeros de la lista porque `FeriadosController@index()` ordenaba todas las fechas de forma descendente y limitaba a 20.

**Datos encontrados antes de la limpieza:**

- 14 feriados de 8202 (`01/01`, `01/03`, `02/03`, `15/04`, `16/04`, `19/04`, `01/05`, `24/06`, `05/07`, `24/07`, `12/10`, `24/12`, `25/12`, `31/12`).
- 168 filas en 12 años fuera de 2000–2100 (`1, 2, 6, 8, 10, 20, 62, 82, 202, 620, 820, 8202`), más 14 filas de 2006 que también eran residuales: **182 filas** en total.
- No había vacaciones, sueldos ni prestaciones con año 8202; los datos reales van de 2021 a 2027. Los calendarios inválidos eran residuos de pruebas con años absurdos.

**Causa:** `DiasFeriadosService::asegurarAnio()` materializaba cualquier año recibido desde `CalendarioLaboral::esFeriado()`, sin validar rango. Además, `materializarAnio()` borraba y reinsertaba el año completo en una ruta de lectura, por lo que una simple consulta podía reescribir el calendario.

**Correcciones aplicadas:**

1. `app/Services/DiasFeriadosService.php`
   - Constantes `ANIO_MIN = 2000` y `ANIO_MAX = 2100`.
   - Método `anioValido()`.
   - `materializarAnio()` devuelve `0` y no escribe si el año está fuera de rango.
   - `asegurarAnio()` devuelve `[]` para años inválidos y **solo materializa si el año no existe**, sin reescribir los existentes.
2. `app/Services/CalendarioLaboral.php`
   - `esFeriado()` retorna `false` para años fuera de rango sin tocar la base.
3. `app/Http/Controllers/Web/FeriadosController.php`
   - La lista de feriados materializados se filtra a los últimos 5 años y los próximos 5, en lugar de mostrar cualquier año.
4. `app/Livewire/Vacaciones/CrearVacacion.php`
   - Valida formato `AAAA-MM-DD`, existencia real de la fecha (`checkdate`), rango 2000–2100 y que no sea anterior al ingreso.
5. `app/Livewire/Liquidaciones/CrearLiquidacion.php`
   - Las mismas validaciones para la fecha de egreso.
6. `tests/Unit/DiasFeriadosServiceTest.php`
   - Pruebas de rechazo de años inválidos y aceptación del rango válido.

**Limpieza de datos:**

- Respaldo previo: `storage/app/private/backups/feriados_invalidos_20261023.json` (182 filas, ~41 KB).
- Eliminadas **182 filas** con `fecha < 2021-01-01` o `fecha > 2030-12-31`, dentro de una transacción.
- Quedan **84 feriados** correspondientes a 2022–2027.
- La tabla ya no tiene ninguna fila con año 8202.

**Verificación posterior:**

- `GET /feriados` → `200`, sin ocurrencias de `8202`; la lista comienza en 2027.
- `materializarAnio(8202)` → `0`; `asegurarAnio(8202)` → `[]`; no se crean filas.
- `asegurarAnio(2026)` → 14 fechas y **no reescribe** las filas existentes (IDs y `updated_at` sin cambios).
- `php -l` sin errores en los archivos modificados.
- `DiasFeriadosServiceTest` → 2 pruebas, 11 aserciones, correctas.

**Nota de transparencia:** durante la verificación inicial, antes de aplicar la corrección, se ejecutó `esDiaHabil('8202-01-01')`; eso disparó el comportamiento antiguo y regeneró los calendarios de 2026, 8202 y 0001 (mismos 14 feriados estándar, IDs y timestamps nuevos). Después de la corrección, esa consulta ya no escribe. No se perdió ningún feriado manual porque esos años solo contenían los 14 feriados estándar.

**Nota:** la suite completa se aisló de la configuración de producción y ahora pasa **28/28** (74 aserciones); ver sección 4.2. Los cambios de este anexo y los de aislamiento de pruebas se versionan en los commits correspondientes.

---

## Anexo B — Seguridad y flujos corregidos (23/10/2026)

### Contraseña del administrador

- Se rotó la contraseña del usuario `admin@scl.local`; la clave anterior ya no funciona.
- `DefaultAdminSeeder` ya no contiene una clave fija: usa `ADMIN_PASSWORD` o genera una clave aleatoria y la muestra una sola vez.
- El seeder usa `firstOrCreate`, así que volver a ejecutar `db:seed` no resetea la clave de un administrador existente.
- README y DEPLOY ya no publican la contraseña.

### Artefactos y respaldos

- `database_scl_backup.sql`, los scripts `_*.php`, `stubs_routes.txt` y `%CK%` salieron del árbol actual del repositorio.
- Se movieron a `storage/app/private/legacy/` (fuera del webroot y restringido).
- `.gitignore` evita que vuelvan a versionarse.
- **Pendiente:** siguen en el historial de Git. Si el repositorio se compartió, rotar credenciales de base de datos y evaluar `git filter-repo`/BFG para purgar el historial.

### Permisos de despliegue

- Se agregó `deploy/fix-permissions.sh` para ejecutar como root:
  - `.env` → `root:www-data 640`.
  - `storage/framework`, `storage/logs`, `storage/app/public`, `bootstrap/cache` → `www-data`.
  - `storage/app/private` → acceso restringido.
- No se pudo ejecutar en esta sesión: el agente no tiene root y el filesystem no soporta ACL.
- Las vistas se recompilaron con `php artisan view:cache` como `edwards` para que los cambios actuales funcionen.
- **Acción del administrador:** `sudo bash deploy/fix-permissions.sh /var/www/html/scl` y luego `php artisan optimize`.

### Flujos de liquidación y vacaciones

- La liquidación ya no inactiva al trabajador al crearse; se inactiva al **aprobar**.
- Al anular o eliminar una liquidación, el trabajador se reactiva solo si no queda otra vigente.
- No se puede aprobar una liquidación anulada ni eliminar una aprobada.
- No se pueden crear dos liquidaciones vigentes para el mismo trabajador.
- El cambio de patrono ahora mueve todo el historial (se captura el patrono anterior antes del update).
- `generarRecibo()` es idempotente: no duplica recibos.
- `puedeBorrar()` bloquea vacaciones pagadas, disfrutadas o con recibo; solo se anulan vacaciones pendientes.
- Se bloquearon borrados de patronos con empleados y de empleados con historial.
- Se bloquearon borrados de bonificaciones aprobadas y movimientos no anulados.
- La vista de liquidación muestra una **alerta de conciliación** cuando la garantía acumulada supera el cálculo de 30 días/año. No ajusta importes automáticamente.

### Estado de las fórmulas

No se cambiaron las fórmulas de prestaciones, intereses ni días adicionales: requieren una decisión legal/contable. La alerta de conciliación sirve para detectar el caso más riesgoso antes de aprobar. Siguen pendientes:

- Unificar garantía, saldo de anticipos y liquidación en una sola fórmula.
- Definir si los intereses se capitalizan o se pagan por separado.
- Revisar el doble conteo de días adicionales.
- Integrar la garantía y sus intereses en la liquidación final.
- Validar el pago de descansos/feriados en vacaciones y las deducciones de ley.

### Verificación

- `php artisan test` → **28 passed (74 assertions)**.
- Login con la clave nueva → correcto; con la anterior → rechazado.
- Transferencia de patrono → 1 vacación movida (antes 0).
- Flujo de liquidación → activo al crear, inactivo al aprobar, activo al anular.
- Recibo → dos llamadas devuelven el mismo recibo (1 registro).
