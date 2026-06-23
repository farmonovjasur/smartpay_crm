#!/bin/bash

###############################################################################
# SmartPay CRM - Yangilanishlarni Deploy Qilish Skripti
###############################################################################

set -e

# Ranglar
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

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

# Root tekshirish
if [ "$EUID" -ne 0 ]; then 
    log_error "Iltimos root sifatida ishga tushiring: sudo ./update.sh"
    exit 1
fi

echo -e "${GREEN}"
echo "======================================"
echo "   SmartPay CRM - Yangilash"
echo "======================================"
echo -e "${NC}"

###############################################################################
# 1. Backup olish
###############################################################################
log_info "Backup yaratilmoqda..."
/usr/local/bin/smartpay-backup.sh
log_success "Backup yaratildi"

###############################################################################
# 2. Git pull
###############################################################################
log_info "Yangi kodni GitHub'dan yuklab olish..."
cd /var/www/smartpay

# Stash local changes
git stash

# Pull latest
git pull origin main

log_success "Kod yangilandi"

###############################################################################
# 3. Backend yangilash
###############################################################################
log_info "Backend'ni yangilash..."
cd /var/www/smartpay/backend

# Dependencies
composer install --no-dev --optimize-autoloader --no-interaction

# Migrations
php bin/console doctrine:migrations:migrate --no-interaction

# Cache clear
php bin/console cache:clear --env=prod

# Permissions
chown -R www-data:www-data /var/www/smartpay/backend/var
chmod -R 775 /var/www/smartpay/backend/var

log_success "Backend yangilandi"

###############################################################################
# 4. Frontend yangilash
###############################################################################
log_info "Frontend'ni yangilash..."
cd /var/www/smartpay/frontend

# Dependencies
npm ci

# Build
npm run build

log_success "Frontend yangilandi"

###############################################################################
# 5. Services'larni qayta yuklash
###############################################################################
log_info "Services'larni qayta yuklash..."

systemctl restart php8.2-fpm
systemctl restart smartpay-messenger
systemctl reload nginx

log_success "Services qayta yuklandi"

###############################################################################
# Yakuniy
###############################################################################
SERVER_IP=$(hostname -I | awk '{print $1}')

echo ""
echo -e "${GREEN}======================================"
echo "   🎉 YANGILASH MUVAFFAQIYATLI!"
echo "======================================${NC}"
echo ""
echo "   Frontend: http://$SERVER_IP"
echo "   Backend API: http://$SERVER_IP/api"
echo ""
log_success "SmartPay CRM yangilandi va ishga tushdi"
echo ""
