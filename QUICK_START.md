# SmartPay CRM — Tezkor Boshlash

## 🎯 Deploy Qilish

### 1️⃣ VPSga kirish
```bash
ssh root@91.98.237.187
```

### 2️⃣ Deploy skriptini ishga tushirish
```bash
curl -o setup.sh https://raw.githubusercontent.com/farmonovjasur/smartpay_crm/main/deploy/setup.sh
chmod +x setup.sh
./setup.sh
```

### 3️⃣ Kutish (10-15 daqiqa)
Skript hamma narsani avtomatik o'rnatadi va sozlaydi.

### 4️⃣ Tayyor!
- **Frontend**: `http://91.98.237.187`
- **Backend API**: `http://91.98.237.187/api`
- **Login**: `admin` / parol skript oxirida ko'rsatiladi

---

## 🔄 Yangilash
```bash
ssh root@91.98.237.187
cd /var/www/smartpay/deploy
./update.sh
```

## 🛠️ Foydali buyruqlar
```bash
smartpay-status.sh                                      # Tizim holati
tail -f /var/www/smartpay/backend/var/log/prod.log      # Backend log
systemctl restart php8.2-fpm smartpay-messenger         # Restart
systemctl reload nginx                                   # Nginx reload
```

## 📖 Batafsil qo'llanma
Batafsil ma'lumot uchun: [DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md)
