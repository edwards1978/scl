#!/usr/bin/env bash
#
# Habilita HTTPS para SCL en Apache, con certificado autofirmado para
# localhost/scl.local. Debe ejecutarse como root:
#
#   sudo bash deploy/enable-https.sh
#
# Variables opcionales:
#   APP_DIR=/var/www/html/scl
#   SERVER_NAME=localhost
#   CERT_FILE=/etc/ssl/scl/scl.crt
#   KEY_FILE=/etc/ssl/scl/scl.key
#
# Para un certificado emitido por una CA real (por ejemplo, Let's Encrypt),
# definir CERT_FILE y KEY_FILE apuntando a esos archivos y ejecutar el script.
#
# El script NO activa HSTS: un certificado autofirmado no debe fijarse con HSTS.
# Para producción con un certificado confiable, agregar HSTS después de validar
# el acceso HTTPS.

set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/html/scl}"
SERVER_NAME="${SERVER_NAME:-localhost}"
CERT_DIR="${CERT_DIR:-/etc/ssl/scl}"
CERT_FILE="${CERT_FILE:-${CERT_DIR}/scl.crt}"
KEY_FILE="${KEY_FILE:-${CERT_DIR}/scl.key}"
APACHE_AVAILABLE="/etc/apache2/sites-available"
APACHE_ENABLED="/etc/apache2/sites-enabled"

if [[ "${EUID}" -ne 0 ]]; then
    echo "ERROR: ejecutar como root: sudo bash deploy/enable-https.sh" >&2
    exit 1
fi

if [[ ! -f "${APP_DIR}/artisan" || ! -d "${APP_DIR}/public" ]]; then
    echo "ERROR: ${APP_DIR} no parece ser la raíz de SCL." >&2
    exit 1
fi

echo "==> Habilitando módulos de Apache"
a2enmod ssl headers rewrite >/dev/null

echo "==> Preparando certificado"
install -d -m 755 "${CERT_DIR}"
if [[ ! -f "${CERT_FILE}" || ! -f "${KEY_FILE}" ]]; then
    openssl req -x509 -nodes -days 825 -newkey rsa:2048 \
        -keyout "${KEY_FILE}" \
        -out "${CERT_FILE}" \
        -subj "/CN=${SERVER_NAME}" \
        -addext "subjectAltName=DNS:${SERVER_NAME},DNS:scl.local,IP:127.0.0.1"
fi
chmod 600 "${KEY_FILE}"
chmod 644 "${CERT_FILE}"

echo "==> Creando VirtualHost HTTPS"
cat > "${APACHE_AVAILABLE}/scl-ssl.conf" <<'CONF'
<VirtualHost *:443>
    ServerName localhost
    ServerAlias scl.local

    DocumentRoot /var/www/html/scl/public
    Alias /scl /var/www/html/scl/public

    <Directory /var/www/html/scl/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    <FilesMatch "^\.">
        Require all denied
    </FilesMatch>

    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"

    RedirectMatch ^/$ /scl/

    SSLEngine on
    SSLCertificateFile /etc/ssl/scl/scl.crt
    SSLCertificateKeyFile /etc/ssl/scl/scl.key
    SSLProtocol all -SSLv3 -TLSv1 -TLSv1.1
    SSLCipherSuite HIGH:!aNULL:!MD5

    ErrorLog ${APACHE_LOG_DIR}/scl_ssl_error.log
    CustomLog ${APACHE_LOG_DIR}/scl_ssl_access.log combined
</VirtualHost>
CONF

# Ajustar rutas si APP_DIR no es el valor por defecto.
if [[ "${APP_DIR}" != "/var/www/html/scl" ]]; then
    sed -i "s|/var/www/html/scl|${APP_DIR}|g" "${APACHE_AVAILABLE}/scl-ssl.conf"
fi

if [[ "${SERVER_NAME}" != "localhost" ]]; then
    sed -i "s|ServerName localhost|ServerName ${SERVER_NAME}|" "${APACHE_AVAILABLE}/scl-ssl.conf"
fi
if [[ "${CERT_FILE}" != "/etc/ssl/scl/scl.crt" ]]; then
    sed -i "s|/etc/ssl/scl/scl.crt|${CERT_FILE}|g" "${APACHE_AVAILABLE}/scl-ssl.conf"
fi
if [[ "${KEY_FILE}" != "/etc/ssl/scl/scl.key" ]]; then
    sed -i "s|/etc/ssl/scl/scl.key|${KEY_FILE}|g" "${APACHE_AVAILABLE}/scl-ssl.conf"
fi

echo "==> Creando VirtualHost HTTP de redirección"
cat > "${APACHE_AVAILABLE}/scl-http-redirect.conf" <<CONF
<VirtualHost *:80>
    ServerName ${SERVER_NAME}
    ServerAlias scl.local

    RedirectMatch permanent ^/scl(/.*)?\$ https://${SERVER_NAME}/scl\$1
    RedirectMatch permanent ^/\$ https://${SERVER_NAME}/scl/
</VirtualHost>
CONF

echo "==> Activando sitios y validando configuración"
a2ensite scl-ssl.conf scl-http-redirect.conf >/dev/null
a2dissite scl.conf >/dev/null 2>&1 || true

if ! apache2ctl configtest; then
    echo "ERROR: Apache no aceptó la configuración. Se restaura el sitio HTTP." >&2
    a2dissite scl-ssl.conf scl-http-redirect.conf >/dev/null 2>&1 || true
    a2ensite scl.conf >/dev/null 2>&1 || true
    systemctl reload apache2 >/dev/null 2>&1 || true
    exit 1
fi

systemctl reload apache2

echo "==> Actualizando .env"
if [[ ! -f "${APP_DIR}/.env" ]]; then
    echo "ERROR: no existe ${APP_DIR}/.env" >&2
    exit 1
fi
cp -a "${APP_DIR}/.env" "${APP_DIR}/.env.bak.https-$(date +%Y%m%d%H%M%S)"
sed -i "s|^APP_URL=.*|APP_URL=https://${SERVER_NAME}/scl|" "${APP_DIR}/.env"
if grep -q '^SESSION_SECURE_COOKIE=' "${APP_DIR}/.env"; then
    sed -i 's|^SESSION_SECURE_COOKIE=.*|SESSION_SECURE_COOKIE=true|' "${APP_DIR}/.env"
else
    printf '\nSESSION_SECURE_COOKIE=true\n' >> "${APP_DIR}/.env"
fi
chown root:www-data "${APP_DIR}/.env"
chmod 640 "${APP_DIR}/.env"

echo "==> Regenerando caché de configuración"
if ! sudo -u www-data php "${APP_DIR}/artisan" config:cache; then
    echo "ADVERTENCIA: no se pudo regenerar config:cache como www-data." >&2
    echo "Ejecuta: cd ${APP_DIR} && sudo -u www-data php artisan config:cache" >&2
fi

echo
echo "HTTPS habilitado. Verifica:"
echo "  curl -k -I https://${SERVER_NAME}/scl"
echo "  curl -I http://${SERVER_NAME}/scl"
echo
echo "El certificado es autofirmado: el navegador mostrará una advertencia"
echo "la primera vez. Para evitarla, usa un certificado de una CA confiable"
echo "y vuelve a ejecutar con CERT_FILE y KEY_FILE."
echo
echo "Rollback:"
echo "  sudo a2dissite scl-ssl.conf scl-http-redirect.conf"
echo "  sudo a2ensite scl.conf"
echo "  sudo systemctl reload apache2"
echo "  Restaura .env desde ${APP_DIR}/.env.bak.https-*"
