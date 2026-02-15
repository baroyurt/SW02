# 🚀 Hızlı Kullanım Kılavuzu

## 📋 İçindekiler

1. [Yeni Kurulum](#yeni-kurulum)
2. [Güncelleme](#güncelleme)
3. [Sorun Giderme](#sorun-giderme)
4. [Sık Kullanılan Komutlar](#sık-kullanılan-komutlar)

---

## 🆕 Yeni Kurulum

### Adım 1: XAMPP Hazırlığı
```batch
# XAMPP Control Panel'i açın
# MySQL ve Apache'yi başlatın
```

### Adım 2: Database Oluşturma
```sql
CREATE DATABASE switchdb;
```

### Adım 3: Dosyaları Yerleştirme
```batch
# Projeyi C:\xampp\htdocs\ içine kopyalayın
# Dizin yapısı: C:\xampp\htdocs\Switchp\
```

### Adım 4: Otomatik Kurulum
```batch
cd C:\xampp\htdocs\Switchp\snmp_worker
update.bat
```

✅ Bu komut şunları yapar:
- Database backup oluşturur
- Tüm migration'ları uygular
- SNMP Worker'ı başlatır
- Sistem hazır!

---

## 🔄 Güncelleme

### Mevcut Sistemi Güncelleme

```batch
# 1. Güncellemeleri al (git)
cd C:\xampp\htdocs\Switchp
git pull origin main

# 2. update.bat çalıştır
cd snmp_worker
update.bat
```

### Manuel Güncelleme (Git kullanmadan)

```batch
# 1. Yeni dosyaları kopyala
# 2. update.bat çalıştır
cd C:\xampp\htdocs\Switchp\snmp_worker
update.bat
```

---

## 🐛 Sorun Giderme

### MySQL Bağlantı Hatası

**Hata**:
```
[HATA] MySQL'e baglanilamiyor!
```

**Çözüm**:
1. XAMPP Control Panel → MySQL "Start"
2. update.bat'ı tekrar çalıştır

---

### Python Bulunamadı

**Hata**:
```
[UYARI] Python bulunamadi
```

**Çözüm**:
1. Python 3.x'i yükle: https://www.python.org/downloads/
2. Kurulumda "Add to PATH" seçeneğini işaretle
3. update.bat'ı tekrar çalıştır

---

### SNMP Worker Çalışmıyor

**Kontroller**:
```batch
# Worker çalışıyor mu?
tasklist | findstr python

# Worker'ı başlat
cd C:\xampp\htdocs\Switchp\snmp_worker
python worker.py

# Log kontrolü
type logs\snmp_worker.log
```

---

### Dark Theme Görünmüyor

**Çözüm**:
1. Tarayıcıda `Ctrl + F5` (hard refresh)
2. Tarayıcı cache'ini temizle
3. Sayfayı yeniden yükle

---

## 💻 Sık Kullanılan Komutlar

### Database İşlemleri

```sql
-- Migration geçmişi
SELECT * FROM migration_history ORDER BY applied_at DESC LIMIT 10;

-- Migration istatistikleri
SELECT * FROM migration_stats;

-- Başarısız migration'lar
SELECT * FROM failed_migrations;

-- Alarm sayıları
SELECT 
    status,
    COUNT(*) as count 
FROM port_alarms 
GROUP BY status;
```

### Backup Yönetimi

```batch
# Manuel backup
cd C:\xampp\htdocs\Switchp\snmp_worker
C:\xampp\mysql\bin\mysqldump -u root switchdb > backups\manual_backup.sql

# Backup geri yükleme
C:\xampp\mysql\bin\mysql -u root switchdb < backups\manual_backup.sql
```

### SNMP Worker Yönetimi

```batch
# Worker durumu
tasklist | findstr python

# Worker'ı başlat
cd C:\xampp\htdocs\Switchp\snmp_worker
python worker.py

# Worker'ı durdur
taskkill /F /IM python.exe

# Log takibi (real-time)
powershell Get-Content logs\snmp_worker.log -Wait -Tail 50
```

### Log Dosyaları

```batch
# Update logları
dir C:\xampp\htdocs\Switchp\snmp_worker\logs\update_*.log

# SNMP Worker logları
type C:\xampp\htdocs\Switchp\snmp_worker\logs\snmp_worker.log

# Son 20 satır
powershell Get-Content logs\snmp_worker.log -Tail 20
```

---

## 🌐 Web Arayüzü

### Ana Sayfalar

```
Ana Dashboard:
http://localhost/Switchp/

SNMP Yönetim:
http://localhost/Switchp/snmp_admin.php

Port Alarmları:
http://localhost/Switchp/port_alarms.html
```

### Varsayılan Giriş

```
Kullanıcı Adı: admin
Şifre: [Kurulumda belirlenir]
```

---

## 📊 Sistem Kontrolü

### Sağlık Kontrolü Checklist

- [ ] XAMPP → MySQL çalışıyor
- [ ] XAMPP → Apache çalışıyor
- [ ] SNMP Worker çalışıyor (`tasklist | findstr python`)
- [ ] http://localhost/Switchp/ açılıyor
- [ ] Port alarmları görünüyor
- [ ] Telegram/Email bildirimleri çalışıyor

### Performance Kontrolü

```sql
-- En son alarm zamanı (2 dakikadan eski olmamalı)
SELECT MAX(created_at) as last_alarm FROM port_alarms;

-- Aktif switch sayısı
SELECT COUNT(*) FROM switches WHERE status = 'active';

-- SNMP polling başarı oranı
SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful,
    ROUND(SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 2) as success_rate
FROM polling_log
WHERE timestamp > DATE_SUB(NOW(), INTERVAL 1 HOUR);
```

---

## 🆘 Acil Durum

### Sistem Çöktü - Hızlı Kurtarma

```batch
# 1. Son backup'i geri yükle
cd C:\xampp\htdocs\Switchp\snmp_worker
dir backups\*.sql /O-D
C:\xampp\mysql\bin\mysql -u root switchdb < backups\[son_backup].sql

# 2. Worker'ı yeniden başlat
taskkill /F /IM python.exe
python worker.py

# 3. update.bat'ı çalıştır
update.bat
```

### Migration Geri Alma

```sql
-- Migration'ı sil
DELETE FROM migration_history WHERE migration_name = '[migration_adi]';

-- Backup'ten geri yükle
-- C:\xampp\mysql\bin\mysql -u root switchdb < backups\[backup].sql
```

---

## 📞 Yardım

### Log Dosyaları Nerede?

```
Update Logları:
C:\xampp\htdocs\Switchp\snmp_worker\logs\update_*.log

SNMP Worker:
C:\xampp\htdocs\Switchp\snmp_worker\logs\snmp_worker.log

Backuplar:
C:\xampp\htdocs\Switchp\snmp_worker\backups\*.sql
```

### Önemli Komutlar Özeti

```batch
# Güncelleme
cd C:\xampp\htdocs\Switchp\snmp_worker && update.bat

# Worker Başlat
cd C:\xampp\htdocs\Switchp\snmp_worker && python worker.py

# Worker Durdur
taskkill /F /IM python.exe

# Log Görüntüle
type logs\snmp_worker.log

# Backup
C:\xampp\mysql\bin\mysqldump -u root switchdb > backup.sql

# Backup Geri Yükle
C:\xampp\mysql\bin\mysql -u root switchdb < backup.sql
```

---

## ✅ Kontrol Listesi

### Günlük Bakım

- [ ] Worker çalışıyor mu? (`tasklist | findstr python`)
- [ ] Logları kontrol et (hata var mı?)
- [ ] Disk alanı yeterli mi? (backuplar için)
- [ ] Alarm sayısı normal mi?

### Haftalık Bakım

- [ ] Eski backup'ları temizle (>7 gün)
- [ ] Eski logları temizle (>30 gün)
- [ ] Database optimize (`OPTIMIZE TABLE port_alarms`)
- [ ] Güvenlik güncellemelerini kontrol et

---

**💡 İpucu**: Bu dosyayı yazdır ve bilgisayarın yanında tut!

**📅 Son Güncelleme**: 15 Şubat 2024
