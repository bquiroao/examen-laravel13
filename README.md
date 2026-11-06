# Examen Laravel 13 — Módulo Citas Médicas

Práctica de migración de un módulo de **Laravel 12 a Laravel 13**. Este repositorio contiene el módulo **`citas-medicas`** del proyecto final *Sistema Hospitalario Integrado* (curso Análisis de Sistemas II), portado a un proyecto limpio de Laravel 13 y verificado con pruebas automáticas.

> Este repositorio es de práctica. El proyecto original (Laravel 12) sigue siendo el del equipo y no se modifica desde aquí.

## Resultado

| Elemento | Estado |
|---|---|
| Framework | Laravel **13.40** |
| Migraciones (2 bases PostgreSQL) | Ejecutan sin cambios |
| Rutas del módulo | 4 rutas de citas + 6 de autenticación |
| Pruebas de citas | **6 pasan, 96 aserciones** |

## Requisitos

- PHP **8.3 o superior** (Laravel 13 ya no admite 8.2). Probado con PHP 8.4.
- Composer 2
- Extensiones de PHP: `pdo_pgsql` y `pgsql`
- Docker (para PostgreSQL)

## Tecnologías

- Laravel 13
- PostgreSQL 16, con dos bases: `his_central` y `his_hospital`
- Autenticación JWT con `tymon/jwt-auth` 2.3
- PHPUnit 12

## Qué incluye el módulo

**Código propio de citas**

- `app/Http/Controllers/Api/V1/Clinical/AppointmentController.php`
- `app/Services/Appointments/` (`AppointmentService`, `AvailabilityService`)
- `app/Http/Resources/AppointmentResource.php`
- `app/Models/Hospital/Appointment.php`
- Migración de la tabla `appointments`, en `database/migrations/hospital/`
- Pruebas: `tests/Feature/Clinical/DoctorAndAppointmentTest.php`

**Base compartida de la que depende**

- Modelos centrales y del hospital, `DomainRuleException`, `AuditTrail`, `UserRefSync` y `HospitalDatabase`
- Middleware `tenant`, `sesion` y `permiso`
- Login JWT (`AuthController`, `AuthService`) y `PermissionResolver`
- Seeders de roles, permisos y red de demostración
- Configuración propia: `config/nido.php`, `config/nido_catalogos.php`, `config/jwt.php`

## Instalación (Windows, PowerShell)

**1. Clonar e instalar dependencias**

```powershell
git clone https://github.com/bquiroao/examen-laravel13.git
cd examen-laravel13
composer install
copy .env.example .env
php artisan key:generate
php artisan jwt:secret
```

**2. Levantar PostgreSQL con Docker**

Se usa el puerto **5433** porque el 5432 suele estar ocupado por un PostgreSQL instalado en Windows.

```powershell
docker run --name pg-examen -e POSTGRES_PASSWORD=postgres -p 5433:5432 -d postgres:16
docker exec pg-examen psql -U postgres -c "CREATE DATABASE his_central"
docker exec pg-examen psql -U postgres -c "CREATE DATABASE his_hospital"
docker exec pg-examen psql -U postgres -d his_hospital -c "CREATE EXTENSION IF NOT EXISTS btree_gist"
```

**3. Configurar `.env`**

Reemplaza las líneas `DB_*` por:

```env
DB_CONNECTION=hospital

CENTRAL_DB_HOST=127.0.0.1
CENTRAL_DB_PORT=5433
CENTRAL_DB_DATABASE=his_central
CENTRAL_DB_USERNAME=postgres
CENTRAL_DB_PASSWORD=postgres

HOSPITAL_DB_HOST=127.0.0.1
HOSPITAL_DB_PORT=5433
HOSPITAL_DB_DATABASE=his_hospital
HOSPITAL_DB_USERNAME=postgres
HOSPITAL_DB_PASSWORD=postgres

JWT_TTL=15
JWT_REFRESH_TTL=20160
```

Comprueba la conexión:

```powershell
php artisan db:show --database=central
php artisan db:show --database=hospital
```

**4. Migrar**

El orden importa, y son tres comandos porque hay dos bases de datos:

```powershell
php artisan migrate --database=central --path=database/migrations/central --realpath
php artisan migrate --database=central --path=database/migrations/framework --realpath
php artisan migrate --database=hospital --path=database/migrations/hospital --realpath
```

Resultado esperado: 29 tablas en `his_central` y 30 en `his_hospital`.

## Pruebas

Las pruebas usan bases desechables, distintas de las de desarrollo, porque borran y recrean tablas en cada ejecución:

```powershell
docker exec pg-examen psql -U postgres -c "CREATE DATABASE his_central_test"
docker exec pg-examen psql -U postgres -c "CREATE DATABASE his_hospital_test"
docker exec pg-examen psql -U postgres -d his_hospital_test -c "CREATE EXTENSION IF NOT EXISTS btree_gist"
```

`phpunit.xml` espera esas bases en los puertos 55431 y 55433. Como aquí están en el 5433, se sobrescriben con variables de entorno antes de correr (valen solo para esa ventana de PowerShell):

```powershell
$env:CENTRAL_DB_PORT = "5433"
$env:HOSPITAL_DB_PORT = "5433"
php artisan test --filter="availability_lists|booking_rules|status_changes|past_confirmed|agenda_lists|doctor_only_sees"
```

Se ejecutan solo los 6 tests de citas. Los otros 5 de ese archivo prueban médicos y especialidades, un módulo que no se portó.

## Endpoints

Prefijo: `/api/v1`. Todas las rutas exigen el encabezado `X-Tenant-ID` con el identificador del hospital. Las de sesión además piden `Authorization: Bearer <token>`.

| Método | Ruta | Permiso |
|---|---|---|
| POST | `/auth/login` | — |
| GET | `/auth/me` | sesión |
| GET | `/auth/hospitales` | sesión |
| POST | `/auth/cambiar-hospital` | sesión |
| POST | `/auth/refresh` | sesión |
| POST | `/auth/logout` | sesión |
| GET | `/disponibilidad` | `citas.ver` |
| GET | `/citas` | `citas.ver` |
| POST | `/citas` | `citas.gestionar` |
| POST | `/citas/{appointment}/estado` | `citas.gestionar` |

## Cambios necesarios de Laravel 12 a 13

- **PHP 8.3 como mínimo.** Laravel 12 pedía 8.2.
- **PHPUnit 12.** El proyecto original usaba PHPUnit 11.
- **`routes/api.php` no existe en el esqueleto nuevo.** Hay que crearlo y registrarlo en `bootstrap/app.php` con `api:` y `apiPrefix: 'api/v1'`.
- **`bootstrap/app.php`** registra los alias de middleware (`tenant`, `sesion`, `permiso`) y el manejo de errores en JSON.
- **`config/auth.php`:** guard `api` con driver `jwt` y proveedor `users` apuntando a `CentralUser`.
- **`config/database.php`:** conexiones `central` y `hospital` en PostgreSQL, y `hospital` como conexión por defecto.
- **`AppServiceProvider`:** registro de `PermissionResolver` y `HospitalDatabase`.
- **Migraciones por defecto del esqueleto** (usuarios, caché, trabajos) eliminadas: el proyecto usa las de `database/migrations/framework`.
- **`phpunit.xml` propio**, porque el de Laravel 13 usa SQLite en memoria.
- **`tymon/jwt-auth` 2.3** declara compatibilidad con Laravel 13 y se instaló sin conflictos.

## Problemas comunes

| Síntoma | Causa y solución |
|---|---|
| `password authentication failed for user "postgres"` | Hay otro PostgreSQL en el puerto 5432. Revisa con `netstat -ano \| findstr :5432` y usa el puerto 5433 como en esta guía. |
| `Class "App\Services\Access\PermissionResolver" not found` | Falta copiar `app/Services/Access`. |
| `foreach() argument must be of type array\|object, null given` en `RbacCatalogSeeder` | Falta `config/nido.php`. |
| `Test directory ".../tests/Database" not found` | `phpunit.xml` declara la suite `Database` y la carpeta no existe. Créala o quita la suite del XML. |
| Error al cargar rutas al arrancar | Falta `routes/api.php`, que `bootstrap/app.php` ya referencia. |

## Origen

Código tomado del proyecto *Sistema Hospitalario Integrado* (Análisis de Sistemas II, 2026), módulo `citas-medicas`.