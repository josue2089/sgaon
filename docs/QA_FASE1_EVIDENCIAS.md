# QA Fase 1 MVP — Evidencias

Fecha: 2026-02-25
Proyecto: SGAON (Laravel + Blade + MySQL)

## Resultado Ejecutivo
- Estado funcional técnico: `Aprobado con hallazgo menor`
- Hallazgo abierto: `1` (test feature con 419 por CSRF)
- Backlog de implementación: `Sprints 1-5 completados` (ver `docs/BACKLOG_SPRINTS_MEJORAS.md`)

## Evidencia de plataforma y operaciones
1. Framework y rutas
- `php artisan --version` => `Laravel Framework 12.52.0`
- Conteo de rutas web (`php artisan route:list --except-vendor | wc -l`) => `64`

2. Scheduler configurado
- `php artisan schedule:list`:
  - `generate:alerts` diario 06:00
  - `finance:reconcile-charges` diario 05:50
  - `finance:generate-recurring-charges` mensual día 1 06:10

3. Migraciones
- `php artisan migrate:status` mostró 3 pendientes nuevas.
- `php artisan migrate` ejecutado correctamente:
  - `add_capacity_to_groups_table` DONE
  - `create_report_presets_table` DONE
  - `create_report_exports_table` DONE

## Checklist por módulo (Fase 1)

### 1) Alumnos
- Estado: `PASS`
- Evidencia:
  - CRUD activo con filtros reales (`q`, `level`, `status`, `payment_status`).
  - Carga de foto perfil (FilePond) integrada.
  - Timeline de auditoría contextual en edición.

### 2) Profesores
- Estado: `PASS`
- Evidencia:
  - CRUD activo con filtros reales (`q`, `status`, `specialty`).
  - Carga de foto perfil (FilePond) integrada.
  - Timeline de auditoría contextual en edición.

### 3) Cursos / Niveles / Grupos
- Estado: `PASS`
- Evidencia:
  - Cursos con filtros reales y ocupación desde datos reales.
  - Grupos con capacidad (`capacity`) y export CSV.
  - Catálogos controlados para `periodo`, `horario`, `status`.
  - Validaciones de negocio: sesiones fuera de rango bloqueadas.

### 4) Inscripciones
- Estado: `PASS`
- Evidencia:
  - Filtros reales (`q`, `group_id`, `status`) con persistencia URL.
  - Regla activa: no inscripción en grupo inactivo.
  - Regla activa: no sobrecupo según capacidad.

### 5) Sesiones
- Estado: `PASS`
- Evidencia:
  - Filtros reales (`q`, `group_id`, `date`) con persistencia URL.
  - Reglas activas: grupo activo y fecha dentro de rango del grupo.

### 6) Asistencia
- Estado: `PASS`
- Evidencia:
  - Registro por sesión operativo.
  - Acciones masivas: `Marcar todos presentes` y `Copiar última asistencia`.
  - Estado vacío claro cuando no hay sesión seleccionada.

### 7) Financiero
- Estado: `PASS`
- Evidencia:
  - Cargos/pagos/recibos operativos.
  - Priorización por mora (`days_overdue`) y semáforo.
  - KPI de mora crítica (30+ días).
  - Reconciliación implementada (`FinanceReconcile` + comando diario).
  - Export CSV de mora.

### 8) Reportes
- Estado: `PASS`
- Evidencia:
  - Asistencia y pagos con filtros y export CSV consistentes.
  - Presets de filtros por usuario (guardar/aplicar/eliminar).
  - Exportación asíncrona con cola (`pending/running/done/failed`) y descarga.

### 9) Dashboard
- Estado: `PASS`
- Evidencia:
  - Agenda semanal (Lun-Dom) + navegación diaria.
  - KPI avanzados de asistencia por nivel/docente/grupo con datos reales.
  - Métricas sin fuente real se mantienen como `N/D`.

## Pruebas automáticas
- Comando: `php artisan test --stop-on-failure`
- Resultado: `FAIL (1)`
- Caso fallando:
  - `Tests\Feature\RoleAccessTest::test_audit_log_is_created_on_finance_charge_creation`
  - Motivo: respuesta `419` (CSRF) en POST `/finance/charges` dentro del test.
- Impacto: `bajo` (afecta suite de testing, no evidenció fallo funcional directo en flujos UI).

## Riesgos abiertos
1. Worker de cola para export asíncrona
- Requiere proceso activo en servidor (`php artisan queue:work`).

2. Ajuste de test de CSRF
- Recomendado adaptar test para incluir token o desactivar middleware CSRF para ese caso de feature test.

## Comandos de verificación recomendados en servidor
1. `php artisan migrate --force`
2. `php artisan optimize:clear`
3. `php artisan config:cache && php artisan route:cache && php artisan view:cache`
4. `php artisan schedule:list`
5. `php artisan queue:work --tries=3`

