# Pendientes vigentes — SCL

**Actualizado:** 29/10/2026
**Estado:** suite 195 pruebas / 821 aserciones en verde; Pint limpio; CI en verde (último run verificado: #15).

Este archivo reúne lo que queda pendiente de funcionalidades **ya existentes**. No incluye ideas nuevas.

---

## Diferido por decisión: papelera

La purga definitiva y la retención automática ya están implementadas. Quedan para el final, cuando se retome el tema:

- Auditar individualmente los recibos de vacaciones que se restauran junto con su vacación.
- Revisar los conflictos de restauración por claves únicas; hoy las altas redirigen a la papelera.
- QA visual en navegador de la papelera y de los botones según rol.
- Verificar en producción que el cron de Laravel ejecute `schedule:run` para la purga diaria.

---

## Prioridad alta

1. **Fórmulas pendientes de firma legal/contable.**
   Días adicionales acumulativos, intereses cuando gana el literal c, deducción de intereses de préstamos y base de alícuota de utilidades. No se deben cambiar sin decisión del usuario.

2. **Calidad del cálculo histórico de utilidades.**
   El último sueldo no se filtra por año, el promedio divide entre filas con valores nulos y la tasa BCV es la del día del cálculo. Requiere decisión funcional/legal antes de tocarlo.

---

## Prioridad media

3. **Vacaciones.**
   - `accionPagar()` marca `disfrutada`; el estado `pagada` no se usa (0 registros frente a 14 `disfrutada`).
   - `reciboPdf()` es un GET con efecto de escritura: crea el recibo si no existe.
   - Revisar el recálculo de períodos anulados y la validación de descansos/feriados/deducciones.

4. **Despliegue.**
   - HTTP con `SESSION_SECURE_COOKIE=false`, `MAIL_MAILER=log`, sin CSP/HSTS/`TrustProxies`.
   - `public/index.php` fuerza el subdirectorio `/scl` con una comparación que también coincide con `/sclfoo`.

5. **Historial salarial histórico.**
   Faltan los aumentos reales anteriores al 29/10/2026. El usuario los cargará manualmente cuando existan.

---

## Prioridad baja

6. **Traducciones.** No existe `lang/es`; `validation.required` y `auth.failed` se muestran como claves.

7. **Rendimiento.** N+1 en `PrestacionesController@index()`, reportes de vacaciones y generación síncrona de períodos/proyecciones.

8. **Cobertura de pruebas.** Faltan casos de vacaciones, importación/exportación y flujos completos de liquidación.

9. **Mantenimiento.**
    - README desactualizado y archivos Excel de ejemplo en la raíz.
    - Dos migraciones comparten el prefijo `2026_10_29_000002`.
    - `PrestacionesSocialesService` concentra demasiadas responsabilidades.

---

## Resuelto

### 29/10/2026

- **Dependencias actualizadas y auditadas.** Sin avisos en `composer audit` ni en `npm audit`. Se actualizaron Laravel 12.64.0 → 12.70.2, Livewire 4.3.3 → 4.4.8, PhpSpreadsheet 5.9.0 → 5.10.0, league/commonmark 2.8.3 → 2.10.4, Flysystem 3.35.2 → 3.36.0 y Symfony 7.4.x → 7.4.22. Se agregó `composer audit --locked --no-dev` como paso bloqueante del CI.

- **Utilidades: bonificaciones aprobadas protegidas.** `UtilidadesService::calcularBonificacion()` lanza `DomainException` si la bonificación existente está `aprobado`; el cálculo masivo la omite y reporta cuántas recalculó/omitió; el recálculo individual devuelve error; la vista oculta la acción y muestra un candado. Cubierto por `tests/Feature/Utilidades/ProtegerAprobadasTest.php`.

- **Empleado 10 (LUIS DIAZ) regularizado.** Se confirmó que no fue reincorporado tras el retiro del 07/08/2026. El empleado quedó `Inactivo`, con lo que se bloquean nuevos cálculos y la generación de sueldos. Sus vacaciones ya estaban disfrutadas y no tenía períodos pendientes.

- **Sueldos posteriores al egreso.** `SueldosService::periodosRequeridos()` ahora se detiene en el mes de egreso de la última liquidación aprobada. Al aprobar una liquidación se eliminan los sueldos devengados después de ese mes. Se eliminaron **13 filas** generadas por error el 29/10 en 7 trabajadores ya liquidados (2026-09 y 2026-10). Cubierto por `tests/Feature/Sueldos/SueldosPostEgresoTest.php`.
