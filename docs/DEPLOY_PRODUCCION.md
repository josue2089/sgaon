# Despliegue Producción - SGA ON English MVP

## 1. Requisitos servidor
- Ubuntu 22.04+ (o similar)
- PHP 8.3+ con extensiones Laravel
- Nginx + PHP-FPM
- MySQL 8+
- Composer 2+
- Node.js 20+ (build frontend)
- Supervisor (workers)

## 2. Variables de entorno
1. Copiar:
```bash
cp .env.production.example .env
```
2. Completar valores reales (`APP_KEY`, DB, MAIL, APP_URL).
3. Generar key si no existe:
```bash
php artisan key:generate --force
```

## 3. Pre-deploy (automatizado)
```bash
bash scripts/ops/predeploy.sh
```

## 4. Queue workers
1. Copiar config de ejemplo:
- `docs/deploy/SUPERVISOR_WORKER.conf`
2. Ajustar rutas/usuario.
3. Activar:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start sgaon-worker:*
```

## 5. Scheduler
1. Instalar crontab:
```bash
crontab docs/deploy/CRONTAB.example
```
2. Verificar comandos programados:
```bash
php artisan schedule:list
```

## 6. Backups y restore
- Backup DB:
```bash
bash scripts/ops/backup_db.sh
```
- Restore DB:
```bash
bash scripts/ops/restore_db.sh storage/backups/<archivo> --yes
```

## 7. Hardening mínimo
- `APP_DEBUG=false`
- HTTPS obligatorio
- Permisos correctos en `storage/` y `bootstrap/cache/`
- Firewall activo
- Credenciales fuertes DB/SMTP

## 8. Validación post deploy
```bash
php artisan about
php artisan migrate:status
php artisan test --filter=RoleAccessTest
php artisan test --filter=UatChecklistTest
php artisan data:reconcile
```

## 9. Rollback operativo
1. Poner en mantenimiento:
```bash
php artisan down
```
2. Restaurar backup:
```bash
bash scripts/ops/restore_db.sh storage/backups/<archivo> --yes
```
3. Levantar app:
```bash
php artisan up
```
