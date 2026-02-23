# Manual Breve de Uso - SGA ON English MVP

## Acceso inicial
- URL local: `http://127.0.0.1:8000`
- Usuario admin: `admin@onenglish.test`
- Password: `password`

## Módulos disponibles
- Dashboard
- Alumnos
- Profesores
- Cursos
- Grupos
- Inscripciones
- Sesiones
- Asistencia
- Financiero
- Reportes (asistencia y pagos)
- Reporte de auditoría
- Portal Alumno
- Portal Representante

## Flujo operativo recomendado
1. Crear profesores y alumnos.
2. Crear cursos y grupos.
3. Inscribir alumnos en grupos.
4. Crear sesiones de clase.
5. Registrar asistencia por sesión.
6. Crear cargos financieros y registrar pagos.
7. Revisar reportes de asistencia y pagos.
8. Revisar auditoría para trazabilidad de operaciones críticas.

## Portales por rol
- Alumno: `/portal/student`
- Representante: `/portal/representative`

Los portales muestran solo datos asociados al usuario autenticado.

## Seguridad (RBAC)
- Se mantiene `role` en usuarios por compatibilidad.
- Además se incluyen tablas formales: `roles`, `permissions`, `role_user`, `permission_role`.
- Middleware disponibles:
  - `role:<rol>`
  - `permission:<permiso>`

## Operación pre-producción
- Variables de producción: `.env.production.example`
- Guía despliegue: `docs/DEPLOY_PRODUCCION.md`
- Scripts operativos:
  - `scripts/ops/predeploy.sh`
  - `scripts/ops/backup_db.sh`
  - `scripts/ops/restore_db.sh`

## Importaciones
- Histórico alumnos: `php artisan import:students-historical --file="../docs/Historial de Matriculas Sede Picacho 22-24.xlsx" --campus=PICACHO`
- Histórico inscripciones: `php artisan import:enrollments-historical --file="../docs/Historial de Matriculas Sede Picacho 22-24.xlsx" --campus=PICACHO`
- Cursos/matriz: `php artisan import:courses-matrix --file=/ruta/matriz.txt --campus=PICACHO`
- Ledger financiero (CSV o XLSX): `php artisan import:finance-ledger --file=/ruta/ledger.csv --campus=PICACHO`
- Ledger financiero histórico (XLSX real): `php artisan import:finance-ledger --file="../docs/Historial de Matriculas Sede Picacho 22-24.xlsx" --campus=PICACHO`
- Conciliación global: `php artisan data:reconcile`
- Conciliación anual: `php artisan data:reconcile --year=2022` / `--year=2023`
- Recalcular alertas automáticas: `php artisan generate:alerts`

Nota: la importación financiera está preparada para re-ejecución sin duplicar cargos/pagos (idempotente).

## Exportación de reportes
- Asistencia: botón `Exportar CSV` en `/reports/attendance`
- Pagos: botón `Exportar CSV` en `/reports/payments`
