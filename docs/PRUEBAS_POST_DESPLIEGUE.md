# Pruebas Post-Despliegue (Producción/Staging)

## 1. Smoke técnico
```bash
cd /var/www/sgaon
php artisan about
php artisan migrate:status
php artisan route:list | wc -l
php artisan schedule:list
sudo supervisorctl status
```

Resultado esperado:
- Sin migraciones pendientes.
- Scheduler muestra `generate:alerts`.
- Workers de supervisor en `RUNNING`.

## 2. Prueba de login y roles (manual)
1. Iniciar sesión como `admin`.
2. Confirmar acceso a módulos admin (`Alumnos`, `Financiero`, `Reportes`, `Auditoría`).
3. Iniciar sesión como `teacher`.
4. Confirmar que solo ve opciones permitidas y asistencia asignada.
5. Iniciar sesión como `student` y validar `portal/student`.
6. Iniciar sesión como `representative` y validar `portal/representative`.

Resultado esperado:
- No hay accesos indebidos entre roles.

## 3. Pruebas automáticas mínimas
```bash
cd /var/www/sgaon
php artisan test --filter=RoleAccessTest
php artisan test --filter=UatChecklistTest
```

Resultado esperado:
- Todo en verde.

## 4. Flujo académico (manual)
1. Crear curso.
2. Crear grupo y asignar profesor.
3. Inscribir alumno.
4. Crear sesión.
5. Registrar asistencia.

Resultado esperado:
- Flujo completo sin errores y datos visibles en reportes.

## 5. Flujo financiero (manual)
1. Crear cargo con fecha vencida.
2. Ejecutar: `php artisan generate:alerts`
3. Verificar alerta financiera abierta.
4. Registrar pago.
5. Verificar recibo generado y saldo actualizado.

Resultado esperado:
- Cargo pasa a estado correcto.
- Recibo visible.
- Alertas se recalculan.

## 6. Exportación reportes
1. Abrir `/reports/attendance` y exportar CSV.
2. Abrir `/reports/payments` y exportar CSV.

Resultado esperado:
- Descarga de archivos CSV válida.
- Contenido coherente con datos cargados.

## 7. Aislamiento multi-sede
1. Con usuario de sede A intentar editar recurso de sede B (URL directa).

Resultado esperado:
- Respuesta `403`.

## 8. Auditoría
1. Ejecutar operaciones críticas:
   - Login
   - Crear cargo/pago
   - Registrar asistencia
   - Crear/editar inscripción
2. Revisar `/reports/audit`.

Resultado esperado:
- Entradas de auditoría con usuario, acción y payload.

## 9. Backup y restore (simulado)
```bash
cd /var/www/sgaon
bash scripts/ops/backup_db.sh
ls -lah storage/backups
# restore solo en staging
bash scripts/ops/restore_db.sh storage/backups/<archivo> --yes
```

Resultado esperado:
- Backup generado sin error.
- Restore funcional en staging.

## 10. Criterio de Go-Live
- Smoke técnico OK
- Pruebas de rol/seguridad OK
- Flujo académico y financiero OK
- Exportes OK
- Auditoría OK
- Backup/restore validado
- Aprobación UAT firmada por cliente
