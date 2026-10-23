#!/usr/bin/env bash
#
# Corrige permisos de SCL para que Apache/PHP (www-data) pueda leer .env y
# escribir en las carpetas de runtime. Debe ejecutarse como root:
#
#   sudo bash deploy/fix-permissions.sh
#   sudo bash deploy/fix-permissions.sh /var/www/html/scl
#
# No toca storage/app/private: ahí viven respaldos y scripts legados que no
# deben quedar accesibles para el usuario web.

set -euo pipefail

APP_DIR="${1:-/var/www/html/scl}"
WEB_USER="${WEB_USER:-www-data}"

if [[ "${EUID}" -ne 0 ]]; then
    echo "ERROR: ejecutar como root (sudo)." >&2
    exit 1
fi

if [[ ! -d "${APP_DIR}/public" || ! -f "${APP_DIR}/artisan" ]]; then
    echo "ERROR: ${APP_DIR} no parece ser la raíz de SCL." >&2
    exit 1
fi

if ! id "${WEB_USER}" >/dev/null 2>&1; then
    echo "ERROR: el usuario ${WEB_USER} no existe." >&2
    exit 1
fi

echo "Aplicando permisos en ${APP_DIR} para ${WEB_USER}..."

# Carpetas de runtime que Laravel necesita escribir.
for dir in \
    "${APP_DIR}/storage/framework" \
    "${APP_DIR}/storage/logs" \
    "${APP_DIR}/storage/app/public" \
    "${APP_DIR}/bootstrap/cache"
do
    install -d -o "${WEB_USER}" -g "${WEB_USER}" -m 775 "${dir}"
    chown -R "${WEB_USER}:${WEB_USER}" "${dir}"
    find "${dir}" -type d -exec chmod 775 {} \;
    find "${dir}" -type f -exec chmod 664 {} \;
done

# .env: legible por el usuario web, no por el resto.
if [[ -f "${APP_DIR}/.env" ]]; then
    chown root:"${WEB_USER}" "${APP_DIR}/.env"
    chmod 640 "${APP_DIR}/.env"
fi

# Respaldos, dumps y scripts legados: solo el propietario.
if [[ -d "${APP_DIR}/storage/app/private" ]]; then
    chmod 700 "${APP_DIR}/storage/app/private"
    find "${APP_DIR}/storage/app/private" -type d -exec chmod 700 {} \;
    find "${APP_DIR}/storage/app/private" -type f -exec chmod 600 {} \;
fi

# Verificación rápida (solo metadatos; no se ejecuta como www-data).
echo
echo "Permisos resultantes:"
stat -c '%U:%G %a %n' \
    "${APP_DIR}/.env" \
    "${APP_DIR}/storage/framework/views" \
    "${APP_DIR}/storage/logs" \
    "${APP_DIR}/bootstrap/cache" 2>/dev/null || true

echo
echo "Listo. Si había cachés de configuración/vistas, regenéralas con:"
echo "  cd ${APP_DIR} && sudo -u ${WEB_USER} php artisan optimize:clear && sudo -u ${WEB_USER} php artisan optimize"
