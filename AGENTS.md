# AGENTS.md — ON English Academy Portal (sgaon)

Guía para agentes y desarrolladores que trabajan en este repositorio.

## Stack

- **Backend**: Laravel 12, PHP 8.2+
- **Frontend**: Blade, Vite, CSS en `resources/css/app.css`
- **PDF**: DomPDF (`barryvdh/laravel-dompdf`)
- **Tests**: PHPUnit, `RefreshDatabase` en Feature tests
- **DB**: MySQL en producción; SQLite en tests

## Idioma y UI

- Interfaz de usuario en **español**.
- Patrones de vista: `module-head`, `page-title`, `card`, `data-table`, `fi-filter-bar`, `form-actions`.
- Layout principal: `resources/views/layouts/app.blade.php`.

## Autenticación y roles

| Rol | Constante | Acceso |
|-----|-----------|--------|
| Administrador | `User::ROLE_ADMIN` | Módulos operativos según sede |
| Master admin | `admin` + `is_master=true` | Todas las sedes + configuración global |
| Profesor | `teacher` | Asistencia, evaluaciones, dashboard |
| Alumno / Representante | `student` / `representative` | Portal |

- Middleware `role:admin`, `permission:*`, `campus.access`, `master.admin`.
- Master-only: campus, períodos, horarios, programas, exportaciones sensibles, usuarios admin.

## Aislamiento por sede (campus)

- **Fuente de verdad**: `App\Support\CampusScope`.
- Master (`isMasterAdmin()`) o `access_all_campuses=true`: sin filtro de sede.
- Admin de sede: filtro por `campus_user` pivot y/o `users.campus_id`.
- Autorización puntual: `CampusScope::userCanAccessCampus($user, $campusId)`.
- Queries: `CampusScope::apply($query, $user)`.
- Middleware `EnsureCampusAccess` valida modelos en rutas con binding.

## Patrones de código

- Controladores delgados; lógica reusable en `App\Support\*` o `App\Services\*`.
- Acciones administrativas: `AuditTrail::log($request, 'action.name', $model, $payload)`.
- Emails: `App\Mail\*` + vistas en `resources/views/emails/`.
- Validación en controlador con `$request->validate()` o métodos privados `validatedData()`.
- **Diff mínimo**: reutilizar convenciones del archivo vecino; no sobre-abstraer.

## Tests

- Ubicación: `tests/Feature/`, `tests/Unit/`.
- Ejecutar: `php artisan test`.
- Crear master en tests: `role=admin`, `is_master=true`, sync rol `admin` en `role_user`.
- `Mail::fake()` para assertions de correo.

## Rutas

- Definidas en `routes/web.php`.
- Grupo autenticado: `middleware(['auth', 'campus.access'])`.
- Recursos master dentro de `middleware('master.admin')`.

## Finanzas y precios EUR

- Precio base EUR: `programs.base_price_eur` (por defecto del programa) y `program_levels.base_price_eur` (override por nivel; si el nivel no tiene precio, se usa el del programa).
- Al inscribir un alumno se crea cargo `tuition` en EUR vía `App\Services\EnrollmentBillingService`.
- Cargos tienen `currency` (`USD` legacy, `EUR` matrícula nueva) y `last_reminder_sent_at`.
- Tasas BCV: `ExchangeRateService` sincroniza USD/VES y EUR/VES (`bcv:sync-rates`).
- Pagos contra cargos EUR: `PaymentCurrencyConverter::resolveForCharge()` (EUR o VES).
- Recordatorios: `finance:send-payment-reminders` (diario 07:00).
- Resumen: ruta `finance.summary` y helper `App\Support\FinanceSummary`.

## Despliegue

Tras cambios de rutas/vistas: `php artisan route:clear`, `php artisan view:clear`.
Migraciones: `php artisan migrate`.
Correo en producción: configurar `MAIL_*` en `.env`.
