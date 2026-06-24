# SmartPay CRM — Deploy Qo'llanmasi

## VPS Ma'lumotlari
- **Server**: Hetzner VPS
- **IP**: 91.98.237.187
- **OS**: Ubuntu 22.04 LTS
- **GitHub Repo**: https://github.com/farmonovjasur/smartpay_crm.git

---

## 🚀 Tezkor Deploy (Avtomatik)

### 1-qadam: VPSga kirish
```bash
ssh root@91.98.237.187
```

### 2-qadam: Tozalash (agar avval o'rnatilgan bo'lsa)
```bash
# Eski servicelarni to'xtatish
systemctl stop smartpay-messenger nginx php8.2-fpm 2>/dev/null || true
rm -f /etc/systemd/system/smartpay-messenger.service
rm -f /etc/nginx/sites-enabled/smartpay /etc/nginx/sites-available/smartpay
rm -rf /var/www/smartpay
rm -f /root/.smartpay_credentials
```

### 3-qadam: Deploy skriptini yuklab ishga tushirish
```bash
curl -o setup.sh https://raw.githubusercontent.com/farmonovjasur/smartpay_crm/main/deploy/setup.sh
chmod +x setup.sh
./setup.sh
```

Skript avtomatik ravishda:
- ✅ PHP 8.2, MySQL 8.0, Nginx, Node.js 20, Composer o'rnatadi
- ✅ GitHub'dan loyihani clone qiladi
- ✅ Database yaratadi va migratsiyalarni bajaradi
- ✅ Backend va Frontend'ni sozlaydi
- ✅ Nginx konfiguratsiyasini to'g'ri sozlaydi
- ✅ Systemd service yaratadi
- ✅ Backup va monitoring tizimini sozlaydi
- ✅ Firewall (UFW) konfiguratsiyalaydi

---

## 🔄 Yangilash

Kelajakda kod yangilanishlarini deploy qilish uchun:
```bash
ssh root@91.98.237.187
cd /var/www/smartpay/deploy
./update.sh
```

---

## 🧪 Tekshirish

### API test:
```bash
curl http://91.98.237.187/api/auth/login
```

### Frontend test:
Brauzerda ochish: http://91.98.237.187

### Login:
- Username: `admin`
- Password: skript oxirida ko'rsatiladi yoki `cat /root/.smartpay_credentials`

### Tizim holati:
```bash
smartpay-status.sh
```

---

## 📋 Loglar

```bash
# Backend log
tail -f /var/www/smartpay/backend/var/log/prod.log

# Nginx access log
tail -f /var/log/nginx/access.log

# Nginx error log
tail -f /var/log/nginx/error.log

# Messenger worker log
journalctl -u smartpay-messenger -f
```

---

## 🔐 Xavfsizlik

Deploy'dan keyin albatta bajaring:
1. **Admin parolni o'zgartirish** (birinchi login'dan keyin)
2. **SSH parolni o'zgartirish**: `passwd root`
3. **Fail2Ban o'rnatish**: `apt install -y fail2ban && systemctl enable fail2ban`

---

## Arxitektura

```
http://91.98.237.187
         │
       Nginx (port 80)
         │
    ┌────┴────┐
    │         │
 /api/*    Boshqa /*
    │         │
 PHP-FPM   Frontend
 (Symfony)  (React SPA)
    │      dist/index.html
 index.php
```

- **Frontend** va **Backend** bitta IP da, bitta Nginx server blokida ishlaydi
- `/api/*` so'rovlari → PHP-FPM → Symfony
- Qolgan barcha so'rovlar → React SPA (dist/index.html)
- Same-origin sxemasi: CORS muammosi yo'q, cookie'lar first-party
