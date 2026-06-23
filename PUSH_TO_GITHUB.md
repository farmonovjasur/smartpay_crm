# GitHub'ga Yuklash Yo'riqnomasi

## 📤 Deployment Skriptlarni GitHub'ga Push Qilish

### 1. Git Status Tekshirish

```bash
cd c:\Users\user\Desktop\smartpay_crm
git status
```

### 2. Yangi Fayllarni Stage'ga Qo'shish

```bash
git add .gitattributes
git add DEPLOYMENT_GUIDE.md
git add QUICK_START.md
git add PUSH_TO_GITHUB.md
git add deploy/setup.sh
git add deploy/update.sh
git add deploy/rollback.sh
git add deploy/health-check.sh
git add deploy/README.md
```

### 3. Commit Qilish

```bash
git commit -m "Add complete deployment scripts and documentation

- Added automated deployment script (setup.sh)
- Added update script for deployments
- Added rollback script for emergency recovery
- Added health check monitoring script
- Added comprehensive deployment guides (DEPLOYMENT_GUIDE.md, QUICK_START.md)
- Updated deploy/README.md with detailed instructions
- Added .gitattributes for proper line endings in shell scripts"
```

### 4. GitHub'ga Push Qilish

```bash
git push origin main
```

---

## ✅ Push Qilingandan Keyin

GitHub'da skriptlar mavjud bo'lgandan keyin, VPSga deploy qilish juda oson bo'ladi:

```bash
# VPSga kirish
ssh root@91.98.237.187

# Deploy skriptini yuklash
curl -o setup.sh https://raw.githubusercontent.com/farmonovjasur/smartpay_crm/main/deploy/setup.sh

# Ishga tushirish
chmod +x setup.sh
./setup.sh
```

---

## 🔍 Tekshirish

Push qilingandan keyin GitHub'da quyidagi fayllar mavjud ekanligini tekshiring:

- ✅ `deploy/setup.sh`
- ✅ `deploy/update.sh`
- ✅ `deploy/rollback.sh`
- ✅ `deploy/health-check.sh`
- ✅ `deploy/README.md`
- ✅ `DEPLOYMENT_GUIDE.md`
- ✅ `QUICK_START.md`
- ✅ `.gitattributes`

GitHub repo: https://github.com/farmonovjasur/smartpay_crm

---

## 📝 Eslatma

Agar `.env` yoki maxfiy ma'lumotlar commit qilinmagan bo'lsa, bu to'g'ri!
`.gitignore` fayli ularni avtomatik exclude qiladi.

Deployment paytida bu fayllar avtomatik yaratiladi yoki nusxalanadi.
