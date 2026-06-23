# SmartPay CRM - Tezkor Boshlash

## 🎯 3 Daqiqada Deploy Qilish

### 1️⃣ VPSga Kirish

```bash
ssh root@91.98.237.187
# Parol: Jasurbek6091!
```

### 2️⃣ Deploy Skriptini Ishga Tushirish

```bash
curl -o setup.sh https://raw.githubusercontent.com/farmonovjasur/smartpay_crm/main/deploy/setup.sh
chmod +x setup.sh
./setup.sh
```

### 3️⃣ Kutish (10-15 daqiqa)

Skript avtomatik ravishda hamma narsani o'rnatadi va sozlaydi.

### 4️⃣ Tayyor!

Loyihangiz ishga tushdi:
- **Frontend**: `http://91.98.237.187`
- **Backend API**: `http://91.98.237.187/api`

**Login:**
- Username: `admin`
- Parol: Skript oxirida ko'rsatiladi (yoki `/root/.smartpay_credentials` faylida)

---

## 📋 Nima O'rnatiladi?

✅ PHP 8.2 + Barcha kerakli extensionlar  
✅ MySQL 8.0 + Database  
✅ Nginx + Optimallashtirilgan konfiguratsiya  
✅ Node.js 20 + npm  
✅ Composer  
✅ SSL (Certbot)  
✅ Systemd services (Messenger worker)  
✅ Avtomatik backup (har kuni)  
✅ Firewall (UFW)  
✅ Monitoring tools  

---

## 🔄 Yangilash

```bash
ssh root@91.98.237.187
cd /var/www/smartpay/deploy
./update.sh
```

---

## 🛠️ Foydali Buyruqlar

```bash
# Tizim holati
smartpay-status.sh

# Loglar
tail -f /var/www/smartpay/backend/var/log/prod.log

# Service'larni restart qilish
systemctl restart php8.2-fpm smartpay-messenger
systemctl reload nginx

# Backup yaratish
/usr/local/bin/smartpay-backup.sh
```

---

## 🔐 Xavfsizlik (Muhim!)

Deploy'dan keyin ALBATTA bajaring:

1. **Admin parolni o'zgartirish** (birinchi login'dan keyin)
2. **Root parolni o'zgartirish**: `passwd root`
3. **SSH parolni o'zgartirish** yoki SSH key autentifikatsiyani sozlash

---

## 🌐 SSL (HTTPS) O'rnatish

Domeningizni serverga yo'naltirgandan keyin:

```bash
certbot --nginx -d domeningiz.uz -d www.domeningiz.uz
```

---

## 📞 Yordam

- **Batafsil qo'llanma**: `DEPLOYMENT_GUIDE.md`
- **Deploy ma'lumotlari**: `deploy/README.md`
- **GitHub**: https://github.com/farmonovjasur/smartpay_crm

---

**Omad!** 🚀
