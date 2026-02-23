# UAT Checklist Ejecutable (Fase 1 MVP)

## Objetivo
Validar que el MVP cumple escenarios críticos de operación con aislamiento multi-sede, seguridad por rol, trazabilidad y reportes.

## Fixtures
Se dispone de fixture multi-sede para pruebas UAT:
- `Database\\Seeders\\UatFixtureSeeder`

## Escenarios automatizados
Archivo:
- `tests/Feature/UatChecklistTest.php`

Escenarios incluidos:
1. Aislamiento de sede: bloquear acceso cross-campus a edición de alumno.
2. Profesor: solo puede registrar asistencia en sesiones asignadas.
3. Representante: solo visualiza alumnos vinculados.
4. Reportes: exportación CSV de asistencia y pagos.
5. Alertas: generación automática de alertas financieras.

## Ejecución
1. Reset + seed base:
```bash
php artisan migrate:fresh --seed
```

2. Ejecutar pruebas UAT:
```bash
php artisan test --filter=UatChecklistTest
```

3. Ejecutar suite completa:
```bash
php artisan test
```

4. Recalcular alertas manualmente:
```bash
php artisan generate:alerts
```

## Criterio de aceptación
- Todas las pruebas UAT en verde.
- Sin acceso cross-campus en rutas protegidas.
- Export CSV responde con `Content-Type: text/csv`.
- Alertas generadas para mora/inasistencia cuando aplica.
