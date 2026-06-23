# SmartPay CRM - To'liq Deployment Qo'llanmasi

## VPS Ma'lumotlari
- **Server**: Hetzner CX23
- **IP**: 91.98.237.187
- **Location**: Nuremberg, Germany
- **OS**: Ubuntu 22.04 LTS (tavsiya etiladi)
- **User**: root
- **GitHub Repo**: https://github.com/farmonovjasur/smartpay_crm.git

## 🚀 Tezkor Deploy (Avtomatik)

### 1-qadam: VPSga kirish
```bash
ssh root@91.98.237.187
```

### 2-qadam: Deploy skriptini yuklash va ishga tushirish
```bash
# Deploy skriptini yuklab olish
curl -o setup.sh https://raw.githubusercontent.com/farmonovjasur/smartpay_crm/main/deploy/setup.sh

# Ruxsat berish va ishga tushirish
chmod +x setup.sh
./setup.sh
```

Skript avtomatik ravishda:
- ✅ Barcha kerakli dasturlarni o'rnatadi (PHP, MySQL, Nginx, Node.js)
- ✅ GitHub'dan loyihani clone qiladi
- ✅ Backend va Frontend'ni sozlaydi
- ✅ SSL sertifikatini o'rnatadi
- ✅ Systemd service'larni yaratadi

---

## 📋 Qo'lda Deploy Qilish (Batafsil)

Agar avtomatik skript ishlamasa yoki qo'lda deploy qilishni xohlasangiz:

### Boshlang'ich Tayyorgarlik

#### 1. VPSga kirish
```bash
ssh root@91.98.237.187
```

#### 2. Tizimni yangilash
```bash
apt update && apt upgrade -y
```

#### 3. Kerakli dasturlarni o'rnatish

**PHP 8.2+ va extensionlar:**
```bash
apt install -y software-properties-common
add-apt-repository ppa:ondrej/php -y
apt update
apt install -y php8.2-fpm php8.2-cli php8.2-mysql php8.2-xml php8.2-mbstring \
    php8.2-curl php8.2-zip php8.2-intl php8.2-bcmath php8.2-gd php8.2-opcache
```

**MySQL 8.0:**
```bash
apt install -y mysql-server
mysql_secure_installation
```

**Nginx:**
```bash
apt install -y nginx
```

**Composer:**
```bash
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer
```

**Node.js 20+ va npm:**
```bash
curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
apt install -y nodejs
```

**Git:**
```bash
apt install -y git
```

**Certbot (SSL uchun):**
```bash
apt install -y certbot python3-certbot-nginx
```

### Backend Deploy

#### 1. Loyihani clone qilish
```bash
mkdir -p /var/www
cd /var/www
git clone https://github.com/farmonovjasur/smartpay_crm.git smartpay
cd smartpay
```

#### 2. MySQL database yaratish
```bash
mysql -u root -p
```

MySQL konsolida:
```sql
CREATE DATABASE smartpay_crm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'smartpay'@'localhost' IDENTIFIED BY 'Jasurbek6091Strong!';
GRANT ALL PRIVILEGES ON smartpay_crm.* TO 'smartpay'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

#### 3. Backend sozlash
```bash
cd /var/www/smartpay/backend

# Composer dependencies o'rnatish
composer install --no-dev --optimize-autoloader

# Environment faylini nusxalash va sozlash
cp .env.prod.example .env.local

# .env.local faylini tahrirlash
nano .env.local
```

`.env.local` faylida quyidagilarni o'zgartiring:
```env
APP_ENV=prod
APP_SECRET=f8d7a6b5c4e3d2f1a0b9c8d7e6f5a4b3  # Tasodifiy 32 belgili string
APP_TIMEZONE=Asia/Tashkent
DATABASE_URL="mysql://smartpay:Jasurbek6091Strong!@127.0.0.1:3306/smartpay_crm?serverVersion=8.0&charset=utf8mb4"
JWT_PASSPHRASE=MySecureJWTPassphrase123!  # Murakkab parol
CORS_ALLOWED_ORIGIN=https://smartpaycrm.com
INITIAL_ADMIN_PASSWORD=Admin123!  # Birinchi login'dan keyin o'zgartiring
```

#### 4. JWT kalitlarini yaratish
```bash
php bin/console lexik:jwt:generate-keypair
```

#### 5. Database migratsiyalari va seed
```bash
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console app:seed:initial
```

#### 6. Cache va ruxsatlar
```bash
php bin/console cache:clear --env=prod
chown -R www-data:www-data /var/www/smartpay/backend/var
chmod -R 775 /var/www/smartpay/backend/var
```

### Frontend Deploy

#### 1. Frontend build qilish
```bash
cd /var/www/smartpay/frontend

# Dependencies o'rnatish
npm ci

# Environment faylini sozlash
cp .env.example .env
nano .env
```

`.env` faylida:
```env
VITE_API_BASE_URL=https://api.smartpaycrm.com
VITE_APP_NAME=SmartPay CRM
```

#### 2. Production build
```bash
npm run build
# Build natijasi frontend/dist papkasida bo'ladi
```

### Nginx Konfiguratsiyasi

#### 1. Backend uchun Nginx config
```bash
nano /etc/nginx/sites-available/smartpay-backend
```

Quyidagi konfiguratsiyani kiriting:
```nginx
server {
    listen 80;
    server_name api.smartpaycrm.com;
    root /var/www/smartpay/backend/public;
    
    location / {
        try_files $uri /index.php$is_args$args;
    }
    
    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        internal;
    }
    
    location ~ \.php$ {
        return 404;
    }
    
    client_max_body_size 10M;
}
```

#### 2. Frontend uchun Nginx config
```bash
nano /etc/nginx/sites-available/smartpay-frontend
```

```nginx
server {
    listen 80;
    server_name smartpaycrm.com www.smartpaycrm.com;
    root /var/www/smartpay/frontend/dist;
    index index.html;
    
    location / {
        try_files $uri $uri/ /index.html;
    }
    
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

#### 3. Nginx konfiguratsiyalarni faollashtirish
```bash
ln -s /etc/nginx/sites-available/smartpay-backend /etc/nginx/sites-enabled/
ln -s /etc/nginx/sites-available/smartpay-frontend /etc/nginx/sites-enabled/
rm /etc/nginx/sites-enabled/default

# Konfiguratsiyani tekshirish
nginx -t

# Nginx'ni qayta yuklash
systemctl reload nginx
```

### SSL Sertifikat O'rnatish

```bash
# Backend uchun SSL
certbot --nginx -d api.smartpaycrm.com

# Frontend uchun SSL
certbot --nginx -d smartpaycrm.com -d www.smartpaycrm.com
```

### Systemd Service (Messenger Worker)

```bash
nano /etc/systemd/system/smartpay-messenger.service
```

```ini
[Unit]
Description=SmartPay Messenger Worker
After=network.target mysql.service

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/smartpay/backend
ExecStart=/usr/bin/php /var/www/smartpay/backend/bin/console messenger:consume async --time-limit=3600
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

Servicesni ishga tushirish:
```bash
systemctl daemon-reload
systemctl enable smartpay-messenger
systemctl start smartpay-messenger
systemctl status smartpay-messenger
```

### Backup Sozlash

#### 1. Backup papkasi yaratish
```bash
mkdir -p /var/backups/smartpay
```

#### 2. Backup skripti
```bash
nano /usr/local/bin/smartpay-backup.sh
```

```bash
#!/bin/bash
DATE=$(date +%Y-%m-%d_%H-%M-%S)
BACKUP_DIR="/var/backups/smartpay"
DB_NAME="smartpay_crm"
DB_USER="smartpay"
DB_PASS="Jasurbek6091Strong!"

# Database backup
mysqldump -u $DB_USER -p$DB_PASS $DB_NAME | gzip > "$BACKUP_DIR/db_$DATE.sql.gz"

# Uploads backup (agar mavjud bo'lsa)
tar -czf "$BACKUP_DIR/uploads_$DATE.tar.gz" /var/www/smartpay/backend/var/uploads/ 2>/dev/null

# Eski backuplarni o'chirish (7 kundan eski)
find $BACKUP_DIR -name "*.gz" -mtime +7 -delete

echo "Backup completed: $DATE"
```

```bash
chmod +x /usr/local/bin/smartpay-backup.sh
```

#### 3. Cron job qo'shish (har kuni soat 2 da)
```bash
crontab -e
```

Quyidagi qatorni qo'shing:
```
0 2 * * * /usr/local/bin/smartpay-backup.sh >> /var/log/smartpay-backup.log 2>&1
```

### Firewall Sozlash

```bash
# UFW o'rnatish
apt install -y ufw

# SSH, HTTP, HTTPS ruxsat berish
ufw allow 22/tcp
ufw allow 80/tcp
ufw allow 443/tcp

# Firewall yoqish
ufw --force enable
ufw status
```

### Monitoring va Logs

#### Loglarni ko'rish:
```bash
# Backend logs
tail -f /var/www/smartpay/backend/var/log/prod.log

# Nginx access logs
tail -f /var/log/nginx/access.log

# Nginx error logs
tail -f /var/log/nginx/error.log

# Messenger worker logs
journalctl -u smartpay-messenger -f

# MySQL logs
tail -f /var/log/mysql/error.log
```

#### Tizim resurslarini monitoring:
```bash
# CPU va RAM
htop

# Disk space
df -h

# PHP-FPM status
systemctl status php8.2-fpm
```

---

## 🔄 Yangilanishlar Deploy Qilish

Kelajakda kod yangilanishlarini deploy qilish uchun:

```bash
cd /var/www/smartpay

# Yangi kodni olish
git pull origin main

# Backend yangilash
cd backend
composer install --no-dev --optimize-autoloader
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console cache:clear --env=prod

# Frontend yangilash
cd ../frontend
npm ci
npm run build

# Services'larni qayta yuklash
systemctl restart php8.2-fpm
systemctl restart smartpay-messenger
systemctl reload nginx
```

---

## 🧪 Test Qilish

### Backend API test:
```bash
curl https://api.smartpaycrm.com/api/health
```

### Frontend test:
Brauzerda ochish: https://smartpaycrm.com

### Login test:
- Username: `admin`
- Password: `.env.local` faylidagi `INITIAL_ADMIN_PASSWORD`

---

## 🔐 Xavfsizlik Tavsifalari

1. **SSH parolni o'zgartirish:**
```bash
passwd root
```

2. **SSH key autentifikatsiyani sozlash** (opsional lekin tavsiya etiladi)
3. **Fail2Ban o'rnatish:**
```bash
apt install -y fail2ban
systemctl enable fail2ban
```

4. **MySQL root parolni o'rnatish**
5. **Admin parolni o'zgartirish** (birinchi login'dan keyin)

---

## 📞 Muammolarni Hal Qilish

### 500 Internal Server Error:
```bash
# Logs tekshirish
tail -f /var/www/smartpay/backend/var/log/prod.log
tail -f /var/log/nginx/error.log

# Ruxsatlarni tekshirish
chown -R www-data:www-data /var/www/smartpay/backend/var
```

### Database connection xatosi:
```bash
# MySQL ishlaganligini tekshirish
systemctl status mysql

# Database mavjudligini tekshirish
mysql -u smartpay -p smartpay_crm -e "SHOW TABLES;"
```

### CORS xatosi:
- `.env.local` faylidagi `CORS_ALLOWED_ORIGIN` to'g'ri sozlanganligini tekshiring

---

## ✅ Yakuniy Checklist

- [ ] VPS sozlangan va kirish mumkin
- [ ] Barcha dasturlar o'rnatilgan (PHP, MySQL, Nginx, Node.js)
- [ ] Loyiha GitHub'dan clone qilingan
- [ ] Database yaratilgan va migration'lar bajarilgan
- [ ] Backend ishlayapti va API javob qaytarayapti
- [ ] Frontend build qilingan va ochiladi
- [ ] SSL sertifikatlar o'rnatilgan (HTTPS)
- [ ] Systemd service'lar ishga tushirilgan
- [ ] Backup cron job sozlangan
- [ ] Firewall konfiguratsiyalangan
- [ ] Admin panel'ga kirish mumkin

---

**Muvaffaqiyatli deployment!** 🎉
