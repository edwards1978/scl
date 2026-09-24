# Migracion del Sistema SCL a Ubuntu 26.04.1

## Requisitos del sistema

- Ubuntu 26.04.1 LTS
- PHP 8.2+ con extensiones: pdo_mysql, mbstring, openssl, tokenizer, xml, ctype, bcmath, gd
- MySQL 8.0+ o MariaDB 10.4+
- Composer 2.x
- Node.js 18+ y npm

## Paso 1: Instalar dependencias del sistema

```bash
sudo apt update
sudo apt install -y php php-cli php-mysql php-mbstring php-xml php-curl php-zip php-gd php-bcmath php-tokenizer unzip curl git
```

## Paso 2: Instalar MySQL

```bash
sudo apt install -y mysql-server
sudo mysql_secure_installation
```

Durante la instalacion, configurar:
- Contraseña de root: la que prefieras (actualizar `.env` con esta contraseña)
- Eliminar usuarios anonimos: Si
- Eliminar acceso remoto root: Si
- Eliminar base de datos de prueba: Si
- Recargar tablas de privilegios: Si

## Paso 3: Instalar Composer

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

## Paso 4: Instalar Node.js (para Vite/Tailwind)

```bash
sudo apt install -y nodejs npm
```

## Paso 5: Copiar el proyecto al servidor

Copiar la carpeta `scl` del pendrive a `/var/www/` (o la ubicacion que prefieras):

```bash
sudo cp -r /media/usuario/PENDRIVE/scl /var/www/
sudo chown -R www-data:www-data /var/www/scl
sudo chmod -R 775 /var/www/scl/storage
sudo chmod -R 775 /var/www/scl/bootstrap/cache
```

## Paso 6: Configurar el entorno

```bash
cd /var/www/scl
```

Editar el archivo `.env` y cambiar las siguientes lineas:

```
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=scl
DB_USERNAME=root
DB_PASSWORD=tu_contraseña_de_mysql
APP_URL=http://localhost/scl
```

> **IMPORTANTE:** En Ubuntu, MySQL usa el puerto por defecto **3306** (no 3308 como en XAMPP).

## Paso 7: Crear la base de datos e importar los datos

```bash
sudo mysql -u root -p -e "CREATE DATABASE scl CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -u root -p scl < /var/www/scl/database_scl_backup.sql
```

## Paso 8: Instalar dependencias PHP y Node

```bash
cd /var/www/scl
composer install --no-dev --optimize-autoloader
npm install
npm run build
```

## Paso 9: Configurar Laravel

```bash
cd /var/www/scl
php artisan key:generate
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan optimize:clear
```

## Paso 10: Configurar permisos de almacenamiento

```bash
sudo chown -R www-data:www-data /var/www/scl/storage
sudo chown -R www-data:www-data /var/www/scl/bootstrap/cache
sudo chmod -R 775 /var/www/scl/storage
sudo chmod -R 775 /var/www/scl/bootstrap/cache
```

## Paso 11: Verificar la conexion

```bash
cd /var/www/scl
php artisan migrate:status
```

Si muestra la lista de migraciones como "Ran", la conexion y los datos estan correctos.

## Paso 12: Configurar el servidor web (Apache)

```bash
sudo apt install -y apache2 libapache2-mod-php
sudo a2enmod rewrite
```

Crear archivo de configuracion:

```bash
sudo nano /etc/apache2/sites-available/scl.conf
```

Contenido:

```apache
<VirtualHost *:80>
    ServerName localhost
    DocumentRoot /var/www/scl/public

    <Directory /var/www/scl>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/scl_error.log
    CustomLog ${APACHE_LOG_DIR}/scl_access.log combined
</VirtualHost>
```

Habilitar el sitio:

```bash
sudo a2dissite 000-default.conf
sudo a2ensite scl.conf
sudo systemctl restart apache2
```

## Paso 13: Verificar

Abrir en el navegador:

```
http://localhost/scl
```

## Archivos incluidos en la migracion

| Archivo | Descripcion |
|---------|-------------|
| `scl/` | Carpeta completa del proyecto Laravel |
| `scl/database_scl_backup.sql` | Respaldo de la base de datos (260 KB) |
| `scl/.env` | Archivo de configuracion (puerto 3306, sin contraseña por defecto) |

## Solucion de problemas

### Error: "Access denied for user"
Verificar que la contraseña en `.env` coincida con la de MySQL:
```bash
sudo mysql -u root -p
```

### Error: "No such file or directory" en PHP
Verificar que PHP este instalado y en el PATH:
```bash
php -v
```

### Error: "Permission denied" en storage/
Reasignar permisos:
```bash
sudo chown -R www-data:www-data /var/www/scl/storage
sudo chmod -R 775 /var/www/scl/storage
```

### Los estilos CSS no cargan
Rebuild de Vite:
```bash
cd /var/www/scl
npm run build
```
