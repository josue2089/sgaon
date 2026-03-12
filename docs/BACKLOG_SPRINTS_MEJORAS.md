# Backlog de Mejoras por Sprints (SGAON)

Última actualización: `2026-02-25`  
Estado global: `Completado (implementación)`

## Convenciones de estado
- `Pendiente`
- `En progreso`
- `Bloqueado`
- `Completado`

## Plantilla de seguimiento por ítem
- `Estado`:
- `Responsable`:
- `Fecha objetivo`:
- `Notas`:

---

## Sprint 1 — UX Operativa y Velocidad (1 semana)
Objetivo: reducir clics y tiempo operativo diario.

### S1-01 Wizard operativo académico
- Alcance: flujo único `Curso -> Grupo -> Sesiones -> Inscripciones`.
- Criterio de aceptación: admin completa ciclo sin salir del flujo.
- `Estado`: Completado
- `Responsable`: Codex
- `Fecha objetivo`: 2026-02-25
- `Notas`: Implementado módulo `Flujo MVP` con pasos encadenados y rutas dedicadas para crear curso, grupo, sesión e inscripciones en una sola vista (`operations/wizard`).

### S1-02 Asistencia masiva
- Alcance: acciones `Marcar todos presentes` y `Copiar última asistencia`.
- Criterio de aceptación: profesor registra grupo completo en menos de 1 minuto.
- `Estado`: Completado
- `Responsable`: Codex
- `Fecha objetivo`: 2026-02-25
- `Notas`: Agregadas acciones masivas en Asistencia: `Marcar todos presentes` y `Copiar última asistencia` (desde sesión previa del mismo grupo), con aplicación en formulario antes de guardar. Ajustado además a formato compacto tipo hoja de control para grupos de 20-30 alumnos.

### S1-03 Filtros operativos en módulos faltantes
- Alcance: filtros reales en `Grupos`, `Inscripciones`, `Sesiones`.
- Criterio de aceptación: filtros persistentes en URL + paginación.
- `Estado`: Completado
- `Responsable`: Codex
- `Fecha objetivo`: 2026-02-25
- `Notas`: Implementados filtros server-side (`q` + selects) en `GroupController`, `EnrollmentController`, `ClassSessionController`; formularios GET activos en vistas `index`; persistencia con `withQueryString`; estado vacío por filtros.

### S1-04 Alertas con deep-link
- Alcance: cada alerta abre directamente la vista/registro a resolver.
- Criterio de aceptación: resolución de alerta en 1 clic.
- `Estado`: Completado
- `Responsable`: Codex
- `Fecha objetivo`: 2026-02-25
- `Notas`: Notificaciones ahora redirigen por tipo de alerta: `finance` abre financiero filtrado por alumno (`student_id`) y `attendance` abre edición de alumno (admin) o módulo de asistencia (teacher).

---

## Sprint 2 — Consistencia de Datos y Reglas de Negocio (1 semana)
Objetivo: evitar errores operativos y elevar calidad de datos.

### S2-01 Catálogos controlados
- Alcance: estandarizar `periodo`, `horario`, `status` con valores predefinidos.
- Criterio de aceptación: campos críticos sin texto libre.
- `Estado`: Completado
- `Responsable`: Codex
- `Fecha objetivo`: 2026-02-25
- `Notas`: Catálogos centralizados en `config/academic.php` para `group_periods`, `group_schedules`, `group_statuses`; formularios de grupo y wizard migrados a selects; validación backend con `Rule::in`.

### S2-02 Validaciones de reglas cruzadas
- Alcance:
  - impedir inscripción en grupo inactivo,
  - impedir sesión fuera de rango del grupo.
- Criterio de aceptación: validaciones activas con mensajes claros al usuario.
- `Estado`: Completado
- `Responsable`: Codex
- `Fecha objetivo`: 2026-02-25
- `Notas`: Validaciones activas en controladores y wizard: inscripción bloqueada si grupo está inactivo, y sesiones bloqueadas fuera de rango `start_date/end_date` del grupo (con mensajes explícitos).

### S2-03 Capacidad y sobrecupo
- Alcance: capacidad por grupo (o por curso con override).
- Criterio de aceptación: no permitir sobrecupo + ocupación real.
- `Estado`: Completado
- `Responsable`: Codex
- `Fecha objetivo`: 2026-02-25
- `Notas`: Agregada capacidad por grupo (migración + formularios + wizard), bloqueo de sobrecupo en inscripciones (módulo y wizard), y ocupación real en Cursos calculada con relación `inscritos/capacidad`.

### S2-04 Auditoría contextual por entidad
- Alcance: timeline de cambios por `Alumno`, `Profesor`, `Grupo`.
- Criterio de aceptación: trazabilidad visible sin depender del reporte global.
- `Estado`: Completado
- `Responsable`: Codex
- `Fecha objetivo`: 2026-02-25
- `Notas`: Se agregaron logs CRUD en `StudentController`, `TeacherController`, `GroupController` y timeline contextual en vistas de edición (`Alumno`, `Profesor`, `Grupo`) usando parcial reutilizable.

---

## Sprint 3 — Tableros y Monitoreo Académico (1 semana)
Objetivo: mejorar seguimiento académico y decisiones operativas.

### S3-01 Dashboard académico semanal
- Alcance: agenda semanal + navegación diaria de clases.
- Criterio de aceptación: visualización por día/grupo/docente.
- `Estado`: Completado
- `Responsable`: Codex
- `Fecha objetivo`: 2026-02-25
- `Notas`: Dashboard actualizado con agenda semanal (Lun-Dom), conteo de sesiones por día y navegación directa por fecha, manteniendo detalle diario por grupo/docente.

### S3-02 KPIs avanzados con fuente real
- Alcance: asistencia por nivel/docente/grupo.
- Criterio de aceptación: KPI solo con dato real o `N/D` explícito.
- `Estado`: Completado
- `Responsable`: Codex
- `Fecha objetivo`: 2026-02-25
- `Notas`: Dashboard incorpora KPIs de asistencia con datos reales por `nivel`, `docente` y `grupo` (top 5), usando agregados sobre `attendance_records`.

### S3-03 Estados vacíos y UX de excepciones
- Alcance: pantallas vacías con acciones sugeridas.
- Criterio de aceptación: sin vistas “muertas” en módulos clave.
- `Estado`: Completado
- `Responsable`: Codex
- `Fecha objetivo`: 2026-02-25
- `Notas`: Estados vacíos implementados en reportes (asistencia/pagos), financiero (cargos/pagos) y asistencia (sin sesión seleccionada) con mensajes accionables.

---

## Sprint 4 — Finanzas Operativas (1 semana)
Objetivo: reducir trabajo manual en cobranza y mejorar control.

### S4-01 Cargos recurrentes por inscripción activa
- Alcance: generación automática mensual.
- Criterio de aceptación: job programado genera cargos correctos.
- `Estado`: Completado
- `Responsable`: Codex
- `Fecha objetivo`: 2026-02-25
- `Notas`: Comando `finance:generate-recurring-charges` implementado con anti-duplicado por alumno/mes/grupo y calendarizado mensual (día 1, 06:10) en `routes/console.php`.

### S4-02 Control de mora mejorado
- Alcance: días de atraso, semáforo y priorización.
- Criterio de aceptación: listado ordenado por criticidad.
- `Estado`: Completado
- `Responsable`: Codex
- `Fecha objetivo`: 2026-02-25
- `Notas`: Financiero prioriza por `days_overdue` (desc), agrega semáforo por días de atraso y KPI de mora crítica (30+ días).

### S4-03 Conciliación básica y recibos
- Alcance: consistencia entre parcial/total/saldo.
- Criterio de aceptación: estado de cuenta coherente en módulos y reportes.
- `Estado`: Completado
- `Responsable`: Codex
- `Fecha objetivo`: 2026-02-25
- `Notas`: Reconciliación implementada con `FinanceReconcile` + comando `finance:reconcile-charges` (programado diario 05:50); vistas y CSV de pagos muestran `pagado`/`saldo` consistentes.

---

## Sprint 5 — Reportería y Exportaciones (1 semana)
Objetivo: escalar productividad de análisis y operación.

### S5-01 Filtros guardados (presets)
- Alcance: guardar y reutilizar filtros por usuario/rol.
- Criterio de aceptación: cargar reporte frecuente en 1 clic.
- `Estado`: Completado
- `Responsable`: Codex
- `Fecha objetivo`: 2026-02-25
- `Notas`: Presets por usuario implementados para `reports.attendance` y `reports.payments` (guardar, listar, aplicar y eliminar en 1 clic).

### S5-02 Exportaciones robustas
- Alcance: CSV/XLS en asistencia, pagos, mora, grupos.
- Criterio de aceptación: export consistente con filtros activos.
- `Estado`: Completado
- `Responsable`: Codex
- `Fecha objetivo`: 2026-02-25
- `Notas`: Exportes CSV aplican filtros activos en asistencia/pagos; se agregó export de grupos y export de mora desde financiero.

### S5-03 Exportación asíncrona
- Alcance: colas para reportes pesados + notificación al completar.
- Criterio de aceptación: UI no bloqueada durante exportación.
- `Estado`: Completado
- `Responsable`: Codex
- `Fecha objetivo`: 2026-02-25
- `Notas`: Exportación asíncrona implementada para asistencia/pagos con cola (`GenerateReportExportJob`), tracking en `report_exports`, estado visible en UI y descarga al completar (requiere worker de cola activo).

---

## Prioridad transversal
- `P0`: Sprint 1 + Sprint 2
- `P1`: Sprint 3 + Sprint 4
- `P2`: Sprint 5

## Dependencias de negocio
1. Catálogo oficial de `periodo/horario/status`.
2. Política de capacidad (`por grupo` vs `por curso`).
3. Reglas de cobranza recurrente (corte, prorrateo, mora).

## Métricas de éxito sugeridas
1. Tiempo de registro de asistencia por sesión.
2. Tasa de errores de validación por módulo.
3. Porcentaje de grupos con sobrecupo.
4. Recuperación de mora en 30 días.
5. Tiempo promedio de generación de reportes.
