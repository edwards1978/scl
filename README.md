# SCL - Sistema de Cálculos Laborales

Sistema web desarrollado en **Laravel 11** para la gestión y cálculo de prestaciones sociales, vacaciones, liquidaciones y sueldos según la legislación laboral venezolana (LOTTT).

## 🚀 Características Principales

### Gestión de Entidades
- **Patronos**: Registro de empresas/empleadores con configuración de parámetros (INCES, bonificación fin de año, días de descanso, etc.)
- **Empleados**: Administración de personal con datos personales, salarios, fechas de ingreso, moneda (BS/USD), y configuración de prestaciones

### Módulos de Cálculo
- **Vacaciones**: Cálculo de días, períodos, bonos vacacionales y recibos de pago
- **Prestaciones Sociales**: Cálculo automático según antigüedad, salario integral y tasas de interés BCV
- **Liquidaciones**: Liquidación final de empleo (prestaciones, vacaciones pendientes, utilidades, bonificaciones)
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

### Tecnologías
- **Backend**: Laravel 11, PHP 8.2+
- **Frontend**: Blade, Tailwind CSS, Alpine.js, Livewire 3
- **Base de Datos**: MySQL/MariaDB
- **Autenticación**: Laravel Breeze
- **Testing**: PHPUnit, Pest

## 📋 Requisitos

- PHP >= 8.2
- Composer
- Node.js >= 18 & NPM
- MySQL/MariaDB 8.0+
- Extensiones PHP: bcmath, ctype, fileinfo, gd, mbstring, openssl, pdo, tokenizer, xml

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
- **Password**: password

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

## 📦 Despliegue en Producción

```bash
# Optimizaciones
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Compilar assets producción
npm run build

# Permisos storage
chmod -R 775 storage bootstrap/cache
```

## 📄 Licencia

Proyecto privado - Todos los derechos reservados.

## 👨‍💻 Autor

**Edwards** - [@edwards1978](https://github.com/edwards1978)

---

*Sistema diseñado para cumplir con la legislación laboral venezolana (LOTTT, LOPCYMAT, normas BCV)*