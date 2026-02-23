# Despliegue Paso a Paso (Servidor) - SGA ON English

> Asume Ubuntu 22.04+, proyecto en `/var/www/sgaon`, dominio `sga.onenglish.net` y usuario con sudo.

## 0. Variables de referencia
```bash
export APP_DIR=/var/www/sgaon
export PHP_FPM_SOCK=/var/run/php/php8.3-fpm.sock
export DOMAIN=sga.onenglish.net
```

## 1. Instalar paquetes base
```bash
sudo apt update
sudo apt install -y nginx mysql-server unzip git curl supervisor
sudo apt install -y php8.3-fpm php8.3-cli php8.3-mysql php8.3-xml php8.3-mbstring php8.3-curl php8.3-zip php8.3-bcmath php8.3-intl php8.3-sqlite3
```

## 2. Instalar Composer y Node (si no existen)
```bash
cd /tmp
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php
sudo mv composer.phar /usr/local/bin/composer
composer --version

curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
node -v
npm -v
```

## 3. Crear base de datos MySQL
```bash
sudo mysql -e "CREATE DATABASE sgaon_prod CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER 'sgaon_user'@'localhost' IDENTIFIED BY 'CAMBIAR_PASSWORD_FUERTE';"
sudo mysql -e "GRANT ALL PRIVILEGES ON sgaon_prod.* TO 'sgaon_user'@'localhost';"
sudo mysql -e "FLUSH PRIVILEGES;"
```

## 4. Copiar proyecto al servidor
### Opción A: rsync desde máquina local
```bash
rsync -avz --delete /ruta/local/sgaon/ usuario@servidor:$APP_DIR/
```

### Opción B: git clone (si usas repositorio)
```bash
sudo mkdir -p $APP_DIR
sudo chown -R $USER:$USER $APP_DIR
git clone <repo-url> $APP_DIR
cd $APP_DIR
```

## 5. Configurar entorno de producción
```bash
cd $APP_DIR
cp .env.production.example .env
```
Editar `.env`:
- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://sga.onenglish.net`
- `DB_CONNECTION=mysql`
- `DB_HOST=127.0.0.1`
- `DB_PORT=3306`
- `DB_DATABASE=sgaon_prod`
- `DB_USERNAME=sgaon_user`
- `DB_PASSWORD=<password_fuerte>`

Luego:
```bash
php artisan key:generate --force
```

## 6. Permisos de carpetas
```bash
sudo chown -R www-data:www-data $APP_DIR
sudo find $APP_DIR -type f -exec chmod 644 {} \;
sudo find $APP_DIR -type d -exec chmod 755 {} \;
sudo chmod -R ug+rwx $APP_DIR/storage $APP_DIR/bootstrap/cache
```

## 7. Ejecutar predeploy
```bash
cd $APP_DIR
bash scripts/ops/predeploy.sh
```

## 8. Configurar Nginx
```bash
sudo cp $APP_DIR/docs/deploy/NGINX.example.conf /etc/nginx/sites-available/sgaon
sudo sed -i "s#server_name sga.onenglish.net#server_name $DOMAIN#g" /etc/nginx/sites-available/sgaon
sudo sed -i "s#/var/www/sgaon#$APP_DIR#g" /etc/nginx/sites-available/sgaon
sudo sed -i "s#php8.3-fpm.sock#$(basename $PHP_FPM_SOCK)#g" /etc/nginx/sites-available/sgaon
sudo ln -sf /etc/nginx/sites-available/sgaon /etc/nginx/sites-enabled/sgaon
sudo nginx -t
sudo systemctl reload nginx
```

## 9. Configurar HTTPS (Let's Encrypt)
```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d $DOMAIN
```

## 10. Configurar cola (Supervisor)
```bash
sudo cp $APP_DIR/docs/deploy/SUPERVISOR_WORKER.conf /etc/supervisor/conf.d/sgaon-worker.conf
sudo sed -i "s#/var/www/sgaon#$APP_DIR#g" /etc/supervisor/conf.d/sgaon-worker.conf
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start sgaon-worker:*
sudo supervisorctl status
```

## 11. Configurar scheduler (cron)
```bash
( crontab -l 2>/dev/null; echo "* * * * * cd $APP_DIR && php artisan schedule:run >> /dev/null 2>&1" ) | crontab -
crontab -l
```

## 12. Importación inicial (si aplica)
```bash
cd $APP_DIR
php artisan import:students-historical --file="/ruta/Historial de Matriculas Sede Picacho 22-24.xlsx" --campus=PICACHO
php artisan import:enrollments-historical --file="/ruta/Historial de Matriculas Sede Picacho 22-24.xlsx" --campus=PICACHO
php artisan import:finance-ledger --file="/ruta/Historial de Matriculas Sede Picacho 22-24.xlsx" --campus=PICACHO
php artisan generate:alerts
php artisan data:reconcile
```

## 13. Verificación rápida de servicios
```bash
sudo systemctl status nginx --no-pager
sudo systemctl status php8.3-fpm --no-pager
sudo systemctl status mysql --no-pager
sudo supervisorctl status
```

## 14. Backup inicial
```bash
cd $APP_DIR
bash scripts/ops/backup_db.sh
ls -lah storage/backups
```

## 15. Comandos de operación diaria
```bash
# Estado app
php artisan about

# Recalcular alertas
php artisan generate:alerts

# Conciliación
php artisan data:reconcile

# Reiniciar workers
sudo supervisorctl restart sgaon-worker:*
```
