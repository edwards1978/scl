# Análisis técnico del proyecto SCL

**Sistema:** SCL — Sistema de Cálculos Laborales (LOTTT)
**Ruta analizada:** `/var/www/html/scl`
**Fecha del análisis:** 27/10/2026
**Alcance:** arquitectura, módulos, lógica de cálculo, base de datos, seguridad, despliegue, pruebas y mantenibilidad.
**Naturaleza:** auditoría técnica. No sustituye la validación de un abogado laboral ni de un contador público; las fórmulas legales deben firmarse con casos de prueba antes de usarse para pagos reales.

> **Método.** No se modificó información de producción. Las comprobaciones de escritura se hicieron con la suite (`DatabaseTransactions`) y con lecturas controladas. La suite se ejecutó con `DB_PREFIX=test_`; la compilación de vistas se validó con `php artisan view:cache`.
>
> Los **anexos A, B y C** documentan incidentes y decisiones del 23/10/2026. Se conservan como historial. Las cifras de pruebas de esos anexos (28/28) son históricas; el estado actual es **90/90**.

---

## 1. Resumen ejecutivo

SCL es una aplicación **Laravel 12 + Livewire 4 + Blade/Tailwind + MySQL/MariaDB** para administrar patronos, empleados, sueldos, vacaciones, prestaciones sociales, anticipos/préstamos, pagos de intereses, liquidaciones, utilidades, tasas BCV, tasas de interés, feriados y salarios mínimos, con generación de PDF y exportación a hoja de cálculo.

**Balance general:** el proyecto está **mejor que en el informe del 23/10/2026**. La suite creció de 28 a **90 pruebas** y pasa completa; se incorporó el módulo de pagos de intereses del Art. 143 LOTTT, las proyecciones de prestaciones, la capitalización autorizada por escrito, la comparación garantía vs. 30 días/año en la liquidación y la regeneración de períodos al aprobar/anular liquidaciones. También se corrigieron los permisos de despliegue y se versionaron archivos que antes no estaban en Git.

**Lo que está bien:**

- La aplicación **funciona**: 150 pruebas, 632 aserciones, ~27 s; las vistas Blade compilan sin errores y el pipeline de GitHub Actions está en verde.
- Separación razonable en capas: 17 controladores web, 4 componentes Livewire, 12 servicios de dominio, 21 modelos, 43 migraciones y 131 rutas (contando las de vendor).
- Motor de prestaciones documentado y cubierto con pruebas de dominio: trimestres, aniversarios, fracción de egreso, días adicionales, intereses, capitalización, anticipos, proyecciones y pagos de intereses.
- Aislamiento por patrono en la mayoría de consultas mediante `patrono_id` de sesión; se validan cédula/RIF, duplicados y períodos.
- Buenas prácticas presentes: `APP_DEBUG=false`, registro público deshabilitado, throttling de login, CSRF, regeneración de sesión, `Hash` de contraseñas y cabeceras de seguridad en Apache.
- Despliegue con permisos coherentes: `www-data` puede leer `.env` y escribir en `storage/` y `bootstrap/cache/`.
- Documentación de despliegue (`DEPLOY.md`) y scripts en `deploy/`.

**Riesgos principales (en orden de impacto):**

1. **Permisos por acción y borrado lógico pendientes.** Roles, Policies, aislamiento por patrono y auditoría ya están implementados; falta revisar permisos por acción específica y definir borrado lógico. **P2.**
2. **Historial salarial — corregido el 29/10/2026.** Se completó `salario_inicial` en los 37 trabajadores y se registró el cambio de ingreso en `cambios_salarios` (37 registros). Alta, edición de ficha, variaciones e importación registran ahora el cambio con fecha de vigencia y recalculan sueldos y prestaciones. Queda cargar el historial real de aumentos anteriores, que no consta en el sistema. **P2.**
3. **Fórmulas legales aún no firmadas.** La decisión de abonar el acumulado completo de días adicionales en cada aniversario, el tratamiento de los intereses cuando gana el literal c y la deducción de intereses de préstamos en la liquidación requieren validación legal/contable. **P1.**
4. **La suite comparte la base de datos de producción** (`scl`) con prefijo `test_` y 28 tablas de prueba. El aislamiento funciona, pero un error de configuración podría tocar datos reales. Se recomienda una base `scl_test` separada. **P1.**
5. **Trabajo sin commitear.** Hay 25 archivos modificados (+1.722/−980) y varios grupos de archivos nuevos (módulo de intereses, migración, vistas, pruebas, tooling) fuera de Git. El estado actual no es reproducible desde un commit. **P1.**
6. **Dependencias con avisos de seguridad.** `composer audit --locked --no-dev` reporta **19 avisos en 5 paquetes**, incluido un XSS de Livewire 4.3.3. **P2.**
7. **Despliegue sobre HTTP.** `SESSION_SECURE_COOKIE=false`, `APP_URL=http://localhost/scl`, `MAIL_MAILER=log`, sin CSP/HSTS ni `TrustProxies`. Aceptable en red interna, insuficiente si se publica. **P2.**
8. **Anomalía de datos.** El empleado 10 tiene una liquidación aprobada y sigue `Activo`; puede generar nuevos cálculos y otra liquidación. **P2.**
9. **Mensajes sin traducir.** No existe `lang/`; con locale `es` la interfaz sigue mostrando claves como `validation.required` y `auth.failed`. **P3.**
10. **Rendimiento y mantenibilidad.** N+1 en listados/reportes, generación síncrona de períodos, `PrestacionesSocialesService` de 1.251 líneas, 77 archivos con problemas de estilo y ausencia de CI. **P3.**

---

## 2. Ficha técnica

| Elemento | Valor |
|---|---|
| Framework | Laravel 12.64.0 |
| PHP | 8.3.6 |
| Frontend | Blade, Tailwind CSS 3, Alpine.js, Livewire 4.3.3 |
| Base de datos | MariaDB/MySQL (`scl`) |
| PDF | `barryvdh/laravel-dompdf` 3.1.2 |
| Excel | `phpoffice/phpspreadsheet` 5.9.0 |
| Rutas | 117 totales; 105 propias (sin vendor) |
| Migraciones | 40 |
| Modelos | 20 |
| Servicios de dominio | 11 |
| Controladores web | 16 |
| Componentes Livewire | 4 |
| Vistas Blade | 75 |
| Líneas PHP en `app/` | ~10.400 |
| Líneas Blade | ~5.900 |
| Líneas de pruebas | ~2.200 |
| Pruebas | 90 (332 aserciones) |
| Estado de Git | 34 commits, rama `main`, árbol de trabajo con cambios sin commitear |

### Datos actuales en la base

| Tabla | Registros |
|---|---:|
| `patronos` | 10 |
| `empleados` | 37 |
| `users` | 3 |
| `vacaciones` | 32 |
| `liquidaciones` | 8 (8 aprobadas) |
| `prestaciones_sociales` | 733 (729 reales + 4 proyecciones) |
| `movimientos_prestaciones` | 2 |
| `pagos_interes_prestaciones` | 2 |
| `bonificaciones_fin_ano` | 5 |
| `sueldos` / `cambios_salarios` | 1.405 / **37** |
| `feriados` | 84 |
| `tasas_bcv` / `tasas_interes_prestaciones` | 915 / 350 |
| `salarios_minimos` | 10 |

**Anomalías de datos:**

- Hay **8 liquidaciones aprobadas** y **7 empleados inactivos**. El empleado **10** conserva una liquidación aprobada (egreso 07/08/2026) pero sigue `Activo`. Puede ser una reincorporación no modelada; debe revisarse antes de permitir nuevos cálculos.
- **Historial salarial completo desde el 29/10/2026:** los 37 trabajadores tienen `salario_inicial` y su cambio de ingreso registrado. El historial anterior a esa fecha es una reconstrucción con el salario actual, no la evolución real.
- Las liquidaciones aprobadas **antes** del motor actual no se reescribieron. Hay **8 aprobadas** en total; la más reciente (empleado 32, 25/10/2026) es posterior al motor actual y las anteriores conservan la fórmula con la que se aprobaron.

---

## 3. Arquitectura y funcionamiento

### 3.1 Capas

```
routes/web.php
  └── Controladores Web (16)  ──┐
  └── Componentes Livewire (4) ─┼── Servicios de dominio (11) ── Modelos Eloquent (20)
  └── Form Requests (7)        ─┘
                                  └── Vistas Blade + PDF (Dompdf)
```

**Servicios principales:**

- `VacacionesService`: días, fechas reales, feriados/descansos, deducciones y recibo.
- `PrestacionesSocialesService`: garantía trimestral, días adicionales, intereses, capitalización, pagos, anticipos, proyecciones y estado de cuenta.
- `MovimientosPrestacionesService`: anticipos/préstamos, límites, saldos y anulación.
- `LiquidacionService`: liquidación final, fracciones, indemnización, comparación garantía vs. literal c y deducciones.
- `UtilidadesService`: bonificación de fin de año.
- `SueldosService`: nómina mensual, variaciones y regeneración.
- `SalarioIntegralService`: salario diario integral y alícuotas.
- `TasasBcvService` / `TasasInteresService`: importación y consulta de tasas.
- `CalendarioLaboral` / `DiasFeriadosService`: días hábiles y feriados.

### 3.2 Flujo de contexto (multi-patrono)

1. El usuario inicia sesión (`auth`).
2. `ValidarContextoPatrono` exige `session('patrono_id')`; si no existe, intenta restaurarlo desde la cookie `scl_ctx` y, si falla, redirige a `entorno.seleccionar`.
3. `EntornoController@seleccionar` y `guardar` permiten escoger **cualquier patrono activo** y lo guardan en sesión/cookie.
4. Los controladores leen `session('patrono_id')` y filtran por `patrono_id` en sus consultas.

**Fortaleza:** el filtrado por patrono está aplicado en la mayoría de controladores y componentes Livewire; no se encontraron consultas evidentes sin ese filtro en los módulos de negocio.

**Debilidad estructural (resuelta):** el contexto ya está ligado al usuario mediante la tabla pivote `patrono_user`, roles y políticas. `contexto.patrono` valida la cookie cifrada contra el usuario y `nomina.gestionar` bloquea las escrituras del rol Consulta.

### 3.3 Módulos funcionales

- **Entidades:** patronos, empleados, sueldos.
- **Cálculos:** prestaciones, anticipos/préstamos, pagos de intereses, vacaciones, liquidaciones, utilidades.
- **Configuración:** tasas BCV, tasas de interés, salarios mínimos, feriados, configuración del patrono.
- **Reportes:** reporte de vacaciones en pantalla y PDF; recibos PDF de vacaciones, prestaciones, liquidaciones, utilidades, movimientos y comprobantes de pago.
- **Proyecciones:** generación explícita de hasta 12 meses hacia adelante, usada como base operativa para anticipos e intereses con fecha futura.

---

## 4. Estado verificado

### 4.1 Pruebas automatizadas

```
php artisan test --compact
Tests:    143 passed (573 assertions)
Duration: 25.0 s
```

Distribución:

| Archivo | Pruebas |
|---|---:|
| `tests/Feature/Prestaciones/PrestacionesSocialesTest.php` | 37 |
| `tests/Feature/Prestaciones/InteresesPrestacionesTest.php` | 21 |
| `tests/Feature/ProfileTest.php` | 5 |
| `tests/Feature/FiltrosDeListadosTest.php` | 4 |
| `tests/Feature/Auth/*` | 15 |
| `tests/Unit/DiasFeriadosServiceTest.php` | 2 |
| `tests/Feature/ExampleTest.php` / `tests/Unit/ExampleTest.php` | 3 |
| `tests/Feature/Seguridad/*` | 23 |
| **Total** | **143** |

**Fortaleza:** la lógica de prestaciones e intereses ya tiene cobertura de dominio con fechas, tasas y salarios controlados: cierres de trimestre, ingresos a mitad de mes, 29 de febrero, 31 de mes, fracciones de egreso, días adicionales, topes, proyecciones y pagos.

**Limitación:** no hay pruebas de vacaciones, liquidaciones, utilidades ni sueldos; la cobertura se concentra en prestaciones, intereses, filtros y ahora autorización. Ya existen pruebas HTTP de acceso por patrono y por rol (`tests/Feature/Seguridad`).

### 4.2 Aislamiento de pruebas

`phpunit.xml` apunta las cachés a `storage/framework/testing/*` y usa `DB_PREFIX=test_`. `tests/bootstrap.php`:

- exige `APP_ENV=testing`;
- rechaza ejecutar si la base es `scl` sin prefijo `test_`;
- migra el esquema de pruebas de forma idempotente;
- restaura los manejadores de error para PHPUnit.

Actualmente existen **28 tablas `test_*`** en la misma base `scl`. `TestCase` usa `DatabaseTransactions`, por lo que cada prueba revierte sus escrituras.

**Riesgo:** aunque el aislamiento funciona, se comparte la misma instancia y credenciales de producción. Un cambio en `phpunit.xml`, un `migrate:fresh` accidental o una consulta con nombre de tabla explícito podría afectar datos reales. La solución recomendada es una base `scl_test` separada y `DB_PREFIX` vacío.

### 4.3 Calidad y dependencias

| Comprobación | Resultado |
|---|---|
| `php -l` en `app`, `routes`, `database` | Sin errores de sintaxis |
| `php artisan view:cache` | 101 vistas compiladas correctamente |
| `composer validate --no-check-publish` | Correcto |
| `vendor/bin/pint --test` | **77 archivos** con problemas de estilo |
| `composer audit --locked --no-dev` | **19 avisos en 5 paquetes** |
| `npm audit --omit=dev` | 0 vulnerabilidades |
| CI/CD | No existe `.github`, GitLab CI ni equivalente |

**Avisos de dependencias (no-dev):**

| Paquete | Avisos |
|---|---:|
| `league/commonmark` | 12 |
| `laravel/framework` | 3 |
| `livewire/livewire` | 2 |
| `league/flysystem` | 1 |
| `symfony/http-foundation` | 1 |

Destacan:

- `livewire/livewire` — CVE-2026-81887, XSS DOM-based. La versión instalada (4.3.3) está dentro del rango afectado.
- `league/commonmark` — varios DoS y bypass de XSS.
- `symfony/http-foundation` — ReDoS en `HeaderUtils::split()`.
- `laravel/framework` — avisos recientes; `APP_DEBUG=false` reduce el riesgo de la página de debug.

No se evaluó la explotabilidad concreta en esta aplicación. Se recomienda actualizar dependencias, volver a ejecutar `composer audit` y añadir la comprobación al CI.

### 4.4 Despliegue y permisos

Configuración observada:

- `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=http://localhost/scl`.
- `.env` → `640 edwards:www-data`; `storage/` y `bootstrap/cache/` → `775 edwards:www-data`; `storage/logs/laravel.log` → `www-data:www-data`.
- `www-data` puede leer `.env` y escribir en `storage/` y `bootstrap/cache/`. **El hallazgo P0 de permisos del informe anterior está corregido.**
- No hay `bootstrap/cache/config.php`; la aplicación lee `.env` y las cachés de paquetes/servicios/eventos.
- Las vistas compilan correctamente como el usuario actual y son legibles por el servidor web.
- Apache publica la aplicación en `/scl` mediante `Alias`, con `DocumentRoot` en `public/`.

**Pendiente:**

- `SESSION_SECURE_COOKIE=false` y sitio por HTTP.
- `MAIL_MAILER=log`: la recuperación de contraseña no envía correos; los tokens quedan en el log.
- No hay CSP, HSTS ni `TrustProxies` configurado.
- `public/index.php` fuerza el subdirectorio con `str_starts_with($uri, '/scl')`, que también coincide con `/sclfoo`; el soporte de subdirectorio debería derivarse de `SCRIPT_NAME`/configuración.
- Quedan artefactos de sesiones de depuración en `storage/app/p32.html` y `storage/app/p33.html` (volcados HTML), además de `test.php` y los Excel de ejemplo en la raíz. No están en el webroot, pero conviene retirarlos.

### 4.5 Estado del repositorio

- **34 commits**, rama `main`, remoto `github.com/edwards1978/scl`.
- **Árbol de trabajo sucio:** 25 archivos modificados (+1.722/−980) y varios grupos sin versionar:
  - `app/Http/Controllers/Web/InteresesPrestacionesController.php`
  - `database/migrations/2026_10_27_010619_add_fecha_comprobante_to_movimientos_y_pagos_prestaciones.php`
  - `resources/views/intereses-prestaciones/`
  - `tests/Feature/FiltrosDeListadosTest.php`
  - `tests/Feature/Prestaciones/`
  - `AGENTS.md`, `boost.json`, `opencode.json`, `.agents/`
- **Corregido respecto al informe anterior:** `app/Http/Request.php`, `public/index.php` y `deploy/` ya están versionados.
- **Pendiente:** commitear el módulo de intereses, la migración, las pruebas y el tooling; o descartarlos explícitamente. El estado actual no se puede reconstruir desde Git.

### 4.6 Herramientas de desarrollo

`laravel/boost` está instalado (`v2.10.3`) pero sus comandos no se registran en este entorno porque `BoostServiceProvider::shouldRun()` exige `APP_ENV=local` o `APP_DEBUG=true`. Con `APP_ENV=production` y `APP_DEBUG=false`, `php artisan boost:mcp` no existe. Es un detalle de tooling, no de la aplicación; si se quiere usar Boost/OpenCode en este servidor, ejecutar con `--env=local` o una configuración equivalente.

---

## 5. Motor de prestaciones e intereses (estado actual)

### 5.1 Reglas implementadas

El docblock de `PrestacionesSocialesService` documenta las reglas vigentes:

- **Depósito trimestral:** 15 días de salario integral al cierre de cada trimestre. Si la relación termina antes de completar el trimestre, se abona la fracción de 15 días.
- **Días adicionales:** al cierre del período que completa cada año se abonan 2, 4, 6… días (2 por cada año desde el segundo), con tope de 30 por abono. Desde el año 16 se abonan 30 días cada año.
- **Interés:** simple mensual sobre el **capital** de la garantía, a la tasa promedio entre activa y pasiva del BCV.
- **Solo contabilidad:** se calculan intereses cuando la garantía está abonada en la contabilidad del patrono. En fideicomiso o fondo los calcula la entidad.
- **Capitalización:** el interés no pagado se capitaliza en el aniversario **solo** si el trabajador tiene una autorización escrita registrada (`capitaliza_intereses` + `capitalizacion_autorizada_desde`).
- **Anticipos:** reducen el capital.
- **Préstamos:** son deuda separada y **no** reducen la garantía.
- **Pagos de intereses:** se registran en `pagos_interes_prestaciones`; cubren primero el interés pendiente y el exceso reduce capital.
- **Períodos:** meses de servicio anclados al día de ingreso. El período 1 va del ingreso al día anterior del mismo día del mes siguiente; un ingreso el 11/05 cierra su primer período el 10/06.

### 5.2 Comparación en la liquidación

`LiquidacionService::calcular()`:

1. Calcula el literal c: `diasPrestaciones × salario integral diario`.
2. Llama a `garantiaBrutaAlEgreso()`, que calcula en memoria el período de egreso con corte en esa fecha e incluye la fracción del trimestre.
3. Compara garantía bruta vs. literal c y paga **el mayor**.
4. Muestra la diferencia y el origen (`garantia` o `literal_c`).
5. Descuenta anticipos una sola vez y los préstamos como deuda separada.

Esto corrige el hallazgo crítico del informe anterior (liquidación que ignoraba la garantía). La aprobación de la liquidación regenera los períodos con corte en el egreso, y la anulación/eliminación los restablece.

### 5.3 Proyecciones

- Se generan explícitamente con `proyectarHasta()` / `proyectarActivos()`.
- Horizonte máximo: **12 meses** hacia adelante, solo meses futuros y solo trabajadores activos.
- Se marcan con `es_proyeccion = true`.
- Se usan como base operativa para anticipos e intereses con fecha futura.
- Un cálculo real del mes vencido reemplaza la proyección; un movimiento futuro reconstruye la proyección hasta su mes.
- Las consultas GET **no** generan períodos; la generación ocurre en acciones POST explícitas o al registrar un movimiento.

**Riesgo:** la proyección usa la última tasa BCV y el último salario conocidos. Es una estimación operativa, no un devengo contable definitivo. La interfaz debe mantenerla claramente etiquetada y no debe usarse como saldo final para pagos sin revisión.

### 5.4 Hallazgos residuales del motor

1. **Días adicionales acumulativos.** `diasAdicionalesDelAniversario()` devuelve 2, 4, 6… y `calcularMes()` abona ese acumulado completo en cada aniversario. Es la decisión de negocio documentada el 23/10/2026 y está cubierta por pruebas, pero sigue siendo una interpretación legal que debe firmarse. Si la norma se interpreta como “2 días adicionales por año, acumulativos al total”, el abono repetido del acumulado sobreestima la garantía.
2. **Intereses vs. literal c.** La línea “Intereses sobre prestaciones” se guarda en `0.0` porque el interés ya está incluido en la garantía comparada. Si gana el literal c, el sistema no paga intereses por separado. Hay que definir si el Art. 143 es un derecho independiente que debe pagarse siempre o si queda absorbido por el mayor.
3. **Deducción de intereses de préstamos.** `LiquidacionService::deducciones()` suma los registros de `movimiento_prestaciones_intereses` como “Pago de intereses”. El flujo `registrarInteres()` se describe como **pago** de intereses de un préstamo/anticipo. Si esos intereses ya fueron pagados por el trabajador, volver a descontarlos en la liquidación sería un doble cobro; si representan intereses devengados no pagados, el modelo no distingue su estado. Requiere revisión funcional y legal.
4. **Anticipos sin abonos.** Los anticipos no admiten abonos parciales; se descuentan por el monto original completo. Los préstamos sí tienen `saldo`, pero no un historial de pagos independiente.
5. **Conversión de moneda.** Las conversiones usan la tasa de la fecha del movimiento o del período; en reportes históricos, recalcular con tasas distintas puede cambiar equivalentes. Hay que fijar una política única.
6. **Redondeo.** Se usa `float` y `round(..., 2)` en puntos distintos; no hay una política central de redondeo para casos límite.

---

## 6. Liquidación final

### 6.1 Flujo

- La liquidación se crea en `por_aprobar`; el trabajador **no** se inactiva al crearla.
- Al **aprobar**, se marca `Inactivo` y se regeneran los períodos con corte en el egreso.
- Al **anular** o **eliminar**, se reactiva solo si no queda otra liquidación vigente y se regeneran los períodos.
- No se puede aprobar una liquidación anulada ni eliminar una aprobada.
- No se pueden crear dos liquidaciones vigentes para el mismo trabajador.
- La vista muestra una **alerta de conciliación** cuando la garantía supera el literal c; ya no se recalcula al abrir `show`/`pdf` (`repararSiEsNecesario` fue retirado).

### 6.2 Deducciones

La liquidación descuenta:

- anticipos no anulados hasta la fecha de egreso;
- saldo de préstamos activos;
- intereses registrados en `movimiento_prestaciones_intereses` (ver hallazgo 5.4.3);
- deducciones de ley sobre la base remunerativa (vacaciones + bono + utilidades fraccionadas): IVSS 4 %, Paro 0,5 %, FAOV 1 %, INCES 0,5 %, según los flags del patrono.

**Observaciones:**

- `deducciones()` devuelve `de_ley => 0.0`; el total real se calcula después con `deduccionesDeLey()`. El campo es informativo y no se usa para el neto.
- `fraccionUtilidades()` usa `max(1, round(días/30))`: un egreso el 1 de enero otorga al menos 1 mes de utilidades. Revisar.
- `dias_indemnizacion` almacena `1` como bandera booleana, no días; funciona con `esDobleIndemnizacion()`, pero el nombre es engañoso para reportes.
- Los campos de auditoría (`created_by`, `updated_by`, `approved_by`, `annulled_by` y sus fechas) y el historial de estados están implementados desde el 29/10/2026.

---

## 7. Vacaciones, utilidades y sueldos

### 7.1 Vacaciones

**Estado actual:**

- `generarRecibo()` es idempotente: devuelve el recibo existente.
- `puedeBorrar()` bloquea vacaciones pagadas, disfrutadas o con recibo; solo se eliminan períodos pendientes sin recibo.
- `puedeAnular()` solo permite anular vacaciones pendientes; al anular se eliminan los recibos y se marca `no_aprobada`.
- Un período anulado se puede eliminar y volver a crear; no hay acción explícita de “recalcular”.

**Pendiente:**

- `accionPagar()` marca `estatus_vacacion = 'disfrutada'` al pagar; el estado `pagada` prácticamente no se usa. Separar “pagada” de “disfrutada”.
- `reciboPdf()` sigue siendo un GET con efecto de escritura: si no existe recibo, lo crea. Es idempotente, pero un prefetch puede generar el primero.
- `VacacionesService::calcular()` recibe `$anioDeServicio` pero lo ignora y usa el próximo período pendiente.
- `proximoVacacionesPendiente()` puede devolver un período futuro cuando todos los anteriores están registrados.
- El cálculo paga días de descanso y feriados dentro del disfrute; para salarios mensuales puede duplicar días ya remunerados. Validar la política.
- Las deducciones IVSS/Paro/FAOV se calculan sobre el recibo de vacaciones; si la nómina mensual ya las descuenta, podría haber doble descuento.
- El preview calcula deducciones, pero al guardar no se persisten; el recibo las recalcula con las tasas/flags del momento.
- `dias_descanso` no tiene campo en el formulario de empleado; se configura por Livewire/DB.

### 7.2 Utilidades / bonificación de fin de año

- `UtilidadesService` calcula una **bonificación fija** (`dias_base × meses/12`) y la rotula como “anticipo de utilidades”; no calcula utilidades sobre beneficios de la empresa. El README lo presenta como “participación en utilidades según LOTTT”. Aclarar el alcance legal y la base.
- `salarioBase('ultimo')` usa el último sueldo registrado (`sueldos()` ordena por período descendente) **sin filtrar por el año solicitado**. Recalcular un año anterior usa el salario más reciente. Con `promedio`, divide entre todas las filas del año aunque algunas tengan `monto_usd`/`monto_bs` nulos, lo que subestima el promedio.
- `calcularBonificacion()` siempre vuelve a `estatus = 'calculado'`; recalcular una bonificación aprobada la desaprueba y cambia importes. El controlador lo permite.
- La tasa BCV se toma al momento del cálculo, no al cierre del período; recalcular un año pasado cambia los equivalentes.
- El INCES se aplica si hay 5+ empleados activos **en el momento del cálculo**, no en el período.

### 7.3 Historial salarial

**Implementado el 29/10/2026.**

- `SueldosService::registrarCambio()` centraliza el registro: crea o actualiza el `CambioSalario`, sincroniza la ficha y regenera los sueldos desde el mes de vigencia. `registrarIngreso()` deja el cambio de ingreso con `fecha_ingreso`.
- `EmpleadoController@store/update`, el componente Livewire `CrearEmpleado`, la importación `.ods` y el módulo de variaciones usan esa vía. La ficha permite indicar la **fecha de vigencia** del nuevo salario; si no se indica, se usa la fecha actual.
- Al cambiar el salario se recalculan también las prestaciones desde ese mes cuando el trabajador ya tiene períodos registrados.
- El comando `php artisan salarios:completar-historial [--aplicar]` completó los datos heredados: **37 cambios de ingreso** y **0 trabajadores sin `salario_inicial`**.
- Al crear un trabajador no se materializan años de sueldos: se generan de forma perezosa al abrir el módulo. En cambios de sueldos existentes sí se regenera el histórico.

**Pendiente:** cargar los aumentos reales anteriores al 29/10/2026. La reconstrucción actual usa el salario vigente como sueldo de ingreso, por lo que los meses previos a un aumento no registrado quedan sobreestimados.

### 7.4 Salario integral

`SalarioIntegralService::diario()` usa:

- `dias_bono_vacacional` del año en curso +1;
- `dias_utilidades = patrono->dias_bonificacion`.

Esto mezcla “días de bonificación de fin de año” con “días de utilidades para la alícuota”. Si el patrono configura 0 o 30 días de bonificación, la alícuota de utilidades puede no corresponder al valor legal/contable. Validar la base y separar ambos parámetros.

---

## 8. Seguridad y cumplimiento

### 8.1 Autorización y auditoría — **P0 resuelto**

**Implementado el 28/10/2026:**

- Roles en `users.rol`: `administrador`, `analista` y `consulta` (`App\Enums\RolUsuario`).
- Tabla pivote `patrono_user`: cada usuario no administrador solo accede a los patronos asignados.
- Policies `PatronoPolicy` y `EmpleadoPolicy`; Gates `administrar` y `gestionar`.
- Middleware `contexto.patrono` valida el patrono de la sesión/cookie contra el usuario; `nomina.gestionar` bloquea las escrituras del rol Consulta.
- Rutas globales (patronos, feriados, tasas BCV, tasas de interés, salarios mínimos y usuarios) restringidas a administradores.
- Cookie `scl_ctx` cifrada y autenticada por `EncryptCookies`; solo contiene `patrono_id`.
- Pantalla `/usuarios` para crear/editar usuarios, asignar rol y varios patronos; protección del último administrador y del propio usuario.
- Los usuarios existentes conservan su acceso al migrar como administradores.

**Auditoría implementada el 29/10/2026:**

- Trait `App\Models\Concerns\RegistraAuditoria`: completa `created_by` y `updated_by`, coloca sellos de aprobación/anulación y escribe el historial.
- Columnas en patronos, empleados, cambios salariales, pagos de intereses y catálogos editables: `created_by`, `updated_by`.
- Sellos en liquidaciones y vacaciones: `approved_by`, `approved_at`, `annulled_by`, `annulled_at`; en bonificaciones: `approved_by`, `approved_at`; en movimientos: `annulled_by`, `annulled_at`.
- Tabla polimórfica `historial_estados` (patrono, entidad, campo, valor anterior/nuevo, usuario y fecha) con índice por entidad.
- Los cambios de estado que reinician una aprobación (por ejemplo, bonificación recalculada a `calculado`) limpian los sellos y las fechas de emisión.
- La aprobación masiva de utilidades actualiza modelo por modelo para no perder los sellos ni el historial.
- Las vistas de detalle de liquidaciones, vacaciones, bonificaciones y movimientos muestran la trazabilidad y el historial de estados.

**Pendiente:** revisión de permisos por acción específica y borrado lógico.

### 8.2 Controles presentes

- Registro público deshabilitado (`REGISTRATION_ENABLED=false` y condicional en `routes/auth.php`).
- Login con throttling (`RateLimiter` de Breeze), CSRF, regeneración de sesión y `Hash` de contraseñas.
- `APP_DEBUG=false`; errores no se muestran al usuario.
- Cabeceras en Apache: `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`.
- Se bloquean borrados de patronos con empleados, empleados con historial, liquidaciones aprobadas, bonificaciones aprobadas, movimientos no anulados y vacaciones pagadas/disfrutadas/con recibo.
- No se encontraron `eval`, `exec`, `shell_exec`, `unserialize` ni SQL crudo vulnerable; las búsquedas usan bindings y la columna de orden está en lista blanca.

### 8.3 Configuración y transporte — **P2**

- `SESSION_SECURE_COOKIE=false` y sitio por HTTP. Si se publica en una red no confiable, las credenciales viajan sin cifrar.
- No hay HSTS ni CSP.
- `MAIL_MAILER=log`: la recuperación de contraseña no envía correos; los tokens quedan en el log.
- No hay `TrustProxies`; detrás de un proxy inverso las URLs HTTPS y la IP para throttling pueden ser incorrectas.
- La importación de Excel valida `mimes:xls,xlsx` pero no limita tamaño ni filas.
- Rutas GET con efectos secundarios: el PDF de vacaciones crea el recibo si no existe; la generación de períodos y feriados ya no ocurre en consultas de solo lectura.
- El manejador de 419 solo redirige en `/login`; en otros POST el usuario ve la página 419 genérica.
- `public/index.php` fuerza `APP_URL` con `putenv` para `/scl`; es un parche de despliegue, no una configuración portable.

### 8.4 Secretos y artefactos

- Los scripts y backups (`database_scl_backup.sql`, `_*.php`, `stubs_routes.txt`, `%CK%`) salieron del árbol actual y están en `.gitignore`.
- **Siguen en el historial de Git.** Si el repositorio se compartió o publicó, hay que rotar credenciales de base de datos y evaluar `git filter-repo`/BFG.
- `storage/app/p32.html` y `p33.html` son volcados HTML de sesiones de depuración; no están en el webroot, pero conviene eliminarlos.
- `test.php` en la raíz solo imprime “SCL test works”; es un residuo de verificación.

### 8.5 Internacionalización — **P3**

- Existe `config('app.locale') = 'es'` y `fallback_locale = 'es'`, pero **no existe `lang/`**.
- Comprobado en ejecución: `__('validation.required')` devuelve `validation.required` y `__('auth.failed')` devuelve `auth.failed`.
- Solución: publicar `lang/es/validation.php`, `auth.php`, `passwords.php` y configurar Carbon en español.

---

## 9. Rendimiento y mantenibilidad

### 9.1 Rendimiento

- `PrestacionesController@index()` ejecuta, por cada empleado de la página, `saldoConProyeccion()` y `ultimoCierreConProyeccion()`: **2 consultas por fila**. Con 100 filas son ~200 consultas.
- `estadoDeCuenta()` carga todos los períodos y calcula `tiempoServicio()` por fila; es CPU/iteración, no N+1 de BD, pero crece con la antigüedad.
- `asegurarPeriodos()` / `regenerar()` recorren cada período y `registrarMes()` hace varias consultas por período (salario, tasa, registro previo, `updateOrCreate`). Para 10 años de historia es costoso y síncrono.
- `proyectarActivos()` genera la proyección de toda la nómina dentro de la petición.
- `ReportesController@vacacionesPdf()` consulta el último recibo por cada vacación dentro del `map`, aunque `reporteVacacionesExport()` ya eager-carga `recibo`; hay N+1.
- `estadisticasVacaciones()` recorre las vacaciones y consulta recibos para los totales.
- `TasaBcvService::tasaEn()`, `Parametro::get()` y `SalarioMinimo::vigenteEn()` se consultan repetidamente sin caché por request.
- Faltan índices para filtros frecuentes: `liquidaciones(patrono_id, fecha_liquidacion)`, `movimientos_prestaciones(patrono_id, fecha)`, `pagos_interes_prestaciones` ya tiene índices por patrono/empleado y fecha. Revisar con `EXPLAIN`.
- No hay colas/jobs para generaciones masivas.

**Recomendación:** eager loading/agregados para saldos, caché de tasas/parámetros por request, índices compuestos, generación masiva en jobs y pruebas de carga con una nómina representativa.

### 9.2 Mantenibilidad

- `PrestacionesSocialesService` tiene **1.251 líneas** y mezcla cálculo, persistencia, proyecciones, estado de cuenta y conversión. Conviene dividirlo en calculadora, repositorio y presentador.
- Duplicación de reglas de negocio: días de vacaciones, alícuotas, deducciones IVSS/Paro/FAOV y conversión de moneda están repetidos en varios servicios con pequeñas diferencias.
- Duplicación de formularios: alta de empleado por controlador (`EmpleadoController@store` + `StoreEmpleadoRequest`) y por Livewire (`CrearEmpleado`), con reglas que ya divergen (`salario_inicial`).
- `patronoActual()` repetido en cada controlador; podría resolverse en un middleware/base controller.
- Servicios instanciados con `new` como valor por defecto en constructores (`private VacacionesService $service = new VacacionesService`) y `new TasasBcvService` dentro de métodos: dificulta mocking y pruebas.
- Estados y tipos como cadenas (`'Activo'`, `'por_aprobar'`, `'USD'`) en lugar de enums de PHP 8.2; no existe `app/Enums`.
- Uso de `float` para dinero y `decimal(15,2)` en BD; aceptable para este volumen, pero conviene una política única de redondeo y pruebas de casos límite.
- `Pint` reporta **77 archivos** con problemas de estilo; no hay CI que impida la degradación.
- `composer.json` usa `minimum-stability: dev`; para producción conviene `stable` y versiones fijadas.
- `@tailwindcss/vite` 4 está instalado pero no se usa: el proyecto compila con Tailwind 3 + PostCSS.
- `public/index.php` hardcodea `/scl` y `str_starts_with($uri, '/scl')`; el soporte de subdirectorio debería derivarse de `SCRIPT_NAME`/configuración.
- `README.md` dice “Testing: PHPUnit, Pest”, pero el proyecto usa PHPUnit; también hay pequeñas diferencias entre el README y el estado actual.
- No hay CI. El plan mínimo sería `php -l`, `pint --test`, `phpunit`, `composer audit` y `npm audit`.

### 9.3 Cobertura pendiente

No hay pruebas de:

- vacaciones (año 1, año 2, tope 30, feriados, descansos, deducciones);
- liquidaciones (fracciones, indemnización, deducciones, comparación garantía/literal c);
- utilidades (salario histórico, promedio, recálculo de aprobadas);
- sueldos (variaciones, regeneración, tasas faltantes);
- autorización por patrono/rol y rutas críticas;
- importación de Excel y exportación a hoja de cálculo.

La suite actual es una buena base, pero todavía no cubre los módulos donde quedan las decisiones legales y los riesgos de datos.

---

## 10. Matriz de riesgos

| # | Hallazgo | Probabilidad | Impacto | Prioridad |
|---|---|---|---|---|
| 1 | Permisos por acción y borrado lógico pendientes (roles y auditoría ya implementados) | Baja | Medio | **P2** |
| 2 | Historial salarial reconstruido (37 cambios de ingreso); faltan los aumentos reales anteriores al 29/10/2026 | Alta | Medio | **P2** |
| 3 | Fórmulas pendientes de firma legal: días adicionales acumulativos, intereses vs. literal c, deducción de intereses de préstamos | Media | Muy alto | **P1** |
| 4 | Suite comparte la base de producción con prefijo `test_` | Media | Alto | **P1** |
| 5 | Estado reproducible desde Git y CI activo en GitHub Actions | Baja | Bajo | **Resuelto** |
| 6 | 19 avisos de dependencias, incluido XSS de Livewire 4.3.3 | Media | Medio | **P2** |
| 7 | HTTP, `SESSION_SECURE_COOKIE=false`, `MAIL_MAILER=log`, sin CSP/HSTS/TrustProxies | Alta | Medio | **P2** |
| 8 | Empleado 10 activo con liquidación aprobada | Media | Medio | **P2** |
| 9 | Mensajes `validation.required` / `auth.failed` sin traducir | Alta | Bajo-Medio | **P3** |
| 10 | N+1 y generación síncrona de períodos/proyecciones | Media | Medio | **P3** |
| 11 | Mantenibilidad: servicio de 1.251 líneas y strings sin traducir; Pint y CI ya activos | Media | Medio | **P3** |
| 12 | README desactualizado y archivos de tasas BCV en la raíz | Alta | Bajo | **P3** |

**Riesgos cerrados o mitigados desde el 23/10/2026:**

- Permisos de `.env`, `storage/` y `bootstrap/cache/` (P0 anterior) — **corregido**.
- `app/Http/Request.php` y `deploy/` fuera de Git (P1 anterior) — **corregido**.
- Liquidación que ignoraba la garantía (P0 anterior) — **corregido** con comparación y regeneración.
- Inactivación al crear la liquidación / no reactivar al anular (P1 anterior) — **corregido**.
- Cambio de patrono sin mover historial (P1 anterior) — **corregido**.
- Recibos duplicados y borrados destructivos (P1 anterior) — **mitigado**; queda separar “pagada”/“disfrutada”.
- Feriados delete+insert en cada request (P2 anterior) — **corregido**; `asegurarAnio()` solo materializa si el año no existe.
- Suite en rojo por caché de configuración (P2 anterior) — **corregido**; 150/150.
- Roles, Policies y aislamiento por patrono (P0 anterior) — **corregido** el 28/10/2026.
- Auditoría de creación, modificación, aprobación, anulación e historial de estados (P0 anterior) — **implementada** el 29/10/2026.
- CI con pruebas y Pint bloqueantes (P3 anterior) — **activo** en GitHub Actions.

---

## 11. Plan de acción recomendado

### Fase 0 — Inmediato (esta semana)

1. **Commitear** el módulo de intereses, la migración, las vistas, las pruebas, `FiltrosDeListadosTest` y el tooling; o decidir explícitamente qué no se versiona. El estado actual no es reproducible.
2. **Revisar y firmar** con abogado/contador: días adicionales acumulativos, intereses cuando gana el literal c, deducción de intereses de préstamos y base de alícuota de utilidades.
3. **Corregir la deducción de intereses de préstamos** si se confirma que son intereses ya pagados; separar “interés devengado” de “interés pagado”.
4. **Resolver la anomalía del empleado 10** (activo con liquidación aprobada): verificar si fue reincorporado y, si no, regularizar el estatus antes de permitir nuevos cálculos.
5. **Aislar la suite** en una base `scl_test` separada; dejar el prefijo `test_` solo como respaldo.
6. **Actualizar dependencias** y volver a ejecutar `composer audit`; priorizar Livewire.
7. **Habilitar HTTPS** y `SESSION_SECURE_COOKIE=true` si el sistema se publica fuera de la red local; configurar SMTP real.
8. **Publicar `lang/es`** para validación, auth y passwords.

### Fase 1 — Corto plazo (2-4 semanas)

1. **Roles y Policies** mínimos — **implementado** el 28/10/2026.
2. **Auditoría** (`created_by`, `updated_by`, `approved_by`, `approved_at`, `annulled_by`, `annulled_at`) e historial de estados — **implementada** el 29/10/2026; queda el borrado lógico.
3. **Historial salarial:** una sola vía de alta/edición; registrar `CambioSalario` al editar salario con fecha de vigencia; regenerar sueldos y prestaciones desde esa fecha dentro de una transacción — **implementado** el 29/10/2026; falta cargar los aumentos históricos reales.
4. **Utilidades:** bloquear o versionar el recálculo de aprobadas; usar salario y tasa del período; promediar solo valores no nulos; aclarar que es bonificación fija y no participación sobre beneficios.
5. **Vacaciones:** separar “pagada” de “disfrutada”; hacer el PDF idempotente sin efectos en GET; permitir recalcular un período anulado; documentar el pago de descansos/feriados y las deducciones.
6. **Rendimiento:** eager loading/agregados en listados, caché de tasas/parámetros, índices compuestos y jobs para generaciones masivas.
7. **CI** — **implementado** en GitHub Actions con pruebas y Pint bloqueantes.

### Fase 2 — Mediano plazo (1-3 meses)

1. **Pruebas de dominio** para vacaciones, liquidaciones, utilidades y sueldos, con fechas, tasas y salarios fijos.
2. **Pruebas HTTP** de autorización por patrono/rol y de rutas críticas.
3. **Dividir** `PrestacionesSocialesService` en calculadora, repositorio y presentador; unificar reglas duplicadas.
4. **Enums** para estados, tipos y monedas; política única de redondeo.
5. **Borrado lógico** en todas las entidades con historial; la auditoría ya está implementada.
6. **Endurecer despliegue:** CSP, HSTS, `TrustProxies`, límites de subida de Excel y configuración portable del subdirectorio.
7. **Limpiar** artefactos raíz, README y dependencias no usadas (`@tailwindcss/vite`).

---

## 12. Evidencia y comandos

### 12.1 Comandos ejecutados

```bash
php -v
php artisan --version
php artisan route:list --except-vendor
php artisan migrate:status
php artisan test --compact
php artisan view:cache
composer validate --no-check-publish
composer audit --locked --no-dev
npm audit --omit=dev
vendor/bin/pint --test --format=json
php -l (todos los archivos de app/routes/database)
```

### 12.2 Resultados

- `php artisan test --compact` → **159 passed (671 assertions)**, 26,5 s.
- `php artisan route:list --except-vendor` → **105 rutas propias** (117 totales).
- `php artisan migrate:status` → **40 migraciones**, todas ejecutadas.
- `php artisan view:cache` → **101 vistas compiladas** sin errores.
- `composer validate` → válido.
- `composer audit --locked --no-dev` → **19 avisos en 5 paquetes**.
- `npm audit --omit=dev` → **0 vulnerabilidades**.
- `vendor/bin/pint --test` → **77 archivos** con estilo a corregir.
- `php -l` → sin errores de sintaxis.

### 12.3 Comprobaciones de datos (solo lectura)

- `prestaciones_sociales`: 733 filas (729 reales + 4 proyecciones).
- `liquidaciones`: 8 aprobadas; 7 empleados inactivos; empleado 10 activo con liquidación aprobada.
- `cambios_salarios`: 0; empleados con `salario_inicial` nulo: 22/24.
- `pagos_interes_prestaciones`: 2; `movimientos_prestaciones`: 2.
- `feriados`: 84; `tasas_bcv`: 915; `tasas_interes_prestaciones`: 350; `salarios_minimos`: 10.
- Sin duplicados `(empleado_id, periodo)` en `prestaciones_sociales`.

### 12.4 Lecturas recomendadas del código

| Tema | Archivo |
|---|---|
| Motor de prestaciones | `app/Services/PrestacionesSocialesService.php` |
| Anticipos/préstamos | `app/Services/MovimientosPrestacionesService.php` |
| Liquidación | `app/Services/LiquidacionService.php` |
| Vacaciones | `app/Services/VacacionesService.php` |
| Salario integral | `app/Services/SalarioIntegralService.php` |
| Sueldos | `app/Services/SueldosService.php` |
| Utilidades | `app/Services/UtilidadesService.php` |
| Feriados | `app/Services/DiasFeriadosService.php` / `CalendarioLaboral.php` |
| Pagos de intereses | `app/Http/Controllers/Web/InteresesPrestacionesController.php` |
| Contexto patrono | `app/Http/Middleware/ValidarContextoPatrono.php` |
| Rutas | `routes/web.php` / `routes/auth.php` |
| Configuración de pruebas | `phpunit.xml` / `tests/bootstrap.php` |
| Despliegue | `DEPLOY.md` / `deploy/apache-scl.conf` |

---

## 13. Conclusión

SCL es un proyecto **funcional, con buena arquitectura de módulos y una suite que ya cubre el núcleo de prestaciones e intereses**. El avance desde el 23/10/2026 es real: permisos corregidos, motor documentado y probado, proyecciones, pagos de intereses y comparación garantía vs. literal c.

El problema ya no es “que no funcione”, sino **la confiabilidad de los números y del entorno**:

- El historial salarial está prácticamente vacío, y eso afecta cualquier cálculo histórico.
- Quedan interpretaciones legales sin firmar y una deducción de intereses de préstamos que debe revisarse.
- Roles y auditoría implementados; queda el borrado lógico y el historial salarial.
- La suite comparte la base de producción, aunque el estado del árbol ya está commiteado y el CI está activo.
- El despliegue funciona, pero sigue sobre HTTP y con correo en log.

La prioridad debe ser **resolver el historial salarial y las fórmulas pendientes**, **aislar las pruebas**, **completar el borrado lógico** y **endurecer el despliegue** antes de seguir agregando funcionalidades. Con esas correcciones, la base actual es aprovechable y el esfuerzo de refactor es acotado.

---

*Informe generado a partir de la revisión directa del código, la base de datos y la aplicación en ejecución. Las referencias a LOTTT son técnicas y deben ser validadas por un profesional del derecho laboral.*

---

# Anexos históricos (23/10/2026)

> Los anexos A, B y C se conservan como registro de los incidentes, correcciones y decisiones del 23/10/2026. Algunas cifras (28 pruebas, 74 aserciones) son históricas y no reflejan el estado actual (90 pruebas, 332 aserciones).

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

**Nota histórica:** la suite completa se aisló de la configuración de producción y en esa fecha pasó **28/28** (74 aserciones).

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
- En la fecha del anexo no se pudo ejecutar por falta de root. **Estado actual:** los permisos ya están aplicados y `www-data` puede leer `.env` y escribir en `storage/`.

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

### Estado de las fórmulas en esa fecha

No se cambiaron las fórmulas de prestaciones, intereses ni días adicionales: requerían una decisión legal/contable. Quedaron pendientes:

- Unificar garantía, saldo de anticipos y liquidación en una sola fórmula.
- Definir si los intereses se capitalizan o se pagan por separado.
- Revisar el doble conteo de días adicionales.
- Integrar la garantía y sus intereses en la liquidación final.
- Validar el pago de descansos/feriados en vacaciones y las deducciones de ley.

### Verificación histórica

- `php artisan test` → **28 passed (74 assertions)**.
- Login con la clave nueva → correcto; con la anterior → rechazado.
- Transferencia de patrono → 1 vacación movida (antes 0).
- Flujo de liquidación → activo al crear, inactivo al aprobar, activo al anular.
- Recibo → dos llamadas devuelven el mismo recibo (1 registro).

---

## Anexo C — Motor de prestaciones v2 (23/10/2026)

### Reglas aprobadas por el usuario

1. La liquidación final compara **garantía bruta** contra **30 días por año** y paga el mayor.
2. Interés **simple mensual** sobre el capital; el interés vencido y no pagado se capitaliza **en el aniversario de ingreso**.
3. Días adicionales: se mantiene el **abono del acumulado completo** en cada aniversario (2, 4, 6…), como antes.
4. Vacaciones: se mantiene el pago de descansos y feriados dentro del disfrute.
5. Deducciones: se mantiene el descuento de IVSS/Paro/FAOV/INCES en el recibo.
6. Se recalcula **todo el histórico de prestaciones** con backup; no se tocan liquidaciones aprobadas ni vacaciones.
7. Los **anticipos reducen el capital**; los **préstamos son deuda separada** y no reducen la garantía.
8. Se agrega un **módulo de pagos de intereses** (fecha, monto, moneda, comprobante, observación).
9. La comparación se hace con la garantía **bruta**; los anticipos se descuentan **una sola vez** del mayor.
10. En la liquidación se **reemplaza** el importe de “Prestaciones sociales” por el mayor.

### Cambios técnicos

- `prestaciones_sociales` agrega: `capital_inicial`, `capital_final`, `interes_pendiente_inicial`, `interes_pendiente_final`, `interes_capitalizado`, `interes_pagado`.
- Nueva tabla `pagos_interes_prestaciones` y modelo `PagoInteresPrestaciones`.
- `PrestacionesSocialesService` calcula interés simple sobre capital, aplica pagos y capitaliza en el aniversario.
- `MovimientosPrestacionesService::saldoPrestaciones()` usa el capital de la garantía, no la fórmula 30/32.
- `LiquidacionService` compara garantía bruta vs. literal C, reemplaza el importe y descuenta anticipos una vez; los préstamos siguen como deducción separada.
- `PrestacionesController` agrega alta y eliminación de pagos de intereses; cada movimiento recalcula desde el mes del pago.
- `PrestacionesController@index` ya no genera períodos al listar; solo el detalle los asegura.

### Recálculo histórico

- Respaldo: tabla `prestaciones_sociales_backup_20261023` con **433 filas**.
- Resultado: **435 períodos** en **13 empleados**.
- Nota: al momento del respaldo había 433 períodos, no 114, porque las visitas de verificación a `/prestaciones` habían generado los períodos faltantes de los demás empleados activos. Todos se recalcularon.
- Saldo total de los últimos períodos: **31.949,69 → 29.458,22** (suma de saldos finales por empleado).
- Intereses devengados totales: **17.508,34 → 14.338,57**; interés capitalizado: **9.478,59**; interés pendiente: **4.859,98**; pagos: **0**.
- Sin capital negativo ni diferencias entre `capital + pendiente` y `saldo_final`.

| Empleado | Saldo antes | Saldo después |
|---|---:|---:|
| 8 | 1.475,07 | 1.370,84 |
| 9 | 6.053,11 | 4.946,21 |
| 10 | 11.065,92 | 11.674,95 |

El empleado 10 sube porque el recálculo completó períodos faltantes; no se modificó su liquidación aprobada.

### Liquidación y pagos

- Empleado 8 sin anticipos: garantía bruta `1.370,84` vs. 30 días `361,80`; la línea de prestaciones queda en `1.370,84`.
- Con un anticipo de prueba de `50`: garantía bruta `1.368,86`, anticipo descontado una vez, saldo neto `1.318,86`.
- Préstamo de prueba: no redujo el capital de la garantía.
- Pago de interés de `1 USD`: interés pendiente `217,76 → 216,76`, saldo `1.370,84 → 1.369,84`; al eliminar el pago volvió a `217,76 / 1.370,84`.
- Crear y eliminar el pago vía HTTP funcionó y el estado de cuenta se recalculó.

### Verificación histórica

- `php artisan test` → **28 passed (74 assertions)**.
- `/prestaciones`, `/prestaciones/8`, `/liquidaciones/crear` y `/feriados` → `200`.
- La vista de prestaciones muestra capital, interés pendiente, pagado y el formulario de pagos.
- Las 7 liquidaciones aprobadas conservan sus importes; las vacaciones no se tocaron.

### Pendiente / advertencias

- Las **7 liquidaciones aprobadas antes del motor v2** no se recalcularon por decisión expresa; siguen con la fórmula anterior.
- La interpretación legal de las reglas 1–10 debe ser firmada por contador/abogado antes de usarla masivamente en pagos.
- Siguen pendientes: historial salarial, utilidades y borrado lógico; la auditoría quedó implementada el 29/10/2026.
- El respaldo `prestaciones_sociales_backup_20261023` se conserva en la base; no eliminar hasta validar los nuevos saldos.

---

## Anexo D — Actualización 27/10/2026: módulo de intereses, proyecciones y estado actual

### Cambios incorporados después del 23/10/2026

- **Módulo de pagos de intereses (Art. 143 LOTTT):**
  - `InteresesPrestacionesController` con listado, alta, consulta de saldo por fecha, baja y recálculo.
  - Tabla `pagos_interes_prestaciones` y modelo `PagoInteresPrestaciones`.
  - `PrestacionesSocialesService::interesPendienteEn()` devuelve interés pendiente, período, moneda, tasa y equivalentes USD/BS.
  - Los pagos cubren primero el interés pendiente; el exceso reduce capital.
  - 21 pruebas específicas.
- **Proyecciones de prestaciones:**
  - Hasta 12 meses hacia adelante, solo trabajadores activos.
  - Se marcan con `es_proyeccion`; un cálculo real del mes vencido las reemplaza.
  - Se usan para anticipos e intereses con fecha futura.
  - 4 proyecciones activas en la base actual.
- **Capitalización autorizada:**
  - Solo con `capitaliza_intereses = true` y `capitalizacion_autorizada_desde` no nulo.
  - Solo modalidad `contabilidad`.
  - El interés no autorizado queda pendiente y no genera intereses.
- **Liquidación:**
  - Comparación garantía bruta vs. literal c y pago del mayor.
  - Regeneración de períodos al aprobar/anular/eliminar.
  - Alerta de conciliación en la vista.
  - Retiro de `repararSiEsNecesario()` de `show`/`pdf`.
- **Comprobantes:**
  - Migración `2026_10_27_010619_add_fecha_comprobante_to_movimientos_y_pagos_prestaciones`.
  - PDF de comprobante de movimiento.
- **Calidad:**
  - Suite de 28 → 90 pruebas.
  - Vistas compiladas correctamente (101).
  - Permisos de despliegue corregidos.
  - `app/Http/Request.php` y `deploy/` versionados.

### Hallazgos nuevos de esta actualización

1. **`LiquidacionService` descuenta `MovimientoPrestacionInteres`** como “Pago de intereses”. Si son intereses ya pagados, hay doble cobro; si son devengados no pagados, falta modelar el estado. Revisar.
2. **La línea “Intereses sobre prestaciones” queda en 0** cuando se compara con el literal c. Definir si el Art. 143 se paga aparte cuando gana el literal c.
3. **La suite comparte la base de producción** (`scl`) con prefijo `test_` y 28 tablas de prueba. Migrar a `scl_test`.
4. **El árbol de trabajo sigue sucio:** 25 archivos modificados y el módulo de intereses/pruebas sin commitear.
5. **`i18n` sigue sin `lang/es`:** se confirmó que `validation.required` y `auth.failed` se muestran como claves.
6. **`UtilidadesService` permite recalcular aprobadas** y las devuelve a `calculado`; no hay bloqueo por estatus.
7. **`EmpleadoController@update()` ya registra cambios salariales** (corregido el 29/10/2026); el historial tiene 37 cambios de ingreso y 0 trabajadores sin `salario_inicial`.
8. **La anomalía del empleado 10 persiste:** liquidación aprobada y estatus `Activo`.
9. **Boost/OpenCode no funcionan en este entorno** porque `BoostServiceProvider::shouldRun()` exige `APP_ENV=local` o `APP_DEBUG=true`; con producción/debug false, `boost:mcp` no está disponible.
10. **Quedan volcados HTML** en `storage/app/p32.html` y `p33.html`, además de `test.php` y Excel de ejemplo en la raíz.

### Estado de la suite y verificaciones

- `php artisan test --compact` → **159 passed (671 assertions)**, 26,5 s.
- `php artisan view:cache` → 101 vistas compiladas.
- `php artisan route:list --except-vendor` → 105 rutas propias.
- `composer audit --locked --no-dev` → 19 avisos en 5 paquetes.
- `vendor/bin/pint --test` → 77 archivos con estilo a corregir.
- `npm audit --omit=dev` → 0 vulnerabilidades.
- `php -l` → sin errores.

### Recomendación inmediata

Commitear el estado actual, aislar la suite en una base de pruebas separada, revisar la deducción de intereses de préstamos y la interpretación del Art. 143 frente al literal c, y resolver el historial salarial antes de usar los saldos históricos para pagos.
