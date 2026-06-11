# SmartPay CRM Backend — Deploy

## Requirements
- PHP 8.2+ (with bcmath, intl, pdo_mysql, zip extensions)
- MySQL 8.0+
- Nginx
- Composer 2.x

## Deploy Steps

1. Clone repo and install dependencies:
```bash
cd /var/www/smartpay/backend
composer install --no-dev --optimize-autoloader
```

2. Configure environment:
```bash
cp .env.prod.example .env.local
# Edit .env.local with production values
```

3. Generate JWT keys:
```bash
php bin/console lexik:jwt:generate-keypair
```

4. Run migrations:
```bash
php bin/console doctrine:migrations:migrate --no-interaction
```

5. Seed initial admin:
```bash
php bin/console app:seed:initial
```

6. Setup Nginx:
```bash
cp deploy/nginx.conf.example /etc/nginx/sites-available/smartpay
ln -s /etc/nginx/sites-available/smartpay /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
```

7. Setup SSL:
```bash
certbot --nginx -d api.smartpay.uz
```

8. Setup Messenger worker:
```bash
cp deploy/systemd/smartpay-messenger.service /etc/systemd/system/
systemctl daemon-reload
systemctl enable --now smartpay-messenger
```

9. Setup MySQL backup cron:
```bash
crontab -e
# Add: 0 1 * * * mysqldump smartpay_crm | gzip > /var/backups/smartpay-$(date +\%F).sql.gz
```
