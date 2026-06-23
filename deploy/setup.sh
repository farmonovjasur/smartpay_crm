#!/bin/bash

###############################################################################
# SmartPay CRM - Avtomatik Deploy Skripti
# Hetzner VPS uchun to'liq o'rnatish va sozlash
###############################################################################

set -e  # Xatolik bo'lsa to'xtatish

# Ranglar
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Log funksiyasi
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[✓]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[!]${NC} $1"
}

log_error() {
    echo -e "${RED}[✗]${NC} $1"
}

# Banner
echo -e "${GREEN}"
cat << "EOF"
   ____                       _   ____                __________  __  ___
  / ___| _ __ ___   __ _ _ __| |_|  _ \ __ _ _   _   / ___| ___ \|  \/  |
  \___ \| '_ ` _ \ / _` | '__| __| |_) / _` | | | | | |   | |_/ /| |\/| |
   ___) | | | | | | (_| | |  | |_|  __/ (_| | |_| | | |___|  _ < | |  | |
  |____/|_| |_| |_|\__,_|_|   \__|_|   \__,_|\__, |  \____|_| \_\|_|  |_|
                                              |___/                        
                       Avtomatik Deploy Skripti
EOF
echo -e "${NC}"

# Root tekshirish
if [ "$EUID" -ne 0 ]; then 
    log_error "Iltimos root sifatida ishga tushiring: sudo ./setup.sh"
    exit 1
fi

log_info "SmartPay CRM deploy boshlanmoqda..."

###############################################################################
# 1. Tizimni yangilash
###############################################################################
log_info "Tizim paketlarini yangilash..."
apt update && apt upgrade -y
log_success "Tizim yangilandi"

###############################################################################
# 2. Asosiy dasturlarni o'rnatish
###############################################################################
log_info "Asosiy paketlarni o'rnatish..."
apt install -y software-properties-common curl wget git unzip ufw htop

###############################################################################
# 3. PHP 8.2+ o'rnatish
###############################################################################
log_info "PHP 8.2 va extensionlarni o'rnatish..."
add-apt-repository ppa:ondrej/php -y
apt update
apt install -y php8.2-fpm php8.2-cli php8.2-mysql php8.2-xml php8.2-mbstring \
    php8.2-curl php8.2-zip php8.2-intl php8.2-bcmath php8.2-gd php8.2-opcache \
    php8.2-redis php8.2-apcu

# PHP sozlamalarini optimallash
sed -i 's/memory_limit = .*/memory_limit = 256M/' /etc/php/8.2/fpm/php.ini
sed -i 's/upload_max_filesize = .*/upload_max_filesize = 10M/' /etc/php/8.2/fpm/php.ini
sed -i 's/post_max_size = .*/post_max_size = 10M/' /etc/php/8.2/fpm/php.ini
sed -i 's/;date.timezone =.*/date.timezone = Asia\/Tashkent/' /etc/php/8.2/fpm/php.ini
sed -i 's/;date.timezone =.*/date.timezone = Asia\/Tashkent/' /etc/php/8.2/cli/php.ini

systemctl restart php8.2-fpm
log_success "PHP 8.2 o'rnatildi"

###############################################################################
# 4. MySQL 8.0 o'rnatish
###############################################################################
log_info "MySQL 8.0 o'rnatish..."
apt install -y mysql-server

# MySQL xavfsizligini sozlash
log_warning "MySQL root parolini o'rnatish kerak bo'ladi..."
mysql_secure_installation

log_success "MySQL o'rnatildi"

###############################################################################
# 5. Nginx o'rnatish
###############################################################################
log_info "Nginx o'rnatish..."
apt install -y nginx
systemctl enable nginx
log_success "Nginx o'rnatildi"

###############################################################################
# 6. Composer o'rnatish
###############################################################################
log_info "Composer o'rnatish..."
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer
chmod +x /usr/local/bin/composer
log_success "Composer o'rnatildi"

###############################################################################
# 7. Node.js 20+ o'rnatish
###############################################################################
log_info "Node.js 20 o'rnatish..."
curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
apt install -y nodejs
log_success "Node.js $(node -v) o'rnatildi"

###############################################################################
# 8. Certbot o'rnatish (SSL uchun)
###############################################################################
log_info "Certbot o'rnatish..."
apt install -y certbot python3-certbot-nginx
log_success "Certbot o'rnatildi"

###############################################################################
# 9. Loyihani clone qilish
###############################################################################
log_info "GitHub'dan loyihani yuklab olish..."
mkdir -p /var/www
cd /var/www

if [ -d "smartpay" ]; then
    log_warning "smartpay papkasi mavjud. Uni o'chirish..."
    rm -rf smartpay
fi

git clone https://github.com/farmonovjasur/smartpay_crm.git smartpay
cd smartpay
log_success "Loyiha clone qilindi"

###############################################################################
# 10. Database yaratish
###############################################################################
log_info "Database yaratish..."

# Tasodifiy parol generatsiya qilish
DB_PASSWORD=$(openssl rand -base64 16 | tr -d "=+/" | cut -c1-20)

log_warning "Database paroli: $DB_PASSWORD (Buni saqlang!)"
echo "DATABASE_PASSWORD=$DB_PASSWORD" > /root/.smartpay_credentials

mysql -u root <<MYSQL_SCRIPT
CREATE DATABASE IF NOT EXISTS smartpay_crm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'smartpay'@'localhost' IDENTIFIED BY '$DB_PASSWORD';
GRANT ALL PRIVILEGES ON smartpay_crm.* TO 'smartpay'@'localhost';
FLUSH PRIVILEGES;
MYSQL_SCRIPT

log_success "Database yaratildi"

###############################################################################
# 11. Backend sozlash
###############################################################################
log_info "Backend'ni sozlash..."
cd /var/www/smartpay/backend

# Composer install
composer install --no-dev --optimize-autoloader --no-interaction

# .env.local yaratish
APP_SECRET=$(openssl rand -hex 16)
JWT_PASSPHRASE=$(openssl rand -base64 24)
INITIAL_ADMIN_PASSWORD=$(openssl rand -base64 12)

cat > .env.local <<ENV_FILE
APP_ENV=prod
APP_SECRET=$APP_SECRET
APP_TIMEZONE=Asia/Tashkent
DATABASE_URL="mysql://smartpay:$DB_PASSWORD@127.0.0.1:3306/smartpay_crm?serverVersion=8.0&charset=utf8mb4"
JWT_PASSPHRASE=$JWT_PASSPHRASE
CORS_ALLOWED_ORIGIN=http://$(hostname -I | awk '{print $1}')
INITIAL_ADMIN_PASSWORD=$INITIAL_ADMIN_PASSWORD
ENV_FILE

log_warning "Admin paroli: $INITIAL_ADMIN_PASSWORD (Buni saqlang!)"
echo "ADMIN_PASSWORD=$INITIAL_ADMIN_PASSWORD" >> /root/.smartpay_credentials

# JWT keys
php bin/console lexik:jwt:generate-keypair --skip-if-exists

# Migrations
php bin/console doctrine:migrations:migrate --no-interaction

# Seed
php bin/console app:seed:initial

# Permissions
chown -R www-data:www-data /var/www/smartpay/backend/var
chmod -R 775 /var/www/smartpay/backend/var
chown -R www-data:www-data /var/www/smartpay/backend/config/jwt
chmod -R 640 /var/www/smartpay/backend/config/jwt/*.pem

# Cache clear
php bin/console cache:clear --env=prod

log_success "Backend sozlandi"

###############################################################################
# 12. Frontend sozlash
###############################################################################
log_info "Frontend'ni build qilish..."
cd /var/www/smartpay/frontend

# .env yaratish
SERVER_IP=$(hostname -I | awk '{print $1}')
cat > .env <<FRONTEND_ENV
VITE_API_BASE_URL=http://$SERVER_IP/api
VITE_APP_NAME=SmartPay CRM
FRONTEND_ENV

# npm install va build
npm ci
npm run build

log_success "Frontend build qilindi"

###############################################################################
# 13. Nginx konfiguratsiyalari
###############################################################################
log_info "Nginx'ni sozlash..."

# Backend config
cat > /etc/nginx/sites-available/smartpay <<NGINX_CONFIG
server {
    listen 80;
    server_name _;
    client_max_body_size 10M;

    # Backend API
    location /api {
        alias /var/www/smartpay/backend/public;
        try_files \$uri /index.php\$is_args\$args;
        
        location ~ ^/api/index\.php(/|$) {
            fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
            fastcgi_split_path_info ^(.+\.php)(/.*)$;
            include fastcgi_params;
            fastcgi_param SCRIPT_FILENAME /var/www/smartpay/backend/public/index.php;
            fastcgi_param DOCUMENT_ROOT /var/www/smartpay/backend/public;
            internal;
        }
    }

    # Frontend
    location / {
        root /var/www/smartpay/frontend/dist;
        try_files \$uri \$uri/ /index.html;
        index index.html;
    }

    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        root /var/www/smartpay/frontend/dist;
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
NGINX_CONFIG

# Default config'ni o'chirish va yangi config'ni faollashtirish
rm -f /etc/nginx/sites-enabled/default
ln -sf /etc/nginx/sites-available/smartpay /etc/nginx/sites-enabled/

# Nginx test va restart
nginx -t
systemctl restart nginx

log_success "Nginx sozlandi"

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
WorkingDirectory=/var/www/smartpay/backend
ExecStart=/usr/bin/php /var/www/smartpay/backend/bin/console messenger:consume async --time-limit=3600
Restart=always
RestartSec=10

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
DB_PASS=$(grep DATABASE_PASSWORD /root/.smartpay_credentials | cut -d'=' -f2)

# Database backup
mysqldump -u $DB_USER -p$DB_PASS $DB_NAME | gzip > "$BACKUP_DIR/db_$DATE.sql.gz"

# Uploads backup
tar -czf "$BACKUP_DIR/uploads_$DATE.tar.gz" /var/www/smartpay/backend/var/uploads/ 2>/dev/null || true

# Eski backuplarni o'chirish (7 kundan eski)
find $BACKUP_DIR -name "*.gz" -mtime +7 -delete

echo "Backup completed: $DATE"
BACKUP_SCRIPT

chmod +x /usr/local/bin/smartpay-backup.sh

# Cron job
(crontab -l 2>/dev/null; echo "0 2 * * * /usr/local/bin/smartpay-backup.sh >> /var/log/smartpay-backup.log 2>&1") | crontab -

log_success "Backup tizimi sozlandi (har kuni soat 2:00 da)"

###############################################################################
# 16. Firewall sozlash
###############################################################################
log_info "Firewall sozlash..."

ufw --force disable
ufw --force reset
ufw default deny incoming
ufw default allow outgoing
ufw allow 22/tcp
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable

log_success "Firewall sozlandi"

###############################################################################
# 17. Monitoring skripti
###############################################################################
log_info "Monitoring skripti yaratish..."

cat > /usr/local/bin/smartpay-status.sh <<'STATUS_SCRIPT'
#!/bin/bash

echo "======================================"
echo "   SmartPay CRM - Tizim Holati"
echo "======================================"
echo ""

# Nginx
echo -n "Nginx: "
systemctl is-active nginx && echo "✓ Ishlayapti" || echo "✗ To'xtagan"

# PHP-FPM
echo -n "PHP-FPM: "
systemctl is-active php8.2-fpm && echo "✓ Ishlayapti" || echo "✗ To'xtagan"

# MySQL
echo -n "MySQL: "
systemctl is-active mysql && echo "✓ Ishlayapti" || echo "✗ To'xtagan"

# Messenger Worker
echo -n "Messenger Worker: "
systemctl is-active smartpay-messenger && echo "✓ Ishlayapti" || echo "✗ To'xtagan"

echo ""
echo "======================================"
echo "   Disk va Xotira"
echo "======================================"
df -h / | tail -1 | awk '{print "Disk: " $3 " / " $2 " (" $5 " ishlatilgan)"}'
free -h | grep Mem | awk '{print "RAM: " $3 " / " $2 " (" $7 " mavjud)"}'

echo ""
echo "======================================"
echo "   Loglar"
echo "======================================"
echo "Backend log: tail -f /var/www/smartpay/backend/var/log/prod.log"
echo "Nginx access: tail -f /var/log/nginx/access.log"
echo "Nginx error: tail -f /var/log/nginx/error.log"
echo "Messenger: journalctl -u smartpay-messenger -f"
STATUS_SCRIPT

chmod +x /usr/local/bin/smartpay-status.sh

log_success "Monitoring skripti yaratildi (/usr/local/bin/smartpay-status.sh)"

###############################################################################
# Yakuniy ma'lumotlar
###############################################################################
SERVER_IP=$(hostname -I | awk '{print $1}')

echo ""
echo -e "${GREEN}======================================"
echo "   🎉 DEPLOY MUVAFFAQIYATLI YAKUNLANDI!"
echo "======================================${NC}"
echo ""
log_success "SmartPay CRM to'liq o'rnatildi va ishga tushdi"
echo ""
echo -e "${BLUE}📍 Kirish ma'lumotlari:${NC}"
echo "   Frontend: http://$SERVER_IP"
echo "   Backend API: http://$SERVER_IP/api"
echo ""
echo -e "${BLUE}🔑 Login ma'lumotlari:${NC}"
echo "   Username: admin"
echo "   Password: $INITIAL_ADMIN_PASSWORD"
echo ""
echo -e "${YELLOW}⚠️  Muhim:${NC}"
echo "   1. Birinchi login'dan keyin admin parolni o'zgartiring!"
echo "   2. Barcha parollar /root/.smartpay_credentials faylida saqlanadi"
echo "   3. SSL sertifikat o'rnatish uchun:"
echo "      certbot --nginx -d domeningiz.uz"
echo ""
echo -e "${BLUE}🛠️  Foydali buyruqlar:${NC}"
echo "   Tizim holati: smartpay-status.sh"
echo "   Backend log: tail -f /var/www/smartpay/backend/var/log/prod.log"
echo "   Yangilash: cd /var/www/smartpay && git pull"
echo ""
echo -e "${GREEN}✅ Tayyor! Loyihangizni ochish mumkin: http://$SERVER_IP${NC}"
echo ""
