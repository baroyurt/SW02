# 🚨 Alarm Sistemi Sorun Giderme Rehberi

## Sorun: "alarmlar hala düşmüyor sisteme"

### Hızlı Teşhis ✅

update.bat çalıştırdınız ama alarmlar hala oluşmuyor. Aşağıdaki adımları sırayla kontrol edin:

---

## 1. Python Bağımlılıkları Eksik ❌

### Sorun
```
[UYARI] create_tables.py hatali - devam ediliyor...
[UYARI] add_port_config_columns.py hatali - devam ediliyor...
```

**Neden**: Python migration'ları sqlalchemy, pymysql gibi paketleri bulamıyor.

### Çözüm

#### Seçenek A: Python Paketlerini Kur (Önerilen)
```bash
cd C:\xampp\htdocs\Switchp\snmp_worker
pip install -r requirements.txt
```

Eğer `pip` bulunamazsa:
```bash
python -m pip install -r requirements.txt
```

veya:
```bash
py -m pip install -r requirements.txt
```

#### Seçenek B: SQL-Only Migration (Daha Kolay)
Python paketleri kurmak istemiyorsanız, sadece SQL migration'ları kullanın:

```bash
cd C:\xampp\htdocs\Switchp\snmp_worker\migrations

# Kritik migration - MUTLAKA çalıştırın
C:\xampp\mysql\bin\mysql.exe -h 127.0.0.1 -u root switchdb < add_port_config_columns.sql
```

Bu migration **çok kritik** çünkü port_type, port_speed, port_mtu kolonlarını ekliyor.

---

## 2. Kritik Kolonlar Eksik ⚠️

### Kontrol Et
```sql
USE switchdb;
DESCRIBE port_status_data;
```

**Olması gerekenler**:
- `port_type` VARCHAR(100)
- `port_speed` BIGINT
- `port_mtu` INTEGER

### Yoksa Ekle
```bash
cd C:\xampp\htdocs\Switchp\snmp_worker\migrations
C:\xampp\mysql\bin\mysql.exe -h 127.0.0.1 -u root switchdb < add_port_config_columns.sql
```

---

## 3. SNMP Worker Çalışmıyor 🔧

### Worker Durumunu Kontrol Et

```bash
cd C:\xampp\htdocs\Switchp\snmp_worker
verify_worker.bat
```

Bu script:
- Python kurulu mu kontrol eder
- Worker process çalışıyor mu bakar
- Log dosyasını gösterir
- Database bağlantısını test eder

### Manuel Kontrol

**Çalışan process'leri listele:**
```bash
tasklist | findstr python.exe
```

**Worker logunu kontrol et:**
```bash
type C:\xampp\htdocs\Switchp\snmp_worker\logs\snmp_worker.log
```

### Worker'ı Yeniden Başlat

**1. Eski process'leri durdur:**
```bash
taskkill /F /IM python.exe
```

**2. Worker'ı başlat:**
```bash
cd C:\xampp\htdocs\Switchp\snmp_worker
python worker.py
```

Worker başladıktan sonra log'u takip edin:
```bash
# PowerShell ile
Get-Content logs\snmp_worker.log -Wait -Tail 50

# veya CMD ile
type logs\snmp_worker.log
```

---

## 4. Database Tabloları Eksik 🗄️

### Kontrol Et

```sql
USE switchdb;

-- Temel tablolar
SHOW TABLES LIKE 'snmp_devices';
SHOW TABLES LIKE 'alarms';
SHOW TABLES LIKE 'port_status_data';
SHOW TABLES LIKE 'acknowledged_port_mac';
SHOW TABLES LIKE 'alarm_severity_config';

-- Tracking tabloları
SHOW TABLES LIKE 'port_change_history';
SHOW TABLES LIKE 'mac_address_tracking';
```

### Eksik Tabloları Oluştur

Tüm migration'ları sırayla çalıştırın:
```bash
cd C:\xampp\htdocs\Switchp\snmp_worker\migrations

C:\xampp\mysql\bin\mysql.exe -h 127.0.0.1 -u root switchdb < create_alarm_severity_config.sql
C:\xampp\mysql\bin\mysql.exe -h 127.0.0.1 -u root switchdb < add_mac_tracking_tables.sql
C:\xampp\mysql\bin\mysql.exe -h 127.0.0.1 -u root switchdb < add_acknowledged_port_mac_table.sql
C:\xampp\mysql\bin\mysql.exe -h 127.0.0.1 -u root switchdb < add_port_config_columns.sql
```

---

## 5. Alarm Konfigürasyonu Eksik ⚙️

### Kontrol Et
```sql
USE switchdb;
SELECT * FROM alarm_severity_config;
```

En az bu alarm tipleri olmalı:
- `mac_moved`
- `mac_added`
- `mac_removed`
- `description_changed`
- `vlan_changed`
- `status_changed`

### Yoksa Ekle
```bash
cd C:\xampp\htdocs\Switchp\snmp_worker\migrations
C:\xampp\mysql\bin\mysql.exe -h 127.0.0.1 -u root switchdb < create_alarm_severity_config.sql
```

---

## 6. Cihazlar Poll Edilmiyor 📡

### Kontrol Et
```sql
USE switchdb;

-- Aktif cihazları listele
SELECT id, name, ip_address, status, enabled, last_poll_time 
FROM snmp_devices 
WHERE enabled = 1;

-- Son poll zamanları
SELECT 
    name,
    ip_address,
    last_poll_time,
    TIMESTAMPDIFF(MINUTE, last_poll_time, NOW()) AS minutes_since_poll
FROM snmp_devices
WHERE enabled = 1
ORDER BY last_poll_time DESC;
```

### last_poll_time NULL veya çok eski ise:

**Neden olabilir**:
1. Worker çalışmıyor
2. SNMP bağlantısı başarısız
3. Database kolonları eksik (port_type, port_speed, port_mtu)

**Çözüm**:
```bash
# 1. Kritik kolonları ekle
cd C:\xampp\htdocs\Switchp\snmp_worker\migrations
C:\xampp\mysql\bin\mysql.exe -h 127.0.0.1 -u root switchdb < add_port_config_columns.sql

# 2. Worker'ı yeniden başlat
cd C:\xampp\htdocs\Switchp\snmp_worker
taskkill /F /IM python.exe
python worker.py

# 3. Log'u izle
type logs\snmp_worker.log
```

---

## 7. Port Değişikliği Test Etme 🧪

### Manuel Test

**1. Bir switch'e bağlan ve port description değiştir:**
```
# Switch CLI'da
interface GigabitEthernet1/0/1
description TEST_DEGISIKLIK
```

**2. 2-3 dakika bekle (polling cycle)**

**3. Database'i kontrol et:**
```sql
USE switchdb;

-- Son 10 port değişikliğini göster
SELECT * FROM port_change_history 
ORDER BY change_timestamp DESC 
LIMIT 10;

-- Son 10 alarmı göster
SELECT 
    id,
    device_id,
    alarm_type,
    severity,
    status,
    port_number,
    title,
    created_at
FROM alarms
ORDER BY created_at DESC
LIMIT 10;
```

**4. UI'da kontrol et:**
- http://localhost/Switchp/index.php
- "Port Değişiklik Alarmları" sekmesini aç
- Yeni alarm görünmeli

---

## 8. Worker Log'unda Hata Mesajları 📋

### Yaygın Hatalar ve Çözümleri

#### A. "Unknown column 'port_type' in 'field list'"
```
ÇÖZÜM: add_port_config_columns.sql çalıştır
cd C:\xampp\htdocs\Switchp\snmp_worker\migrations
C:\xampp\mysql\bin\mysql.exe -h 127.0.0.1 -u root switchdb < add_port_config_columns.sql
```

#### B. "ModuleNotFoundError: No module named 'sqlalchemy'"
```
ÇÖZÜM: Python paketlerini kur
pip install sqlalchemy pymysql pysnmp configparser
```

#### C. "Can't connect to MySQL server"
```
ÇÖZÜM: MySQL çalışıyor mu kontrol et
- XAMPP Control Panel'i aç
- MySQL'in "Running" durumda olduğundan emin ol
```

#### D. "SNMP timeout"
```
ÇÖZÜM: SNMP bağlantı ayarlarını kontrol et
- Switch'te SNMP aktif mi?
- IP adresi doğru mu?
- Community string doğru mu? (genelde "public")
- Firewall engelliyor olabilir mi?
```

---

## 9. Adım Adım Tam Çözüm 🎯

Eğer hiçbir şey işe yaramazsa, sıfırdan başlayın:

### Step 1: Python Paketlerini Kur
```bash
cd C:\xampp\htdocs\Switchp\snmp_worker
pip install -r requirements.txt
```

### Step 2: Kritik SQL Migration'ı Çalıştır
```bash
cd migrations
C:\xampp\mysql\bin\mysql.exe -h 127.0.0.1 -u root switchdb < add_port_config_columns.sql
```

### Step 3: Tüm Tabloları Kontrol Et
```sql
USE switchdb;
SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_SCHEMA = 'switchdb' 
AND TABLE_NAME IN (
    'snmp_devices',
    'alarms', 
    'port_status_data',
    'port_change_history',
    'mac_address_tracking',
    'acknowledged_port_mac',
    'alarm_severity_config'
);
```

7 tablo da olmalı. Eksik varsa ilgili migration'ı çalıştırın.

### Step 4: Worker'ı Başlat
```bash
cd C:\xampp\htdocs\Switchp\snmp_worker

# Eski process'leri temizle
taskkill /F /IM python.exe

# Yeni worker başlat
python worker.py
```

### Step 5: Log'u İzle
```bash
# Yeni pencerede
type logs\snmp_worker.log

# veya PowerShell'de real-time
Get-Content logs\snmp_worker.log -Wait -Tail 50
```

**Görmek istediğiniz**:
```
INFO - Starting SNMP worker
INFO - Polling device SW35-BALO (172.18.1.214)
INFO - Successfully polled 24 ports
INFO - Device SW35-BALO status: ONLINE
```

### Step 6: Port Değişikliği Yap ve Test Et
```
1. Switch'te port description değiştir
2. 2-3 dakika bekle
3. Database'i kontrol et:
   SELECT * FROM port_change_history ORDER BY change_timestamp DESC LIMIT 5;
4. Alarm oluştu mu kontrol et:
   SELECT * FROM alarms ORDER BY created_at DESC LIMIT 5;
```

---

## 10. Hala Çalışmıyorsa 🆘

### Debug Modu

Worker'ı debug mode'da çalıştırın:
```bash
cd C:\xampp\htdocs\Switchp\snmp_worker
python worker.py --debug
```

### Tek Cihazı Test Et

Sadece bir cihazı poll edin:
```bash
python -c "
from core.snmp_poller import SNMPPoller
from config.config_loader import Config
config = Config()
poller = SNMPPoller(config)
# Manuel poll test
"
```

### Log Seviyesini Arttır

`config/config.yaml` dosyasında:
```yaml
logging:
  level: DEBUG
  console_level: DEBUG
```

### Support İçin Gerekli Bilgiler

Eğer sorunu çözemediyseniz, şu bilgileri toplayın:

1. **Worker log son 100 satır:**
   ```bash
   type logs\snmp_worker.log | tail -100
   ```

2. **Database tablo listesi:**
   ```sql
   SHOW TABLES FROM switchdb;
   ```

3. **port_status_data kolonları:**
   ```sql
   DESCRIBE port_status_data;
   ```

4. **Son poll zamanları:**
   ```sql
   SELECT name, last_poll_time FROM snmp_devices;
   ```

5. **Python version:**
   ```bash
   python --version
   ```

6. **Kurulu paketler:**
   ```bash
   pip list | findstr -i "sqlalchemy pymysql pysnmp"
   ```

---

## ✅ Başarı Kontrol Listesi

Sistem düzgün çalışıyorsa:

- [ ] `verify_worker.bat` çalıştırınca tüm kontroller OK
- [ ] `snmp_devices` tablosunda `last_poll_time` güncel (son 5 dakika)
- [ ] `port_status_data` tablosunda port_type, port_speed, port_mtu kolonları var
- [ ] `alarms` tablosunda en az birkaç alarm var
- [ ] Port description değiştirince 2-3 dakika içinde alarm oluşuyor
- [ ] UI'da "Port Değişiklik Alarmları" sayfası alarmları gösteriyor
- [ ] Worker log'unda SNMP polling başarılı mesajları var

---

## 📚 İlgili Dökümanlar

- `README.md` - Genel sistem açıklaması
- `HIZLI_KULLANIM.md` - Hızlı başlangıç
- `OZET.md` - Detaylı özellikler
- `verify_worker.bat` - Worker durum kontrolü
- `update.bat` - Otomatik güncelleme script'i

---

## 🎯 Özet: En Sık Sorun

**%90 durumda sorun şu 2 şeyden biridir:**

1. **Python bağımlılıkları eksik** → `pip install -r requirements.txt`
2. **port_type/port_speed/port_mtu kolonları yok** → `add_port_config_columns.sql` çalıştır

Bu ikisini düzeltin, worker'ı yeniden başlatın. Sorun çözülür.
