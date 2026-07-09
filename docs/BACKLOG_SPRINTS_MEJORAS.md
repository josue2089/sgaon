# Backlog de Mejoras por Sprints (SGAON)

Última actualización: `2026-03-18`  
Estado global: `Completado (implementación) + ajustes operativos`

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

## Ajustes Operativos — Catálogos Base (2026-03-18)
Objetivo: sacar `períodos` y `horarios` del config estático y pasarlos a CRUD administrable.

### AO-01 Admin master para catálogos
- Alcance: restringir CRUD de catálogos a `admin master`.
- Criterio de aceptación: admin normal recibe `403`, admin master accede.
- `Estado`: Completado
- `Responsable`: Codex
- `Fecha objetivo`: 2026-03-18
- `Notas`: agregado `is_master` en `users`, helper `isMasterAdmin()` y middleware `master.admin`.

### AO-02 CRUD de períodos
- Alcance: crear, editar, listar y eliminar períodos informativos.
- Criterio de aceptación: catálogo usable desde UI con valores tipo `2026-Q1`.
- `Estado`: Completado
- `Responsable`: Codex
- `Fecha objetivo`: 2026-03-18
- `Notas`: implementado módulo `Periodos` con tabla `periods`, vistas Blade y navegación en menú `Más` solo para master admin.

### AO-03 CRUD de horarios
- Alcance: crear horarios con días de semana + hora inicio + hora final.
- Criterio de aceptación: selección multidía y franja horaria persistida en BD.
- `Estado`: Completado
- `Responsable`: Codex
- `Fecha objetivo`: 2026-03-18
- `Notas`: implementado módulo `Horarios` con tabla `schedule_templates`, selección de días, rango horario y listado administrable.

### AO-04 Integración con creación de grupos
- Alcance: grupos y flujo MVP consumen catálogos desde BD.
- Criterio de aceptación: `Periodo` y `Horario` ya no salen de `config/academic.php`.
- `Estado`: Completado
- `Responsable`: Codex
- `Fecha objetivo`: 2026-03-18
- `Notas`: `GroupController` y `OperationWizardController` ahora cargan opciones activas desde `periods` y `schedule_templates`.

### AO-05 Curso operativo con planificación automática
- Alcance: `Curso` pasa a manejar profesor, período, horario, fecha inicial, duración y generación automática de sesiones.
- Criterio de aceptación: al guardar un curso operativo se crea/sincroniza su grupo interno y se planifican sesiones hasta cubrir horas académicas.
- `Estado`: Completado
- `Responsable`: Codex
- `Fecha objetivo`: 2026-03-18
- `Notas`: agregados campos operativos a `courses`, servicio `CoursePlanner`, cálculo basado en hora académica de 45 min y fecha final automática.

### AO-06 Panel de detalle de curso
- Alcance: vista detalle con profesor, estudiantes, sesiones completadas/pendientes, tabla de sesiones y acceso rápido a asistencia.
- Criterio de aceptación: un admin puede entrar a un curso y operar desde esa vista.
- `Estado`: Completado
- `Responsable`: Codex
- `Fecha objetivo`: 2026-03-18
- `Notas`: creada ruta/vista `courses.show` con ficha del curso, alta de estudiantes y tabla de sesiones/programa.

### AO-07 Programa de sesión desde asistencia
- Alcance: profesor/admin indica tema, avance del programa y observación desde la asistencia del día.
- Criterio de aceptación: esos datos se guardan en la sesión y se reflejan en detalle de curso.
- `Estado`: Completado
- `Responsable`: Codex
- `Fecha objetivo`: 2026-03-18
- `Notas`: `AttendanceController` y `ClassSessionController` ahora persisten `topic`, `program_status` y `program_notes`.

### AO-08 Listados tabulares de cursos y alumnos
- Alcance: reemplazar cards por tablas más legibles en `Cursos` y `Alumnos`.
- Criterio de aceptación: lectura horizontal más clara y acceso rápido a acciones.
- `Estado`: Completado
- `Responsable`: Codex
- `Fecha objetivo`: 2026-03-18
- `Notas`: vistas `courses.index` y `students.index` convertidas a tablas responsivas.

### AO-09 Cursos como reemplazo operativo de grupos
- Alcance: el detalle del curso se convierte en la entrada principal de operación y `Grupos`/`Sesiones` salen del header.
- Criterio de aceptación: navegación superior centrada en `Cursos` sin accesos redundantes a módulos internos.
- `Estado`: Completado
- `Responsable`: Codex
- `Fecha objetivo`: 2026-03-18
- `Notas`: eliminados `Grupos` y `Sesiones` del menú `Más` y de accesos visibles en `Flujo MVP`/`Detalle de curso`; se mantienen rutas internas por compatibilidad operativa.

### AO-10 Refactor financiero fase 1
- Alcance: agregar contexto académico a cargos y mejorar trazabilidad de pagos sin romper conciliación actual.
- Criterio de aceptación: cargos pueden vincularse a inscripción/curso/grupo/período; reportes muestran ese contexto.
- `Estado`: Completado
- `Responsable`: Codex
- `Fecha objetivo`: 2026-03-18
- `Notas`: agregadas migraciones para contexto académico en `charges` y campos operativos en `payments`; creado comando `finance:backfill-charge-context`; `FinanceController` y reportes actualizados para crear y visualizar cargos con `enrollment_id`, curso, grupo y período.

### AO-11 UX financiera por alumno
- Alcance: filtrar en tiempo real inscripciones y cargos según el alumno seleccionado en formularios financieros.
- Criterio de aceptación: al elegir alumno, solo aparecen sus inscripciones/cargos; al elegir cargo se autocompleta contexto básico.
- `Estado`: Completado
- `Responsable`: Codex
- `Fecha objetivo`: 2026-03-18
- `Notas`: `finance.index` ahora filtra selects en cliente por `student_id`, agrega búsqueda textual en inscripciones/cargos y autocompleta monto sugerido con saldo del cargo seleccionado.

### AO-12 Payment allocations fase 2
- Alcance: permitir que un pago se aplique a varios cargos sin romper pagos históricos.
- Criterio de aceptación: registrar un pago con múltiples cargos seleccionados y recalcular saldo/status correctamente.
- `Estado`: Completado
- `Responsable`: Codex
- `Fecha objetivo`: 2026-03-18
- `Notas`: creada tabla `payment_allocations`, modelo dedicado y conciliación híbrida (`payments.charge_id` legacy + allocations nuevas); flujo financiero actualizado para distribuir un pago entre varios cargos del mismo alumno.

### AO-13 Detalle de recibo con cargos aplicados
- Alcance: mostrar en el recibo exactamente a qué cargos se aplicó el pago y por cuánto monto.
- Criterio de aceptación: desde financiero se puede abrir un recibo y ver tabla de cargos impactados con contexto académico.
- `Estado`: Completado
- `Responsable`: Codex
- `Fecha objetivo`: 2026-03-18
- `Notas`: agregado `finance.receipts.show`, vista `finance/receipt.blade.php` y enlaces desde pagos recientes; soporta pagos legacy y pagos con `payment_allocations`.

### AO-14 Historial financiero por alumno
- Alcance: construir línea de tiempo por alumno con cargos, pagos y aplicaciones relacionadas.
- Criterio de aceptación: desde financiero se puede abrir el historial del alumno y navegar a sus recibos.
- `Estado`: Completado
- `Responsable`: Codex
- `Fecha objetivo`: 2026-03-18
- `Notas`: agregado `finance.students.history`, timeline financiera en `finance/student-history.blade.php` y deep-links desde cuentas por cobrar/pagos recientes.

### AO-15 Impresión y PDF de recibos
- Alcance: permitir imprimir un recibo y exportarlo como PDF descargable.
- Criterio de aceptación: desde el detalle del recibo hay acción `Imprimir` y acción `Exportar PDF` con el mismo detalle de cargos aplicados.
- `Estado`: Completado
- `Responsable`: Codex
- `Fecha objetivo`: 2026-03-18
- `Notas`: instalada dependencia `barryvdh/laravel-dompdf`, agregada ruta `finance.receipts.pdf`, vista `finance/receipt-pdf.blade.php` y estilos base de impresión.

### AO-16 Resumen acumulado en historial financiero
- Alcance: mostrar resumen financiero del alumno antes de la línea de tiempo.
- Criterio de aceptación: el historial presenta total facturado, total cobrado, saldo pendiente y cargos vencidos.
- `Estado`: Completado
- `Responsable`: Codex
- `Fecha objetivo`: 2026-03-18
- `Notas`: `FinanceController` ahora calcula resumen acumulado y `finance/student-history.blade.php` lo renderiza en cards superiores.

### AO-17 Branding ON English en PDF de recibo
- Alcance: incluir logo y marca ON English en la exportación PDF del recibo.
- Criterio de aceptación: el PDF descargado muestra identidad visual básica de la academia en cabecera.
- `Estado`: Completado
- `Responsable`: Codex
- `Fecha objetivo`: 2026-03-18
- `Notas`: el PDF usa `logo.png` embebido como data URI para compatibilidad con DomPDF y cabecera de marca `ON English`.

### AO-18 Filtro por rango en historial financiero
- Alcance: permitir acotar el historial financiero del alumno por fecha inicial y final.
- Criterio de aceptación: el rango afecta línea de tiempo y resumen acumulado, con estado persistente en URL.
- `Estado`: Completado
- `Responsable`: Codex
- `Fecha objetivo`: 2026-03-18
- `Notas`: `studentHistory()` valida `start_date/end_date`, filtra cargos/pagos en backend y la vista expone formulario GET con limpieza rápida.

### AO-19 Escala general de niveles para cursos
- Alcance: crear una escala secuencial de 12 niveles relacionada con cursos y utilizable en seguimiento del alumno.
- Criterio de aceptación: cada curso puede asociarse a un nivel de escala y el sistema reconoce su posicion dentro de 12.
- `Estado`: Completado
- `Responsable`: Codex
- `Fecha objetivo`: 2026-03-18
- `Notas`: creada tabla `course_levels`, relacion `courses.course_level_id` y siembra inicial de 12 niveles generales basada en la linea operativa `Primary 1-6 + High School 1-6`.

### AO-20 Ficha integral de alumno
- Alcance: vista de detalle del alumno con progreso de nivel, curso actual, historico de cursos, pagos, cargos y auditoria.
- Criterio de aceptación: desde el modulo `Alumnos` se abre una ficha completa con informacion academica y financiera consolidada.
- `Estado`: Completado
- `Responsable`: Codex
- `Fecha objetivo`: 2026-03-18
- `Notas`: agregada ruta `students.show`, nueva vista `students/show.blade.php`, resumen de progresion `x/12`, siguiente nivel, fecha fin, historico de cursos y resumen financiero.

### AO-21 Recordatorio automatico de renovacion de nivel
- Alcance: generar recordatorio 5 dias antes de la finalizacion del curso actual para inscripcion al siguiente nivel.
- Criterio de aceptación: comando diario detecta alumnos con curso por vencer y abre alerta `level_renewal`.
- `Estado`: Completado
- `Responsable`: Codex
- `Fecha objetivo`: 2026-03-18
- `Notas`: agregado comando `levels:send-renewal-reminders` y programacion diaria en `routes/console.php`; las alertas abren la ficha del alumno desde el header.

### AO-22 Backfill de escala para cursos existentes
- Alcance: asignar `course_level_id` a cursos ya creados usando reglas de matching por nombre/codigo.
- Criterio de aceptación: existe comando ejecutable con modo `dry-run` para rellenar cursos legacy sin alterar cursos ya mapeados.
- `Estado`: Completado
- `Responsable`: Codex
- `Fecha objetivo`: 2026-03-18
- `Notas`: agregado comando `levels:backfill-course-levels` con heuristica para `Primary 1-6` y `HS 1-6`.

### AO-23 Email real para renovacion de nivel
- Alcance: convertir la alerta `level_renewal` en envio por correo al alumno.
- Criterio de aceptación: al generarse el recordatorio, si el alumno tiene email, el sistema envia correo y registra fecha de envio para no duplicar.
- `Estado`: Completado
- `Responsable`: Codex
- `Fecha objetivo`: 2026-03-18
- `Notas`: agregado `LevelRenewalReminderMail`, vista `emails/level-renewal-reminder.blade.php` y campo `alerts.emailed_at` para control de no duplicacion.

### AO-24 Plantilla de email alineada a ON English
- Alcance: mejorar el email de renovacion para que refleje branding y jerarquia visual de la academia.
- Criterio de aceptación: correo con cabecera de marca, jerarquia clara, resumen del nivel actual/siguiente y mensaje de accion.
- `Estado`: Completado
- `Responsable`: Codex
- `Fecha objetivo`: 2026-03-18
- `Notas`: rediseñada plantilla `emails/level-renewal-reminder.blade.php` con header azul, logo, cards de progreso y bloque de llamada a la accion.

### AO-25 Panel admin de recordatorios enviados
- Alcance: vista administrativa para revisar recordatorios de renovacion generados y enviados por email.
- Criterio de aceptación: listado filtrable por estado, envio y fecha con acceso rapido a la ficha del alumno.
- `Estado`: Completado
- `Responsable`: Codex
- `Fecha objetivo`: 2026-03-18
- `Notas`: agregado reporte `reports.level-renewals`, ruta dedicada, filtros por estado/email/rango y enlace desde configuracion del header.

### AO-26 Catálogos de configuración y menú admin
- Alcance: consolidar acceso admin master a `Campus`, `Períodos`, `Horarios`, `Niveles` y `Escalas` dentro de un dropdown `Configuración`.
- Criterio de aceptación: los cinco CRUD son navegables desde menú y `Flujo MVP` desaparece del header.
- `Estado`: Completado
- `Responsable`: Codex
- `Fecha objetivo`: 2026-03-19
- `Notas`: creados CRUD para `campuses`, `academic-levels`, `course-levels`; `periods` y `schedules` ajustados para no limitar por sede a master admin; menú principal actualizado con dropdown `Configuración`.

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

---

## Ajustes recientes

### AO-27 Configuración masiva y protección de campus
- Alcance: proteger eliminación de `Campus` con operación viva y convertir catálogos de configuración a tablas para administración masiva.
- Criterio de aceptación: un campus con entidades relacionadas no se elimina; `Campus`, `Períodos`, `Horarios`, `Niveles` y `Escalas` se administran en tablas con acciones rápidas.
- `Estado`: Completado
- `Responsable`: Codex
- `Fecha objetivo`: 2026-03-19
- `Notas`: `CampusController` valida dependencias operativas antes de borrar; vistas índice de catálogos migradas de cards a tablas y menú consolidado en dropdown `Configuración`.

### AO-28 Selección masiva de alumnos en detalle de curso
- Alcance: reemplazar `select multiple` nativo por modal con tabla, búsqueda y checkboxes para inscribir alumnos en cursos.
- Criterio de aceptación: el admin puede buscar, seleccionar múltiples alumnos y agregarlos desde el detalle del curso con mejor legibilidad.
- `Estado`: Completado
- `Responsable`: Codex
- `Fecha objetivo`: 2026-03-19
- `Notas`: el listado de candidatos ahora toma alumnos activos del campus del curso, excluye alumnos ya inscritos, muestra nivel actual y la tabla de inscritos permite retirar alumnos sin romper historial si ya tienen asistencia/cargos.

### AO-29 Vista operativa de profesores
- Alcance: convertir el listado de profesores a tabla y crear ficha de detalle con cursos, estudiantes y progreso operativo.
- Criterio de aceptación: el admin ve el listado de docentes en tabla y puede abrir una ficha con métricas reales, cursos asignados, alumnos vinculados y auditoría.
- `Estado`: Completado
- `Responsable`: Codex
- `Fecha objetivo`: 2026-03-19
- `Notas`: se agregó `teachers.show`, tabla de docentes y ficha con métricas de sesiones, asistencia promedio, cursos y estudiantes relacionados.

### AO-30 Programas académicos y plantillas por clase
- Alcance: introducir `programs`, `program_levels` y `program_level_lessons`, migrar cursos/sesiones/alumno/recordatorios a la nueva estructura y dejar `course_levels` como legado compatible.
- Criterio de aceptación: el admin puede gestionar programas, niveles y clases base; los cursos se crean con programa+nivel real; las sesiones se generan con contenido planificado y los recordatorios usan el nivel real del programa.
- `Estado`: Completado
- `Responsable`: Codex
- `Fecha objetivo`: 2026-04-13
- `Notas`: se agregó módulo `Programas` en configuración, seeds base de Pre-Primary/Primary/HighSchool, planificador de sesiones con contenido distribuido, detalle de curso/alumno actualizado y comando `programs:backfill-course-program-levels`.

### AO-31 Precios EUR por nivel y estado financiero por alumno
- Alcance: definir precio base EUR en niveles de programa, generar cargo automático al inscribir, mostrar equivalente Bs vía BCV, abonos parciales con nueva fecha de vencimiento, recordatorios por correo y resumen financiero consolidado.
- Criterio de aceptación: nivel con precio EUR crea cargo al inscribir; pagos EUR/VES concilian saldo; resumen muestra facturado/cobrado/pendiente/proyección; recordatorios programados.
- `Estado`: Completado
- `Responsable`: Codex
- `Fecha objetivo`: 2026-07-09
- `Notas`: migraciones `base_price_eur` y `charges.currency`, `EnrollmentBillingService`, `FinanceSummary`, comando `finance:send-payment-reminders`, vista `finance/summary`, tests en `EurPricingFinanceTest`.
