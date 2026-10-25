# SCL - Sistema de Cálculos Laborales

Sistema web desarrollado en **Laravel 12** para la gestión y cálculo de prestaciones sociales, vacaciones, liquidaciones y sueldos según la legislación laboral venezolana (LOTTT).

## 🚀 Características Principales

### Gestión de Entidades
- **Patronos**: Registro de empresas/empleadores con configuración de parámetros (INCES, bonificación fin de año, días de descanso, etc.)
- **Empleados**: Administración de personal con datos personales, salarios, fechas de ingreso, moneda (BS/USD), y configuración de prestaciones

### Módulos de Cálculo
- **Vacaciones**: Cálculo de días, períodos, bonos vacacionales y recibos de pago
- **Prestaciones Sociales**: Cálculo automático según antigüedad, salario integral y tasas de interés BCV
- **Liquidaciones**: Liquidación final de empleo (prestaciones, vacaciones pendientes, utilidades, bonificaciones)
  con comparativo Art. 142 a+b vs. literal c y aplicación del mayor.
- **Utilidades**: Cálculo de participación en utilidades según LOTTT
- **Sueldos**: Gestión de variaciones salariales e histórico de cambios

### Configuración y Parámetros
- **Tasas BCV**: Histórico de tasas de cambio oficial para conversiones USD/BS
- **Tasas de Interés**: Tasas anuales para cálculo de intereses sobre prestaciones
- **Salarios Mínimos**: Histórico de decretos de salario mínimo nacional
- **Feriados**: Días feriados fijos y variables (nacionales, regionales, locales)
- **Parámetros Globales**: Configuración general del sistema

### Reportes y Exportación
- Reportes en pantalla y exportación a PDF para:
  - Vacaciones (individuales y colectivas)
  - Prestaciones sociales
  - Liquidaciones finales
  - Utilidades
  - Recibos de pago
- Estado de cuenta de prestaciones también exportable a **Hoja de Cálculo (.ods)**,
  con filtro por período y totales del período o acumulados.

### Tecnologías
- **Backend**: Laravel 12, PHP 8.2+
- **Frontend**: Blade, Tailwind CSS, Alpine.js, Livewire 4
- **Base de Datos**: MySQL/MariaDB
- **Autenticación**: Laravel Breeze
- **Testing**: PHPUnit, Pest

## 📋 Requisitos

- PHP >= 8.2
- Composer
- Node.js >= 18 & NPM
- MySQL/MariaDB 8.0+
- Extensiones PHP: bcmath, ctype, fileinfo, gd, intl, mbstring, openssl, pdo_mysql, tokenizer, xml, zip

## 🛠️ Instalación

```bash
# Clonar repositorio
git clone https://github.com/edwards1978/scl.git
cd scl

# Instalar dependencias PHP
composer install

# Instalar dependencias JS
npm install

# Configurar entorno
cp .env.example .env
php artisan key:generate

# Configurar base de datos en .env
# DB_DATABASE=scl
# DB_USERNAME=root
# DB_PASSWORD=

# Ejecutar migraciones y seeders
php artisan migrate --seed

# Compilar assets
npm run build

# Iniciar servidor
php artisan serve
```

## 🔐 Acceso Inicial

Tras ejecutar el seeder `DefaultAdminSeeder`:
- **Email**: admin@scl.local
- **Password**: se genera una clave aleatoria y se muestra **una sola vez** en
  la salida del seeder; también se puede fijar con `ADMIN_PASSWORD` en `.env`.

> Cambiar esta contraseña inmediatamente después del despliegue (ver `DEPLOY.md`).

## 📁 Estructura del Proyecto

```
app/
├── Http/Controllers/Web/     # Controladores principales por módulo
├── Livewire/                 # Componentes reactivos (CrearEmpleado, CrearLiquidacion, etc.)
├── Models/                   # Modelos Eloquent (Empleado, Patrono, Liquidacion, etc.)
├── Services/                 # Lógica de negocio (LiquidacionService, PrestacionesService, etc.)
├── Rules/                    # Reglas de validación (CedulaVenezolanaRule, RifVenezolanoRule)
└── View/Components/          # Componentes Blade reutilizables

database/
├── migrations/               # 30+ migraciones para esquemas completos
└── seeders/                  # Datos iniciales (feriados, salarios mínimos, admin)

resources/views/
├── empleados/                # CRUD empleados
├── patronos/                 # CRUD patronos
├── vacaciones/               # Gestión vacaciones
├── prestaciones/             # Prestaciones sociales
├── liquidaciones/            # Liquidaciones finales
├── utilidades/               # Cálculo utilidades
├── sueldos/                  # Histórico salarial
├── tasas/                    # Tasas BCV e interés
├── feriados/                 # Calendario feriados
├── reportes/                 # Reportes PDF
└── livewire/                 # Vistas componentes Livewire
```

## 🧪 Testing

```bash
# Ejecutar tests
php artisan test

# Con coverage
php artisan test --coverage
```

> Las pruebas usan tablas con prefijo `test_` en la misma base de datos y crean
> el esquema automáticamente en la primera ejecución (`tests/bootstrap.php`).
> No leen la caché de configuración de producción ni modifican las tablas
> reales. Si se dispone de otra base para pruebas, configurar `DB_DATABASE` y
> dejar `DB_PREFIX` vacío en `phpunit.xml`.

## 📦 Despliegue en Producción

Consultar la guía completa **[DEPLOY.md](DEPLOY.md)** (Apache, permisos,
seguridad y verificación).

```bash
# Dependencias y assets de producción
composer install --no-dev --optimize-autoloader
npm ci && npm run build

# Base de datos
php artisan migrate --force
php artisan db:seed --force   # solo la primera vez

# Optimizaciones
php artisan optimize

# Permisos storage (ejecutar como root)
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

> El servidor web debe apuntar a la carpeta `public/`, nunca a la raíz del
> proyecto; de lo contrario `.env`, backups y código quedan expuestos.

## 📄 Licencia

Proyecto privado - Todos los derechos reservados.

## 👨‍💻 Autor

**Edwards** - [@edwards1978](https://github.com/edwards1978)

---

*Sistema diseñado para cumplir con la legislación laboral venezolana (LOTTT, LOPCYMAT, normas BCV)*