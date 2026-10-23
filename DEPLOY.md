# Despliegue en producción — SCL

Guía verificada sobre Ubuntu 24.04 / Apache 2.4 / PHP 8.3 / MariaDB 10.11.
El proyecto vive en `/var/www/html/scl` y el servidor web **debe** apuntar a
`/var/www/html/scl/public`.

## 1. Estado verificado del proyecto

| Elemento | Estado |
|---|---|
| Framework | Laravel 12.64 (PHP 8.3.6) |
| Dependencias PHP | `composer validate` correcto; `composer install --no-dev` viable |
| Dependencias JS | Node 18.19 / npm 9.2; `npm run build` correcto |
| Base de datos | MariaDB 10.11, 34 migraciones aplicadas |
| Datos iniciales | Admin, feriados (28) y salarios mínimos (10) cargados |
| Rutas | 110 rutas; `php artisan optimize` (config/route/view cache) funciona |
| Login y sesiones | Probado end-to-end con `admin@scl.local` |
| Módulos | Dashboard, patronos, empleados, vacaciones, prestaciones, movimientos, liquidaciones, utilidades, sueldos, reportes, tasas y feriados responden 200 |
| PDF | Dompdf genera correctamente recibos de vacaciones y prestaciones |
| Extensiones PHP | `pdo_mysql, mbstring, openssl, tokenizer, xml, ctype, bcmath, gd, intl, zip` presentes |

## 2. Requisitos

```bash
sudo apt update
sudo apt install -y apache2 libapache2-mod-php php-cli php-mysql php-mbstring \
  php-xml php-curl php-zip php-gd php-bcmath php-intl php-tokenizer \
  mariadb-server composer unzip git
# Node 18+ solo si se compilan assets en el servidor
```

## 3. Instalación

```bash
cd /var/www/html/scl

composer install --no-dev --optimize-autoloader
npm ci && npm run build

cp .env.example .env      # solo si no existe .env
php artisan key:generate
```

Editar `.env`:

```dotenv
APP_NAME=SCL
APP_ENV=production
APP_DEBUG=false
APP_URL=http://TU_DOMINIO_O_IP/scl

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=scl
DB_USERNAME=scl
DB_PASSWORD=UNA_CLAVE_FUERTE

LOG_LEVEL=warning
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true   # solo con HTTPS
REGISTRATION_ENABLED=false   # el autorregistro esta cerrado por defecto
```

Base de datos:

```bash
sudo mysql -e "CREATE DATABASE IF NOT EXISTS scl CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER IF NOT EXISTS 'scl'@'localhost' IDENTIFIED BY 'UNA_CLAVE_FUERTE';"
sudo mysql -e "GRANT ALL PRIVILEGES ON scl.* TO 'scl'@'localhost'; FLUSH PRIVILEGES;"

php artisan migrate --force
# Solo en la primera instalación (crea admin, feriados y salarios mínimos):
php artisan db:seed --force
```

> Para restaurar datos desde `database_scl_backup.sql`:
> `sudo mysql scl < database_scl_backup.sql` (hacer backup antes; el dump
> elimina y recrea las tablas).

## 4. Permisos

Ejecutar como root (ajusta la ruta si es distinta):

```bash
sudo bash deploy/fix-permissions.sh /var/www/html/scl
php artisan storage:link
```

El script hace:

- `.env` legible por `www-data` (`root:www-data`, `640`).
- `storage/framework`, `storage/logs`, `storage/app/public` y
  `bootstrap/cache` escribibles por `www-data`.
- `storage/app/private` restringido (respaldos y scripts legados).
- No usa ACL: si el filesystem no las soporta, los permisos POSIX bastan.

## 5. Apache (obligatorio)

El servidor actual tiene `DocumentRoot /var/www/html` y `AllowOverride None`,
por lo que **Laravel no enruta** y archivos sensibles quedan públicos
(`.env`, dumps SQL, hojas de cálculo, cookies de sesión). La configuración
incluida publica la aplicación en **http://localhost/scl** mediante un
`Alias` que apunta únicamente a `public/`. Corregir con:

```bash
sudo cp deploy/apache-scl.conf /etc/apache2/sites-available/scl.conf
sudo a2enmod rewrite headers alias
sudo a2ensite scl.conf
sudo a2dissite 000-default.conf
sudo apache2ctl configtest
sudo systemctl reload apache2
```

Ajustar `ServerName` y, si aplica, `APP_URL` (debe incluir `/scl`).

### Limpiar la copia antigua del webroot

En `/var/www/html` existe una copia antigua del proyecto (sin `.env`) cuyos
archivos son accesibles por HTTP. Después de activar el VirtualHost, archivar
fuera del webroot:

```bash
sudo mkdir -p /var/backups/scl-copia-antigua
cd /var/www/html
sudo mv app bootstrap config database public resources routes storage tests \
  artisan composer.json composer.lock package.json package-lock.json \
  phpunit.xml postcss.config.js tailwind.config.js vite.config.js \
  README.md MIGRACION_UBUNTU.md database_scl_backup.sql \
  _dbstate.php _err.php _final.php _login_probe.php _patroncheck.php \
  '%CK%' stubs_routes.txt test.php 1_3_18.xlsx 2_1_2c26_smc.xls \
  /var/backups/scl-copia-antigua/ 2>/dev/null || true
```

Conservar únicamente `/var/www/html/scl` y `index.html` si se usa.

## 6. Optimización final

```bash
cd /var/www/html/scl
php artisan optimize:clear
php artisan optimize        # config + routes + views + events
php artisan migrate --force
```

Repetir `php artisan optimize:clear && php artisan optimize` después de
cada cambio en `.env` o en rutas.

## 7. Seguridad obligatoria antes de publicar

1. **Contraseña del admin**: el seeder ya no usa una clave fija. Si
   `ADMIN_PASSWORD` está definido en `.env`, la usa; si no, genera una clave
   aleatoria y la muestra una sola vez. Para cambiarla manualmente:

   ```bash
   php artisan tinker --execute="\App\Models\User::where('email','admin@scl.local')->update(['password'=>bcrypt('CLAVE_FUERTE_AQUI')]);"
   ```

2. **Registro público**: ya está deshabilitado por defecto
   (`REGISTRATION_ENABLED=false`). No habilitarlo salvo que se implementen
   roles y aprobación de cuentas. Tras cambiarlo ejecutar
   `php artisan optimize:clear && php artisan optimize`.
3. **HTTPS**: instalar certificado (`certbot --apache`) y poner
   `SESSION_SECURE_COOKIE=true`.
4. **Sacar del servidor web** `database_scl_backup.sql`, `*.xls(x)`,
   `stubs_routes.txt`, `%CK%` y los scripts `_*.php`. No deben estar en el
   webroot ni en el repositorio de producción.
5. **Correo real**: `MAIL_MAILER=log` no envía correos; configurar SMTP para
   recuperación de contraseñas.
6. **Base de datos**: contraseña fuerte y `bind-address = 127.0.0.1`.
7. **Backups periódicos** de la base de datos y de `storage/`.
8. `APP_DEBUG=false` y `APP_ENV=production` (ya configurado en `.env`).

## 8. Tests

Los tests usan la base de datos configurada y hacen rollback por transacción
(`DatabaseTransactions`), por lo que no borran datos. Aun así, para CI o para
probar sin tocar producción, crear una base separada:

```sql
CREATE DATABASE scl_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON scl_test.* TO 'scl'@'localhost';
```

y ejecutar con:

```bash
php artisan optimize:clear          # evita usar la configuracion cacheada
DB_DATABASE=scl_test php artisan test
php artisan optimize                # volver a cachear para produccion
```

Resultado esperado: **26 tests OK**.

## 9. Verificación posterior al despliegue

```bash
curl -I http://TU_DOMINIO/scl/up          # 200
curl -I http://TU_DOMINIO/scl/login       # 200
curl -I http://TU_DOMINIO/scl/.env        # 403/404 (nunca 200)
curl -I http://TU_DOMINIO/scl/%25CK%25    # 403/404
```

Luego iniciar sesión, seleccionar/crear patrono y recorrer los módulos.
