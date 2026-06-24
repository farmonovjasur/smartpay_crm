#!/bin/bash

###############################################################################
# SmartPay CRM — To'g'rilangan Deploy Skripti
# Hetzner VPS (Ubuntu 22.04) uchun to'liq o'rnatish va sozlash
#
# Ishga tushirish:
#   chmod +x deploy_fixed.sh
#   ./deploy_fixed.sh
###############################################################################

set -euo pipefail

# ─── Ranglar ───
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log_info()    { echo -e "${BLUE}[INFO]${NC} $1"; }
log_success() { echo -e "${GREEN}[✓]${NC} $1"; }
log_warning() { echo -e "${YELLOW}[!]${NC} $1"; }
log_error()   { echo -e "${RED}[✗]${NC} $1"; }

# Composer'ga root orqali ishlashga doimiy ruxsat berish (interaktiv so'rovlarni oldini olish)
export COMPOSER_ALLOW_SUPERUSER=1

# ─── Banner ───
echo -e "${GREEN}"
cat << "EOF"
   ____                       _   ____                __________  __  ___
  / ___| _ __ ___   __ _ _ __| |_|  _ \ __ _ _   _   / ___| ___ \|  \/  |
  \___ \| '_ ` _ \ / _` | '__| __| |_) / _` | | | | | |   | |_/ /| |\/| |
   ___) | | | | | | (_| | |  | |_|  __/ (_| | |_| | | |___|  _ < | |  | |
  |____/|_| |_| |_|\__,_|_|   \__|_|   \__,_|\__, |  \____|_| \_\|_|  |_|
                                              |___/
                    To'g'rilangan Deploy Skripti v2.0
EOF
echo -e "${NC}"

# ─── Root tekshirish ───
if [ "$EUID" -ne 0 ]; then
    log_error "Iltimos root sifatida ishga tushiring: sudo ./deploy_fixed.sh"
    exit 1
fi

# ─── O'zgaruvchilar ───
SERVER_IP=$(hostname -I | awk '{print $1}')
APP_DIR="/var/www/smartpay"
GITHUB_REPO="https://github.com/farmonovjasur/smartpay_crm.git"
CREDENTIALS_FILE="/root/.smartpay_credentials"

log_info "SmartPay CRM deploy boshlanmoqda... (Server: $SERVER_IP)"

###############################################################################
# 1. Eski o'rnatishni tozalash
###############################################################################
log_info "Eski o'rnatishni tozalash..."

systemctl stop smartpay-messenger 2>/dev/null || true
rm -f /etc/systemd/system/smartpay-messenger.service
systemctl daemon-reload 2>/dev/null || true

rm -f /etc/nginx/sites-enabled/smartpay
rm -f /etc/nginx/sites-available/smartpay
rm -f /etc/nginx/sites-enabled/smartpay-backend
rm -f /etc/nginx/sites-available/smartpay-backend
rm -f /etc/nginx/sites-enabled/smartpay-frontend
rm -f /etc/nginx/sites-available/smartpay-frontend

rm -rf "$APP_DIR"
rm -f /usr/local/bin/smartpay-backup.sh
rm -f /usr/local/bin/smartpay-status.sh
rm -f "$CREDENTIALS_FILE"

log_success "Tozalash tugadi"

###############################################################################
# 2. Tizimni yangilash
###############################################################################
log_info "Tizim paketlarini yangilash..."
export DEBIAN_FRONTEND=noninteractive
apt update && apt upgrade -y
log_success "Tizim yangilandi"

###############################################################################
# 3. Asosiy paketlarni o'rnatish
###############################################################################
log_info "Asosiy paketlarni o'rnatish..."
apt install -y software-properties-common curl wget git unzip ufw htop acl

###############################################################################
# 4. PHP 8.2 o'rnatish
###############################################################################
log_info "PHP 8.2 va extensionlarni o'rnatish..."
add-apt-repository ppa:ondrej/php -y
apt update
apt install -y php8.2-fpm php8.2-cli php8.2-mysql php8.2-xml php8.2-mbstring \
    php8.2-curl php8.2-zip php8.2-intl php8.2-bcmath php8.2-gd php8.2-opcache

# PHP sozlamalarini optimallash
PHP_INI_FPM="/etc/php/8.2/fpm/php.ini"
PHP_INI_CLI="/etc/php/8.2/cli/php.ini"

sed -i 's/memory_limit = .*/memory_limit = 256M/' "$PHP_INI_FPM"
sed -i 's/upload_max_filesize = .*/upload_max_filesize = 10M/' "$PHP_INI_FPM"
sed -i 's/post_max_size = .*/post_max_size = 12M/' "$PHP_INI_FPM"
sed -i 's/;date.timezone =.*/date.timezone = Asia\/Tashkent/' "$PHP_INI_FPM"
sed -i 's/;date.timezone =.*/date.timezone = Asia\/Tashkent/' "$PHP_INI_CLI"

systemctl restart php8.2-fpm
systemctl enable php8.2-fpm
log_success "PHP 8.2 o'rnatildi"

###############################################################################
# 5. MySQL 8.0 o'rnatish (interaktivsiz)
###############################################################################
log_info "MySQL 8.0 o'rnatish..."

apt install -y mysql-server

# MySQL ishlayotganligini tekshirish
systemctl start mysql
systemctl enable mysql

# Root auth_socket da qoladi (Ubuntu default) — bu xavfsizroq:
# faqat Linux root useri mysql root sifatida ulanishi mumkin.
# Agar avvalgi run root ni password ga o'zgartirgan bo'lsa, qaytaramiz:
if ! mysql -u root -e "SELECT 1" &>/dev/null; then
    log_warning "MySQL root auth_socket da emas, tiklaymiz..."
    systemctl stop mysql
    mysqld_safe --skip-grant-tables --skip-networking &
    sleep 3
    mysql -u root <<RESET_ROOT
FLUSH PRIVILEGES;
ALTER USER 'root'@'localhost' IDENTIFIED WITH auth_socket;
FLUSH PRIVILEGES;
RESET_ROOT
    killall mysqld 2>/dev/null || true
    sleep 3
    systemctl start mysql
fi

# Xavfsizlikni sozlash (root auth_socket orqali)
mysql -u root <<MYSQL_SECURE
-- Anonim foydalanuvchilarni o'chirish
DELETE FROM mysql.user WHERE User='';

-- Remote root login ni o'chirish
DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');

-- Test database ni o'chirish
DROP DATABASE IF EXISTS test;
DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';

FLUSH PRIVILEGES;
MYSQL_SECURE

log_success "MySQL o'rnatildi va xavfsizlashtirildi"

###############################################################################
# 6. Nginx o'rnatish
###############################################################################
log_info "Nginx o'rnatish..."
apt install -y nginx
systemctl enable nginx
log_success "Nginx o'rnatildi"

###############################################################################
# 7. Composer o'rnatish
###############################################################################
log_info "Composer o'rnatish..."
if [ ! -f /usr/local/bin/composer ]; then
    curl -sS https://getcomposer.org/installer | php
    mv composer.phar /usr/local/bin/composer
    chmod +x /usr/local/bin/composer
fi
log_success "Composer o'rnatildi ($(composer --version 2>/dev/null | head -1))"

###############################################################################
# 8. Node.js 20 o'rnatish
###############################################################################
log_info "Node.js 20 o'rnatish..."
if ! command -v node &>/dev/null || [[ "$(node -v)" != v20* ]]; then
    curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
    apt install -y nodejs
fi
log_success "Node.js $(node -v) o'rnatildi"

###############################################################################
# 9. Loyihani GitHub dan clone qilish
###############################################################################
log_info "GitHub'dan loyihani clone qilish..."
mkdir -p /var/www
git clone "$GITHUB_REPO" "$APP_DIR"
log_success "Loyiha clone qilindi"

###############################################################################
# 10. Database yaratish
###############################################################################
log_info "Database yaratish..."

DB_PASSWORD=$(openssl rand -base64 16 | tr -d "=+/" | cut -c1-20)

# Eski database va user ni tozalash (qayta ishga tushirish uchun)
mysql -u root <<MYSQL_CLEANUP
DROP DATABASE IF EXISTS smartpay_crm;
DROP USER IF EXISTS 'smartpay'@'localhost';
FLUSH PRIVILEGES;
MYSQL_CLEANUP

# Yangi database va user yaratish (auth_socket orqali, parolsiz)
mysql -u root <<MYSQL_DB
CREATE DATABASE smartpay_crm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'smartpay'@'localhost' IDENTIFIED BY '$DB_PASSWORD';
GRANT ALL PRIVILEGES ON smartpay_crm.* TO 'smartpay'@'localhost';
FLUSH PRIVILEGES;
MYSQL_DB

log_success "Database yaratildi"

###############################################################################
# 11. Backend sozlash
###############################################################################
log_info "Backend'ni sozlash..."
cd "$APP_DIR/backend"

# Composer install (APP_ENV=prod bilan, aks holda dev bundle larni qidirib xato beradi)
APP_ENV=prod COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction

# .env.local yaratish
APP_SECRET=$(openssl rand -hex 16)
JWT_PASSPHRASE=$(openssl rand -base64 24 | tr -d "=+/")
INITIAL_ADMIN_PASSWORD="Admin$(openssl rand -base64 8 | tr -d '=+/')!"

cat > .env.local <<ENV_FILE
APP_ENV=prod
APP_SECRET=$APP_SECRET
APP_TIMEZONE=Asia/Tashkent
DATABASE_URL="mysql://smartpay:$DB_PASSWORD@127.0.0.1:3306/smartpay_crm?serverVersion=8.0&charset=utf8mb4"
JWT_PASSPHRASE=$JWT_PASSPHRASE
CORS_ALLOWED_ORIGIN=http://$SERVER_IP
INITIAL_ADMIN_PASSWORD=$INITIAL_ADMIN_PASSWORD
ENV_FILE

# JWT keys yaratish
mkdir -p config/jwt
php bin/console lexik:jwt:generate-keypair --skip-if-exists

# Database migratsiyalari
php bin/console doctrine:migrations:migrate --no-interaction

# Boshlang'ich ma'lumotlar
php bin/console app:seed:initial || log_warning "Seed allaqachon bajarilgan bo'lishi mumkin"

# Ruxsatlar
chown -R www-data:www-data "$APP_DIR/backend/var"
chmod -R 775 "$APP_DIR/backend/var"
chown -R www-data:www-data "$APP_DIR/backend/config/jwt"
chmod 640 "$APP_DIR/backend/config/jwt"/*.pem 2>/dev/null || true

# Cache tozalash
php bin/console cache:clear --env=prod --no-debug

log_success "Backend sozlandi"

###############################################################################
# 12. Frontend sozlash va build
###############################################################################
log_info "Frontend'ni build qilish..."
cd "$APP_DIR/frontend"

# .env yaratish (same-origin = CORS muammo yo'q)
cat > .env <<FRONTEND_ENV
VITE_API_BASE_URL=/api
VITE_APP_NAME=SmartPay CRM
FRONTEND_ENV

# npm install va build
npm ci
npm run build

if [ ! -d "dist" ] || [ ! -f "dist/index.html" ]; then
    log_error "Frontend build muvaffaqiyatsiz! dist/index.html topilmadi."
    exit 1
fi

log_success "Frontend build qilindi ($(du -sh dist | awk '{print $1}'))"

###############################################################################
# 13. Nginx konfiguratsiyasi (TO'G'RILANGAN)
###############################################################################
log_info "Nginx'ni sozlash..."

cat > /etc/nginx/sites-available/smartpay <<'NGINX_CONFIG'
server {
    listen 80;
    server_name _;
    client_max_body_size 10M;

    # Frontend (React SPA) — asosiy root
    root /var/www/smartpay/frontend/dist;
    index index.html;

    # ─── API so'rovlari → Symfony PHP-FPM ───
    # Barcha /api/* so'rovlarini to'g'ridan-to'g'ri PHP-FPM ga yuboramiz.
    # Symfony index.php front-controller REQUEST_URI orqali marshrutlaydi.
    location ~ ^/api(/|$) {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME /var/www/smartpay/backend/public/index.php;
        fastcgi_param DOCUMENT_ROOT  /var/www/smartpay/backend/public;
        fastcgi_param REQUEST_URI    $request_uri;
        fastcgi_read_timeout 60;
        fastcgi_buffers 16 16k;
        fastcgi_buffer_size 32k;
    }

    # ─── Static assets keshi ───
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    # ─── SPA fallback ───
    # React Router uchun: mavjud bo'lmagan yo'llarni index.html ga qaytarish
    location / {
        try_files $uri $uri/ /index.html;
    }

    # ─── PHP fayllarni to'g'ridan-to'g'ri ochishni taqiqlash ───
    location ~ \.php$ {
        return 404;
    }

    # ─── Yashirin fayllarni taqiqlash ───
    location ~ /\. {
        deny all;
        access_log off;
        log_not_found off;
    }
}
NGINX_CONFIG

# Default config o'chirish va yangi config faollashtirish
rm -f /etc/nginx/sites-enabled/default
ln -sf /etc/nginx/sites-available/smartpay /etc/nginx/sites-enabled/

# Nginx konfiguratsiyani tekshirish
if ! nginx -t; then
    log_error "Nginx konfiguratsiya xatosi!"
    exit 1
fi

systemctl restart nginx
log_success "Nginx sozlandi va ishga tushdi"

###############################################################################
# 14. Systemd service (Messenger Worker)
###############################################################################
log_info "Messenger worker service yaratish..."

cat > /etc/systemd/system/smartpay-messenger.service <<SERVICE
[Unit]
Description=SmartPay Messenger Worker
After=network.target mysql.service

[Service]
Type=simple
User=www-data
WorkingDirectory=$APP_DIR/backend
ExecStart=/usr/bin/php $APP_DIR/backend/bin/console messenger:consume async --time-limit=3600 --memory-limit=128M
Restart=always
RestartSec=10
Environment=APP_ENV=prod

[Install]
WantedBy=multi-user.target
SERVICE

systemctl daemon-reload
systemctl enable smartpay-messenger
systemctl start smartpay-messenger
log_success "Messenger worker ishga tushdi"

###############################################################################
# 15. Backup sozlash
###############################################################################
log_info "Backup tizimini sozlash..."

mkdir -p /var/backups/smartpay

cat > /usr/local/bin/smartpay-backup.sh <<'BACKUP_SCRIPT'
#!/bin/bash
DATE=$(date +%Y-%m-%d_%H-%M-%S)
BACKUP_DIR="/var/backups/smartpay"
DB_NAME="smartpay_crm"
DB_USER="smartpay"
DB_PASS=$(grep "^DB_PASSWORD=" /root/.smartpay_credentials | cut -d'=' -f2)

mysqldump -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" | gzip > "$BACKUP_DIR/db_$DATE.sql.gz"
tar -czf "$BACKUP_DIR/uploads_$DATE.tar.gz" /var/www/smartpay/backend/var/uploads/ 2>/dev/null || true

# 7 kundan eski backuplarni o'chirish
find "$BACKUP_DIR" -name "*.gz" -mtime +7 -delete
echo "Backup completed: $DATE"
BACKUP_SCRIPT

chmod +x /usr/local/bin/smartpay-backup.sh
(crontab -l 2>/dev/null | grep -v smartpay-backup; echo "0 2 * * * /usr/local/bin/smartpay-backup.sh >> /var/log/smartpay-backup.log 2>&1") | crontab -
log_success "Backup tizimi sozlandi (har kuni soat 2:00 da)"

###############################################################################
# 16. Firewall sozlash
###############################################################################
log_info "Firewall sozlash..."
ufw --force disable
ufw --force reset
ufw default deny incoming
ufw default allow outgoing
ufw allow 22/tcp comment 'SSH'
ufw allow 80/tcp comment 'HTTP'
ufw allow 443/tcp comment 'HTTPS'
ufw --force enable
log_success "Firewall sozlandi"

###############################################################################
# 17. Monitoring skripti
###############################################################################
log_info "Monitoring skripti yaratish..."

cat > /usr/local/bin/smartpay-status.sh <<'STATUS_SCRIPT'
#!/bin/bash
echo "======================================"
echo "   SmartPay CRM — Tizim Holati"
echo "======================================"
echo ""

for svc in nginx php8.2-fpm mysql smartpay-messenger; do
    STATUS=$(systemctl is-active "$svc" 2>/dev/null)
    if [ "$STATUS" = "active" ]; then
        echo -e "  ✓ $svc: \033[0;32mIshlayapti\033[0m"
    else
        echo -e "  ✗ $svc: \033[0;31mTo'xtagan\033[0m"
    fi
done

echo ""
echo "======================================"
echo "   Disk va Xotira"
echo "======================================"
df -h / | tail -1 | awk '{print "  Disk: " $3 " / " $2 " (" $5 " ishlatilgan)"}'
free -h | grep Mem | awk '{print "  RAM:  " $3 " / " $2}'
echo ""

# API test
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/api/auth/login 2>/dev/null || echo "000")
echo "  API (/api/auth/login): HTTP $HTTP_CODE"

# Frontend test
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/ 2>/dev/null || echo "000")
echo "  Frontend (/): HTTP $HTTP_CODE"
echo ""
STATUS_SCRIPT

chmod +x /usr/local/bin/smartpay-status.sh
log_success "Monitoring skripti yaratildi"

###############################################################################
# 18. Credential faylni saqlash
###############################################################################
cat > "$CREDENTIALS_FILE" <<CREDS
# SmartPay CRM Credentials — $(date +%Y-%m-%d)
# BU FAYLNI XAVFSIZ SAQLANG!
# MySQL root: auth_socket (parolsiz, faqat Linux root useridan)

DB_PASSWORD=$DB_PASSWORD
APP_SECRET=$APP_SECRET
JWT_PASSPHRASE=$JWT_PASSPHRASE
ADMIN_PASSWORD=$INITIAL_ADMIN_PASSWORD
CREDS

chmod 600 "$CREDENTIALS_FILE"

###############################################################################
# 19. Yakuniy tekshiruvlar
###############################################################################
log_info "Yakuniy tekshiruvlar..."

ERRORS=0

# Nginx
if ! systemctl is-active --quiet nginx; then
    log_error "Nginx ishlamayapti!"
    ERRORS=$((ERRORS + 1))
fi

# PHP-FPM
if ! systemctl is-active --quiet php8.2-fpm; then
    log_error "PHP-FPM ishlamayapti!"
    ERRORS=$((ERRORS + 1))
fi

# MySQL
if ! systemctl is-active --quiet mysql; then
    log_error "MySQL ishlamayapti!"
    ERRORS=$((ERRORS + 1))
fi

# Frontend fayl
if [ ! -f "$APP_DIR/frontend/dist/index.html" ]; then
    log_error "Frontend build topilmadi!"
    ERRORS=$((ERRORS + 1))
fi

# API test
sleep 2
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "http://localhost/api/auth/login" 2>/dev/null || echo "000")
if [ "$HTTP_CODE" = "000" ]; then
    log_error "API javob bermayapti!"
    ERRORS=$((ERRORS + 1))
else
    log_success "API javob qaytarmoqda (HTTP $HTTP_CODE)"
fi

# Frontend test
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "http://localhost/" 2>/dev/null || echo "000")
if [ "$HTTP_CODE" = "200" ]; then
    log_success "Frontend ochilmoqda (HTTP 200)"
else
    log_warning "Frontend HTTP $HTTP_CODE qaytarmoqda"
fi

###############################################################################
# Yakuniy ma'lumotlar
###############################################################################
echo ""
if [ $ERRORS -eq 0 ]; then
    echo -e "${GREEN}======================================"
    echo "   🎉 DEPLOY MUVAFFAQIYATLI YAKUNLANDI!"
    echo "======================================${NC}"
else
    echo -e "${YELLOW}======================================"
    echo "   ⚠️  DEPLOY YAKUNLANDI ($ERRORS ta xato bor)"
    echo "======================================${NC}"
fi

echo ""
echo -e "${BLUE}📍 Kirish manzillari:${NC}"
echo "   Frontend:    http://$SERVER_IP"
echo "   Backend API: http://$SERVER_IP/api"
echo ""
echo -e "${BLUE}🔑 Login ma'lumotlari:${NC}"
echo "   Username: admin"
echo "   Password: $INITIAL_ADMIN_PASSWORD"
echo ""
echo -e "${YELLOW}⚠️  Muhim:${NC}"
echo "   1. Birinchi login'dan keyin admin parolni o'zgartiring!"
echo "   2. Barcha parollar: cat $CREDENTIALS_FILE"
echo "   3. Tizim holati: smartpay-status.sh"
echo ""
echo -e "${GREEN}✅ Tayyor! → http://$SERVER_IP${NC}"
echo ""
