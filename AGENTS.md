# AGENTS.md — ON English Academy Portal (sgaon)

Guía para agentes y desarrolladores que trabajan en este repositorio.

## Ubicación en el monorepo

- Raíz del proyecto: `/Volumes/DevSSD/Developer/Projects/WEBS/OnEnglish/`
  - `sgaon/` — aplicación Laravel (este directorio, el código productivo).
  - `docs/` — documentación no-código: matriz de competencias, propuesta, SQL histórico (`sgaon.sql`), matrículas Picacho.
  - `recursos/` y `resources/` (raíz) — materiales de marketing/branding (BIO 2026, flyers, historias). No confundir con `sgaon/resources/` (Blade + assets).
  - `UI_UX Diseño Plataforma Académica/` — mockups y referencias de diseño.

## Stack

- **Backend**: Laravel 12, PHP 8.2+
- **Frontend**: Blade, Vite, CSS en `resources/css/app.css`
- **PDF**: DomPDF (`barryvdh/laravel-dompdf`)
- **Excel**: PhpSpreadsheet (`phpoffice/phpspreadsheet`)
- **Formato de montos**: punto para miles y coma para decimales en toda la UI (`App\Support\MoneyFormat::number`, `usd`, `eur`, `ves`). Usar `MoneyFormat::raw()` solo en atributos `data-*` para JS.
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
- Al registrar un pago (`PaymentRegistrationService`) se envía `PaymentReceiptMail` al alumno y representantes con PDF adjunto (`ReceiptPdfService`).
- Master admin puede registrar pagos desde la ficha del alumno (`students.payments.store`).
- Master admin puede crear cargos desde la ficha del alumno (`students.charges.store`).
- Recordatorios: `finance:send-payment-reminders` (diario 07:00).
- Resumen: ruta `finance.summary` y helper `App\Support\FinanceSummary`.
- Resumen financiero: filtros por fecha, moneda y sede; exportación CSV, Excel (`.xlsx`) y PDF de cargos creados, cobros realizados y proyección (`charges_*`, `payments_*`, `projection_*` con sufijo `csv`, `xlsx` o `pdf`).

## Calendario de cursos y feriados

- Generación/recálculo: `App\Support\CoursePlanner::sync($course, true)`; salta feriados activos (`Holiday::forCampus`, globales si `campus_id` es null).
- Guardar/borrar un feriado recalcula los cursos activos (`App\Services\HolidayCalendarSync`); botón "Recalcular calendario" por curso; comando `courses:apply-holidays`.
- Sesiones protegidas (nunca se borran ni se mueven al recalcular): con asistencia, con recuperativas o `date_locked`.
- Mover una clase a mano (`App\Services\SessionRescheduler`, desde `sessions.edit` o desde Asistencia): marca `date_locked` y guarda `rescheduled_from`; esa fecha original queda excluida del horario y la clase movida ocupa su lugar (el total no crece).
- Índice único `(group_id, session_date, starts_at)` en `class_sessions`: al mover, `SessionRescheduler::freeSlot` reemplaza la clase planificada sin asistencia o rechaza si la tiene. Horas siempre `H:i:s`.
- Cascada (`SessionRescheduler::rescheduleWithCascade`, casilla "Correr también las clases siguientes" en Asistencia): la clase entra en la fecha y las siguientes que chocan pasan al próximo día del horario (sin feriados) con su asistencia, tema y notas; luego se recalcula el curso.
- Clases con asistencia en un feriado NO ocupan lugar: se mantienen, se avisan y se agrega la clase al final. No cambiar esto sin revisar `SendLevelRenewalReminders` (usa `courses.end_date`).
- El recálculo solo se bloquea si hay clases con asistencia (no movidas a mano) ANTES de la fecha de inicio del curso; se corrige ajustando la fecha de inicio. Clases sin asistencia antes del inicio se eliminan al recalcular.
- Mover una clase y recalcular el curso es atómico (`SessionRescheduler::afterMove` dentro de la transacción): si el recálculo falla, se revierte todo y se muestra el motivo. Nunca tragarse ese error: dejaría una clase planificada borrada sin reponer.

## Módulos funcionales

- **Portal**: estudiantes (`role:student`, `permission:portal.student.view`) y representantes (`role:representative`) — `PortalController`, vistas en `resources/views/portal/`.
- **Asistencia**: `AttendanceController` + `ClassSession` + `AttendanceRecord`. Permiso: `attendance.manage`. Reportes exportables (CSV/PDF).
- **Evaluaciones y notas**: `CourseGradeController`, `GradeEvaluationSet`, `GradeEntry`, rubrica en `App\Support\GradeRubric`, autorización en `GradeAuthorization`. Permiso: `grades.manage`.
- **Recuperaciones (makeups)**: `MakeupRequest`, `MakeupBooking`, `MakeupSession`, adjuntos; motor en `App\Support\MakeupRecoveryEngine`; controlador `MakeupRecoveryController`.
- **Alertas**: motor `App\Support\AlertEngine` + modelo `Alert`, generadas por comando `alerts:generate` (`GenerateAlerts`).
- **Operation Wizard**: `OperationWizardController` — flujo guiado de operaciones (inscripción, matrícula, cambios).
- **Importaciones**:
  - Estudiantes CCL: `ImportStudentsCcl` + `CclActiveStudentsSpreadsheet`.
  - Estudiantes históricos (varios formatos): `ImportStudentsHistorical*`, `HistoricalStudentImportService`.
  - Cursos: `ImportCoursesMatrix`.
  - Inscripciones históricas: `ImportEnrollmentsHistorical`.
  - Ledger financiero: `ImportFinanceLedger` + `HistoricalLedgerSpreadsheet`.
  - Bulk estudiantes: `StudentBulkImportService`.
- **Auditoría**: `AuditLog` + `AuditTrail::log()`. Permiso: `audit.view`.
- **Reportes**: `ReportController`, exports en CSV/XLSX/PDF vía PhpSpreadsheet y DomPDF.

## Comandos y programación

Comandos artisan personalizados (`app/Console/Commands/`):
- `bcv:sync-rates` — `SyncBcvRatesCommand` (BCV USD/VES, EUR/VES).
- `finance:send-payment-reminders` — recordatorios diarios 07:00.
- `alerts:generate` — genera alertas del sistema.
- `finance:reconcile-charges` — reconciliación de cargos.
- `renewals:send-reminders` — recordatorios de renovación de nivel.
- `charges:generate-recurring` — cargos recurrentes.
- Backfills: `charges:backfill-academic-context`, `course-levels:backfill`, `program-levels:backfill`.
- Data reconcile: `data:reconcile` (`DataReconcile`).

## Frontend / assets

- Blade en `resources/views/` organizado por módulo.
- Estilos en `resources/css/app.css`; JS en `resources/js/`.
- Build: `npm run dev` (Vite) o incluido en `composer dev`.

## Base de datos

- Migraciones en `database/migrations/` — timestamps 2024/2025/2026.
- MySQL en producción; SQLite en tests (`RefreshDatabase`).
- Dump histórico: `docs/sgaon.sql`.

## Despliegue

Tras cambios de rutas/vistas: `php artisan route:clear`, `php artisan view:clear`.
Migraciones: `php artisan migrate`.
Correo en producción: configurar `MAIL_*` en `.env`.
Desarrollo local: `composer dev` levanta `serve`, `queue:listen`, `pail` y `vite` en paralelo.
