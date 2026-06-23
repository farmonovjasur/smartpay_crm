#!/bin/bash

###############################################################################
# SmartPay CRM - Health Check Skripti
###############################################################################

GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

SUCCESS="${GREEN}✓${NC}"
FAILED="${RED}✗${NC}"
WARNING="${YELLOW}!${NC}"

SERVER_IP=$(hostname -I | awk '{print $1}')

echo -e "${BLUE}"
cat << "EOF"
   ____                       _   ____             
  / ___| _ __ ___   __ _ _ __| |_|  _ \ __ _ _   _ 
  \___ \| '_ ` _ \ / _` | '__| __| |_) / _` | | | |
   ___) | | | | | | (_| | |  | |_|  __/ (_| | |_| |
  |____/|_| |_| |_|\__,_|_|   \__|_|   \__,_|\__, |
                                              |___/ 
                    Health Check
EOF
echo -e "${NC}"

echo "======================================"
echo "   🏥 SYSTEM HEALTH CHECK"
echo "======================================"
echo ""

###############################################################################
# Services Status
###############################################################################
echo -e "${BLUE}Services:${NC}"

check_service() {
    if systemctl is-active --quiet $1; then
        echo -e "  $SUCCESS $1"
        return 0
    else
        echo -e "  $FAILED $1"
        return 1
    fi
}

check_service "nginx"
check_service "php8.2-fpm"
check_service "mysql"
check_service "smartpay-messenger"

echo ""

###############################################################################
# Disk Space
###############################################################################
echo -e "${BLUE}Disk Space:${NC}"
df -h / | tail -1 | awk '{
    used = int(substr($5, 1, length($5)-1));
    if (used > 90) 
        printf "  \033[0;31m✗\033[0m %s / %s (%s used) - CRITICAL!\n", $3, $2, $5;
    else if (used > 75)
        printf "  \033[1;33m!\033[0m %s / %s (%s used) - Warning\n", $3, $2, $5;
    else
        printf "  \033[0;32m✓\033[0m %s / %s (%s used)\n", $3, $2, $5;
}'

echo ""

###############################################################################
# Memory
###############################################################################
echo -e "${BLUE}Memory:${NC}"
free -h | grep Mem | awk '{
    used_gb = substr($3, 1, length($3)-1);
    total_gb = substr($2, 1, length($2)-1);
    avail_gb = substr($7, 1, length($7)-1);
    percent = int((used_gb / total_gb) * 100);
    
    if (percent > 90)
        printf "  \033[0;31m✗\033[0m %s / %s (%d%% used) - CRITICAL!\n", $3, $2, percent;
    else if (percent > 75)
        printf "  \033[1;33m!\033[0m %s / %s (%d%% used) - Warning\n", $3, $2, percent;
    else
        printf "  \033[0;32m✓\033[0m %s / %s (%s available)\n", $3, $2, $7;
}'

echo ""

###############################################################################
# CPU Load
###############################################################################
echo -e "${BLUE}CPU Load (1min, 5min, 15min):${NC}"
uptime | awk -F'load average:' '{
    split($2, loads, ",");
    load1 = loads[1] + 0;
    load5 = loads[2] + 0;
    load15 = loads[3] + 0;
    
    if (load1 > 2 || load5 > 2)
        printf "  \033[0;31m✗\033[0m %.2f, %.2f, %.2f - HIGH!\n", load1, load5, load15;
    else if (load1 > 1.5 || load5 > 1.5)
        printf "  \033[1;33m!\033[0m %.2f, %.2f, %.2f\n", load1, load5, load15;
    else
        printf "  \033[0;32m✓\033[0m %.2f, %.2f, %.2f\n", load1, load5, load15;
}'

echo ""

###############################################################################
# Database Connection
###############################################################################
echo -e "${BLUE}Database:${NC}"
DB_USER="smartpay"
DB_PASS=$(grep DATABASE_PASSWORD /root/.smartpay_credentials 2>/dev/null | cut -d'=' -f2)
DB_NAME="smartpay_crm"

if mysql -u $DB_USER -p$DB_PASS $DB_NAME -e "SELECT 1;" &>/dev/null; then
    echo -e "  $SUCCESS Connection OK"
    
    # Table count
    TABLE_COUNT=$(mysql -u $DB_USER -p$DB_PASS $DB_NAME -se "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_NAME';")
    echo -e "  $SUCCESS $TABLE_COUNT tables"
    
    # Database size
    DB_SIZE=$(mysql -u $DB_USER -p$DB_PASS $DB_NAME -se "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) FROM information_schema.tables WHERE table_schema='$DB_NAME';")
    echo -e "  $SUCCESS ${DB_SIZE}MB size"
else
    echo -e "  $FAILED Connection failed!"
fi

echo ""

###############################################################################
# Backend API Check
###############################################################################
echo -e "${BLUE}Backend API:${NC}"
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/api 2>/dev/null || echo "000")

if [ "$HTTP_CODE" = "200" ] || [ "$HTTP_CODE" = "404" ]; then
    echo -e "  $SUCCESS API responding (HTTP $HTTP_CODE)"
else
    echo -e "  $FAILED API not responding (HTTP $HTTP_CODE)"
fi

echo ""

###############################################################################
# Frontend Check
###############################################################################
echo -e "${BLUE}Frontend:${NC}"
if [ -f "/var/www/smartpay/frontend/dist/index.html" ]; then
    echo -e "  $SUCCESS Build exists"
    
    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/ 2>/dev/null || echo "000")
    if [ "$HTTP_CODE" = "200" ]; then
        echo -e "  $SUCCESS Accessible (HTTP $HTTP_CODE)"
    else
        echo -e "  $FAILED Not accessible (HTTP $HTTP_CODE)"
    fi
else
    echo -e "  $FAILED Build not found"
fi

echo ""

###############################################################################
# SSL Certificate
###############################################################################
echo -e "${BLUE}SSL Certificate:${NC}"
SSL_CERT_PATH="/etc/letsencrypt/live"
if [ -d "$SSL_CERT_PATH" ]; then
    DOMAINS=$(ls $SSL_CERT_PATH 2>/dev/null)
    if [ -n "$DOMAINS" ]; then
        echo -e "  $SUCCESS Installed for: $DOMAINS"
        
        # Check expiry
        for domain in $DOMAINS; do
            CERT_FILE="$SSL_CERT_PATH/$domain/cert.pem"
            if [ -f "$CERT_FILE" ]; then
                EXPIRY=$(openssl x509 -in $CERT_FILE -noout -enddate 2>/dev/null | cut -d= -f2)
                EXPIRY_EPOCH=$(date -d "$EXPIRY" +%s 2>/dev/null || echo "0")
                NOW_EPOCH=$(date +%s)
                DAYS_LEFT=$(( ($EXPIRY_EPOCH - $NOW_EPOCH) / 86400 ))
                
                if [ $DAYS_LEFT -lt 7 ]; then
                    echo -e "    $FAILED $domain expires in $DAYS_LEFT days - RENEW NOW!"
                elif [ $DAYS_LEFT -lt 30 ]; then
                    echo -e "    $WARNING $domain expires in $DAYS_LEFT days"
                else
                    echo -e "    $SUCCESS $domain expires in $DAYS_LEFT days"
                fi
            fi
        done
    else
        echo -e "  $WARNING Not installed (HTTP only)"
    fi
else
    echo -e "  $WARNING Not installed (HTTP only)"
fi

echo ""

###############################################################################
# Last Backup
###############################################################################
echo -e "${BLUE}Last Backup:${NC}"
BACKUP_DIR="/var/backups/smartpay"
if [ -d "$BACKUP_DIR" ]; then
    LATEST_BACKUP=$(ls -t $BACKUP_DIR/db_*.sql.gz 2>/dev/null | head -1)
    if [ -n "$LATEST_BACKUP" ]; then
        BACKUP_TIME=$(stat -c %y "$LATEST_BACKUP" | cut -d'.' -f1)
        BACKUP_SIZE=$(du -h "$LATEST_BACKUP" | cut -f1)
        echo -e "  $SUCCESS $BACKUP_TIME ($BACKUP_SIZE)"
        
        # Check if backup is older than 2 days
        BACKUP_AGE=$(( ($(date +%s) - $(stat -c %Y "$LATEST_BACKUP")) / 86400 ))
        if [ $BACKUP_AGE -gt 2 ]; then
            echo -e "  $WARNING Backup is $BACKUP_AGE days old!"
        fi
    else
        echo -e "  $FAILED No backups found!"
    fi
else
    echo -e "  $FAILED Backup directory not found!"
fi

echo ""

###############################################################################
# Access URLs
###############################################################################
echo "======================================"
echo -e "${BLUE}Access URLs:${NC}"
echo "  Frontend: http://$SERVER_IP"
echo "  Backend:  http://$SERVER_IP/api"
echo ""

###############################################################################
# Useful Commands
###############################################################################
echo "======================================"
echo -e "${BLUE}Useful Commands:${NC}"
echo "  Logs: tail -f /var/www/smartpay/backend/var/log/prod.log"
echo "  Update: cd /var/www/smartpay/deploy && ./update.sh"
echo "  Backup: /usr/local/bin/smartpay-backup.sh"
echo "  Rollback: cd /var/www/smartpay/deploy && ./rollback.sh"
echo ""
