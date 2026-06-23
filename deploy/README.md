# SmartPay CRM Backend — Deploy

## 🚀 Tezkor Deploy (Tavsiya Etiladi)

### Avtomatik O'rnatish

VPSga kirib quyidagi buyruqni bajaring:

```bash
# VPSga kirish
ssh root@91.98.237.187

# Deploy skriptini GitHub'dan yuklash
curl -o setup.sh https://raw.githubusercontent.com/farmonovjasur/smartpay_crm/main/deploy/setup.sh

# Ruxsat berish va ishga tushirish
chmod +x setup.sh
./setup.sh
```

Skript avtomatik ravishda:
- ✅ PHP 8.2, MySQL 8.0, Nginx, Node.js, Composer o'rnatadi
- ✅ Database va foydalanuvchi yaratadi
- ✅ Backend va Frontend deploy qiladi
- ✅ Nginx konfiguratsiyalarini sozlaydi
- ✅ Systemd service'larni yaratadi
- ✅ Backup tizimini o'rnatadi
- ✅ Firewall sozlaydi

**Vaqt:** ~10-15 daqiqa

---

## 📋 Qo'lda Deploy (Agar Avtomatik Ishlamasa)

To'liq qo'lda deploy uchun loyihaning bosh papkasidagi `DEPLOYMENT_GUIDE.md` faylini ko'ring.

---

## 🔄 Yangilanishlarni Deploy Qilish

Loyihani yangilash uchun:

```bash
# Avtomatik yangilash skripti
cd /var/www/smartpay/deploy
./update.sh
```

Yoki qo'lda:

```bash
cd /var/www/smartpay
git pull origin main

# Backend
cd backend
composer install --no-dev --optimize-autoloader
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console cache:clear --env=prod

# Frontend
cd ../frontend
npm ci
npm run build

# Services restart
systemctl restart php8.2-fpm smartpay-messenger
systemctl reload nginx
```

---

## 🛠️ Foydali Buyruqlar

### Tizim Holati
```bash
# Umumiy holat
smartpay-status.sh

# Individual service'lar
systemctl status nginx
systemctl status php8.2-fpm
systemctl status mysql
systemctl status smartpay-messenger
```

### Loglarni Ko'rish
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

### Backup
```bash
# Manual backup
/usr/local/bin/smartpay-backup.sh

# Backup'larni ko'rish
ls -lh /var/backups/smartpay/

# Backup restore qilish
gunzip < /var/backups/smartpay/db_2024-01-15_02-00-00.sql.gz | mysql -u smartpay -p smartpay_crm
```

### Cache Tozalash
```bash
cd /var/www/smartpay/backend
php bin/console cache:clear --env=prod
```

### Service'larni Qayta Yuklash
```bash
systemctl restart php8.2-fpm
systemctl restart smartpay-messenger
systemctl reload nginx
```

---

## 🔐 Xavfsizlik

### Parollarni Ko'rish
```bash
cat /root/.smartpay_credentials
```

Bu faylda saqlanadi:
- Database paroli
- Admin paroli

### SSL O'rnatish

Domen nomini serverga yo'naltirgandan keyin:

```bash
# Backend uchun
certbot --nginx -d api.yourdomain.com

# Frontend uchun
certbot --nginx -d yourdomain.com -d www.yourdomain.com
```

### SSH Xavfsizligi

1. **Root parolni o'zgartirish:**
```bash
passwd root
```

2. **SSH key autentifikatsiya** (tavsiya etiladi)
3. **Fail2Ban o'rnatish:**
```bash
apt install -y fail2ban
systemctl enable fail2ban
```

---

## 📊 Monitoring

### Tizim Resurslari
```bash
# CPU va RAM
htop

# Disk space
df -h

# Network
iftop
```

### Performance Monitoring
```bash
# PHP-FPM status
systemctl status php8.2-fpm

# Nginx connections
ss -tulpn | grep :80
```

---

## 🐛 Muammolarni Hal Qilish

### 500 Internal Server Error
```bash
# Backend logs
tail -f /var/www/smartpay/backend/var/log/prod.log

# Permissions
chown -R www-data:www-data /var/www/smartpay/backend/var
chmod -R 775 /var/www/smartpay/backend/var

# Cache clear
cd /var/www/smartpay/backend
php bin/console cache:clear --env=prod
```

### Database Connection Error
```bash
# MySQL holati
systemctl status mysql

# Connection test
mysql -u smartpay -p smartpay_crm -e "SELECT 1;"

# .env.local faylini tekshiring
cat /var/www/smartpay/backend/.env.local
```

### CORS Error
```bash
# .env.local'dagi CORS_ALLOWED_ORIGIN sozlamasini tekshiring
nano /var/www/smartpay/backend/.env.local

# Frontend build'ni qayta qiling
cd /var/www/smartpay/frontend
npm run build
```

### Messenger Worker To'xtagan
```bash
# Status
systemctl status smartpay-messenger

# Restart
systemctl restart smartpay-messenger

# Logs
journalctl -u smartpay-messenger -n 50
```

### Nginx Xatosi
```bash
# Konfiguratsiya test
nginx -t

# Error logs
tail -f /var/log/nginx/error.log

# Restart
systemctl restart nginx
```

---

## 📦 Arxitektura

```
/var/www/smartpay/
├── backend/
│   ├── public/            # Nginx root
│   ├── src/               # PHP code
│   ├── var/               # Cache, logs
│   ├── .env.local         # Production config
│   └── config/jwt/        # JWT keys
├── frontend/
│   └── dist/              # Build output (Nginx root)
└── deploy/
    ├── setup.sh           # Avtomatik deploy skripti
    ├── update.sh          # Yangilash skripti
    └── README.md          # Bu fayl
```

### Nginx Routing:
- `http://server_ip/` → Frontend (React SPA)
- `http://server_ip/api` → Backend (Symfony API)

### Systemd Services:
- `nginx` - Web server
- `php8.2-fpm` - PHP processor
- `mysql` - Database
- `smartpay-messenger` - Background job processor

### Cron Jobs:
- Database backup (har kuni soat 2:00 da)

### Firewall (UFW):
- Port 22 (SSH)
- Port 80 (HTTP)
- Port 443 (HTTPS)

---

## 🎯 Requirements

### Server (Minimum):
- **CPU**: 2 vCPU
- **RAM**: 4 GB
- **Disk**: 40 GB SSD
- **OS**: Ubuntu 22.04 LTS

### Software:
- PHP 8.2+ (with extensions: ctype, iconv, json, mbstring, openssl, pdo_mysql, intl, bcmath, gd, zip, xml)
- MySQL 8.0+
- Nginx
- Composer 2.x
- Node.js 20+
- Git

---

## ✅ Post-Deploy Checklist

- [ ] VPS sozlangan va SSH orqali kirish mumkin
- [ ] `setup.sh` skripti muvaffaqiyatli bajarildi
- [ ] Frontend ochiladi: `http://server_ip`
- [ ] Backend API javob qaytaradi: `http://server_ip/api`
- [ ] Admin panel'ga kirish mumkin (username: admin)
- [ ] SSL sertifikat o'rnatilgan (agar domen mavjud bo'lsa)
- [ ] Backup cron job ishlayapti
- [ ] Firewall konfiguratsiyalangan
- [ ] Admin paroli o'zgartirildi
- [ ] Root paroli o'zgartirildi

---

## 📞 Qo'shimcha Yordam

Muammolar yuzaga kelsa:

1. **Loglarni tekshiring** (yuqorida ko'rsatilgan)
2. **Service'lar holatini ko'ring**: `smartpay-status.sh`
3. **Backup'dan qayta tiklang** (agar kerak bo'lsa)
4. **GitHub Issues**: https://github.com/farmonovjasur/smartpay_crm/issues

---

**Muvaffaqiyatli deployment!** 🎉
