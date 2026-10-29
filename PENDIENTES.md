# Pendientes vigentes — SCL

**Actualizado:** 29/10/2026
**Estado:** suite 189 pruebas / 802 aserciones en verde; Pint limpio; CI pendiente de verificar tras el último push.

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

1. **Anomalía de datos: empleado 10.**
   El empleado 10 (LUIS DIAZ) sigue `Activo` con una liquidación `aprobada` del 07/08/2026. Hay que confirmar si fue reincorporado o regularizar su estatus antes de nuevos cálculos.

2. **Dependencias con avisos de seguridad.**
   `composer audit --locked --no-dev` reporta **24 avisos en 7 paquetes**. `livewire/livewire 4.3.3` está afectado por un XSS; hay versión 4.4.8 disponible. Requiere aprobación para actualizar dependencias y añadir `composer audit` al CI.

3. **Fórmulas pendientes de firma legal/contable.**
   Días adicionales acumulativos, intereses cuando gana el literal c, deducción de intereses de préstamos y base de alícuota de utilidades. No se deben cambiar sin decisión del usuario.

### Resuelto (29/10/2026)

- **Utilidades: bonificaciones aprobadas protegidas.** `UtilidadesService::calcularBonificacion()` lanza `DomainException` si la bonificación existente está `aprobado`; el cálculo masivo la omite y reporta cuántas recalculó/omitió; el recálculo individual devuelve error; la vista oculta la acción y muestra un candado. Cubierto por `tests/Feature/Utilidades/ProtegerAprobadasTest.php`.

### Pendiente relacionado

- **Calidad del cálculo histórico de utilidades.** El último sueldo no se filtra por año, el promedio divide entre filas con valores nulos y la tasa BCV es la del día del cálculo. Requiere decisión funcional/legal antes de tocarlo.

---

## Prioridad media

4. **Vacaciones.**
   - `accionPagar()` marca `disfrutada`; el estado `pagada` no se usa (0 registros frente a 14 `disfrutada`).
   - `reciboPdf()` es un GET con efecto de escritura: crea el recibo si no existe.
   - Revisar el recálculo de períodos anulados y la validación de descansos/feriados/deducciones.

5. **Despliegue.**
   - HTTP con `SESSION_SECURE_COOKIE=false`, `MAIL_MAILER=log`, sin CSP/HSTS/`TrustProxies`.
   - `public/index.php` fuerza el subdirectorio `/scl` con una comparación que también coincide con `/sclfoo`.

6. **Historial salarial histórico.**
   Faltan los aumentos reales anteriores al 29/10/2026. El usuario los cargará manualmente cuando existan.

---

## Prioridad baja

7. **Traducciones.** No existe `lang/es`; `validation.required` y `auth.failed` se muestran como claves.

8. **Rendimiento.** N+1 en `PrestacionesController@index()`, reportes de vacaciones y generación síncrona de períodos/proyecciones.

9. **Cobertura de pruebas.** Faltan casos de vacaciones, liquidaciones, utilidades, sueldos e importación/exportación.

10. **Mantenimiento.**
    - README desactualizado y archivos Excel de ejemplo en la raíz.
    - Dos migraciones comparten el prefijo `2026_10_29_000002`.
    - `PrestacionesSocialesService` concentra demasiadas responsabilidades.
