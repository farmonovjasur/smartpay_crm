#!/bin/bash

###############################################################################
# SmartPay CRM - Rollback Skripti
# Oxirgi backup'dan qayta tiklash
###############################################################################

set -e

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

if [ "$EUID" -ne 0 ]; then 
    log_error "Iltimos root sifatida ishga tushiring: sudo ./rollback.sh"
    exit 1
fi

echo -e "${YELLOW}"
echo "======================================"
echo "   ⚠️  ROLLBACK - Oxirgi Holatga Qaytish"
echo "======================================"
echo -e "${NC}"

log_warning "Bu amal joriy database'ni o'chiradi va oxirgi backup'dan qayta tiklaydi!"
read -p "Davom etishni xohlaysizmi? (yes/no): " confirm

if [ "$confirm" != "yes" ]; then
    log_info "Bekor qilindi"
    exit 0
fi

###############################################################################
# Eng oxirgi backup'ni topish
###############################################################################
BACKUP_DIR="/var/backups/smartpay"
LATEST_DB_BACKUP=$(ls -t $BACKUP_DIR/db_*.sql.gz 2>/dev/null | head -1)

if [ -z "$LATEST_DB_BACKUP" ]; then
    log_error "Backup topilmadi!"
    exit 1
fi

log_info "Topilgan backup: $LATEST_DB_BACKUP"

###############################################################################
# Database credentials
###############################################################################
DB_NAME="smartpay_crm"
DB_USER="smartpay"
DB_PASS=$(grep DATABASE_PASSWORD /root/.smartpay_credentials | cut -d'=' -f2)

###############################################################################
# Database restore
###############################################################################
log_info "Database'ni qayta tiklash..."

# Drop va qayta yaratish
mysql -u root <<MYSQL_SCRIPT
DROP DATABASE IF EXISTS $DB_NAME;
CREATE DATABASE $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
MYSQL_SCRIPT

# Restore
gunzip < "$LATEST_DB_BACKUP" | mysql -u $DB_USER -p$DB_PASS $DB_NAME

log_success "Database qayta tiklandi"

###############################################################################
# Git'ni oxirgi commit'ga qaytarish
###############################################################################
log_info "Kodni oxirgi commit'ga qaytarish..."
cd /var/www/smartpay
git reset --hard HEAD~1

log_success "Kod oxirgi holatga qaytarildi"

###############################################################################
# Services restart
###############################################################################
log_info "Services'larni qayta yuklash..."

cd /var/www/smartpay/backend
php bin/console cache:clear --env=prod

systemctl restart php8.2-fpm
systemctl restart smartpay-messenger
systemctl reload nginx

log_success "Services qayta yuklandi"

echo ""
echo -e "${GREEN}======================================"
echo "   ✅ ROLLBACK BAJARILDI"
echo "======================================${NC}"
echo ""
log_success "Tizim oxirgi ishlaydigan holatga qaytarildi"
echo ""
