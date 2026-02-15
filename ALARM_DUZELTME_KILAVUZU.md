# 🚨 ALARM OLUŞTURMA SORUNU - ÇÖZÜM KILAVUZU

## 📋 Sorun Özeti

**Kullanıcı Raporu**: "CCC sw üzerinde yaptığım değişiklik index sw portun sayfasına yansıyor fakat alarm olarak düşmüyor"

- ✅ Değişiklikler UI'da görünüyor
- ❌ Alarmlar oluşmuyor
- ❌ Hiçbir alarm düşmüyor artık

## 🔍 KÖK NEDEN ANALİZİ

Worker loglarından (**snmp_worker.log**) tespit edilen 2 kritik hata:

### Hata 1: Eksik Kolonlar (KRİTİK)
```
Unknown column 'port_status_data.port_type' in 'field list'
```

**Eksik kolonlar**:
- `port_type`
- `port_speed`
- `port_mtu`

**Etki**: Worker port verilerini sorgulayamıyor, değişiklik algılaması yapamıyor.

### Hata 2: Enum Değer Uyuşmazlığı (KRİTİK)
```
'online' is not among the defined enum values.
Enum name: devicestatus
Possible values: ONLINE, OFFLINE, UNREACHABLE, ERROR
```

**Etki**: Worker cihaz durumunu güncelleyemiyor, transaction geri dönüyor.

## 💥 Hata Zinciri

```
1. Worker cihazı poll ediyor → ✅ Başarılı
2. Port verilerini kaydetmeye çalışıyor → ❌ Eksik kolonlar hatası
3. Cihaz durumunu güncellemeye çalışıyor → ❌ Enum hatası  
4. Transaction geri dönüyor → ❌ Hiçbir veri kaydedilmiyor
5. Değişiklik algılaması çalışmıyor → ❌ Alarm oluşmuyor
```

## ✅ ÇÖZÜM ADIMLARI

### Adım 1: Database Migration'larını Çalıştır

**Windows (XAMPP)**:
```batch
cd C:\xampp\htdocs\Switchp\snmp_worker
hizli_duzelt.bat
```

VEYA manuel olarak:
```batch
cd C:\xampp\htdocs\Switchp\snmp_worker\migrations

REM Kritik migration - eksik kolonları ekle
C:\xampp\mysql\bin\mysql.exe -h 127.0.0.1 -u root switchdb < add_port_config_columns.sql

REM Enum değerlerini düzelt
C:\xampp\mysql\bin\mysql.exe -h 127.0.0.1 -u root switchdb < fix_status_enum_uppercase.sql
```

**Linux**:
```bash
cd /path/to/Switchp/snmp_worker/migrations

# Kritik migration - eksik kolonları ekle
mysql -h 127.0.0.1 -u root switchdb < add_port_config_columns.sql

# Enum değerlerini düzelt
mysql -h 127.0.0.1 -u root switchdb < fix_status_enum_uppercase.sql
```

### Adım 2: Database'i Doğrula

```sql
USE switchdb;

-- Kolonların eklendiğini kontrol et
DESCRIBE port_status_data;
-- port_type, port_speed, port_mtu kolonları görünmeli

-- Enum değerlerinin doğru olduğunu kontrol et
SHOW COLUMNS FROM snmp_devices LIKE 'status';
-- Type: enum('ONLINE','OFFLINE','UNREACHABLE','ERROR') olmalı

-- Mevcut cihaz durumlarını kontrol et
SELECT id, name, ip_address, status FROM snmp_devices;
-- Status değerleri büyük harf olmalı (ONLINE, OFFLINE, vb.)
```

### Adım 3: SNMP Worker'ı Yeniden Başlat

**Windows**:
```batch
REM Worker'ı durdur
taskkill /F /IM python.exe

REM 5 saniye bekle
timeout /t 5

REM Worker'ı başlat
cd C:\xampp\htdocs\Switchp\snmp_worker
python worker.py
```

**Linux (systemd)**:
```bash
sudo systemctl restart snmp-worker
```

**Linux (manual)**:
```bash
# Worker'ı durdur
pkill -f "python.*worker.py"

# 5 saniye bekle
sleep 5

# Worker'ı başlat
cd /path/to/Switchp/snmp_worker
python worker.py &
```

### Adım 4: Worker Durumunu Kontrol Et

**Windows**:
```batch
cd C:\xampp\htdocs\Switchp\snmp_worker
verify_worker.bat
```

**Linux/Windows (log kontrolü)**:
```bash
tail -50 logs/snmp_worker.log
```

**Aranacak mesajlar**:
- ✅ `Poll successful: XX ports collected`
- ✅ `Poll cycle complete: X/X successful`
- ❌ `Unknown column` - Hala migration gerekli
- ❌ `enum values` - Enum düzeltmesi gerekli
- ❌ `Database error` - Başka bir sorun var

### Adım 5: Alarm Oluşturma Testi

1. **Switch üzerinde değişiklik yap**:
   - Port description değiştir
   - VEYA MAC adres taşı
   - VEYA VLAN değiştir

2. **2-3 dakika bekle** (polling cycle)

3. **Database'de alarm kontrol et**:
```sql
USE switchdb;

-- En son alarmları listele
SELECT 
    id, 
    device_id, 
    alarm_type,
    status,
    severity,
    port_number,
    title,
    created_at
FROM alarms 
ORDER BY created_at DESC 
LIMIT 10;
```

4. **UI'da kontrol et**:
   - http://localhost/Switchp/ aç
   - "Port Değişiklik Alarmları" tab'ına git
   - Yeni alarm görünmeli

### Adım 6: Sürekli İzleme

Worker loglarını canlı takip et:

**Windows**:
```batch
powershell Get-Content -Path "C:\xampp\htdocs\Switchp\snmp_worker\logs\snmp_worker.log" -Wait -Tail 20
```

**Linux**:
```bash
tail -f /path/to/Switchp/snmp_worker/logs/snmp_worker.log
```

**Görülmesi gerekenler**:
```
Poll successful: 29 ports collected
Description changed on SW35-BALO port 5 from 'old' to 'new'
New alarm created: ID=123, fingerprint=SW35-BALO|5|...
```

## 🔧 SORUN GİDERME

### Sorun: Migration çalıştı ama hata devam ediyor

**Çözüm**: Worker'ı restart edin. SQLAlchemy modelleri bellekte cache'lenmiş olabilir.

```batch
taskkill /F /IM python.exe
timeout /t 5
python worker.py
```

### Sorun: "Table 'switchdb.snmp_devices' doesn't exist"

**Çözüm**: Model `snmp_devices` tablosu kullanıyor, tablo `switches` adıyla oluşturulmuş olabilir.

```sql
-- Tablo adını kontrol et
SHOW TABLES LIKE '%device%';
SHOW TABLES LIKE '%switch%';

-- Eğer switches tablosu varsa ve snmp_devices yoksa:
RENAME TABLE switches TO snmp_devices;
```

### Sorun: Migration'dan sonra hala "Unknown column" hatası

**Çözüm**: Migration idempotent, tekrar çalıştırılabilir:

```batch
cd C:\xampp\htdocs\Switchp\snmp_worker\migrations
C:\xampp\mysql\bin\mysql.exe -h 127.0.0.1 -u root switchdb < add_port_config_columns.sql
```

Sonra kolonları manuel kontrol et:
```sql
DESCRIBE port_status_data;
```

Eğer hala yoksa, manuel ekle:
```sql
ALTER TABLE port_status_data 
ADD COLUMN port_type VARCHAR(100),
ADD COLUMN port_speed BIGINT,
ADD COLUMN port_mtu INTEGER;
```

### Sorun: Worker başlamıyor

**Çözüm**: Python dependencies kontrol et:

```batch
cd C:\xampp\htdocs\Switchp\snmp_worker
python check_dependencies.py
```

Eksik paketleri kur:
```batch
pip install sqlalchemy pymysql pysnmp pyyaml
```

### Sorun: Alarm oluşuyor ama UI'da görünmüyor

**Çözüm**: 
1. Database'de alarm var mı kontrol et
2. Web server loglarını kontrol et
3. Browser console'da hata var mı bak (F12)
4. Port Alarms API'sini test et:
```
http://localhost/Switchp/port_change_api.php?action=get_alarms
```

## 📊 BAŞARI KRİTERLERİ

Sistem düzgün çalışıyorsa:

- [x] Worker loglarında `Poll successful` mesajları var
- [x] Worker loglarında database error yok
- [x] Database'de `port_status_data` tablosunda port_type, port_speed, port_mtu kolonları var
- [x] Database'de `snmp_devices.status` enum'u büyük harf değerler içeriyor
- [x] Port değişikliği yaptıktan 2-3 dakika sonra alarm oluşuyor
- [x] `alarms` tablosunda yeni kayıtlar ekleniyor
- [x] UI'da "Port Değişiklik Alarmları" sayfasında alarmlar görünüyor

## 🆘 HIZLI YARDIM

### Tam Sistem Sağlık Kontrolü

```sql
-- 1. Tablo ve kolon kontrolü
DESCRIBE port_status_data;
DESCRIBE snmp_devices;
DESCRIBE alarms;

-- 2. Aktif cihazlar
SELECT id, name, ip_address, status, last_poll_time, enabled 
FROM snmp_devices 
WHERE enabled = 1;

-- 3. Son polling verileri
SELECT device_id, poll_timestamp, success, error_message
FROM device_polling_data 
ORDER BY poll_timestamp DESC 
LIMIT 5;

-- 4. Son port değişiklikleri
SELECT device_id, port_number, change_type, change_timestamp, change_details
FROM port_change_history 
ORDER BY change_timestamp DESC 
LIMIT 10;

-- 5. Aktif alarmlar
SELECT device_id, alarm_type, severity, status, port_number, title, created_at
FROM alarms 
WHERE status = 'ACTIVE'
ORDER BY created_at DESC;
```

### Worker Durumu (Windows)

```batch
REM Worker çalışıyor mu?
tasklist | findstr python.exe

REM Son loglar
type "C:\xampp\htdocs\Switchp\snmp_worker\logs\snmp_worker.log" | more +1000
```

### Worker Durumu (Linux)

```bash
# Worker çalışıyor mu?
ps aux | grep worker.py

# Son loglar
tail -100 /path/to/logs/snmp_worker.log
```

## 📝 ÖZET - TESLİMAT ÖNCESİ KONTROL LİSTESİ

Kullanıcıya teslim etmeden önce:

1. [ ] `add_port_config_columns.sql` migration çalıştırıldı
2. [ ] `fix_status_enum_uppercase.sql` migration çalıştırıldı
3. [ ] SNMP worker restart edildi
4. [ ] Worker loglarında hata yok
5. [ ] Test değişikliği yapıldı (description change)
6. [ ] Alarm database'de oluştu
7. [ ] Alarm UI'da görünüyor
8. [ ] Notification gönderildi (eğer aktifse)

## 🎯 BEKLENTİLER

**Düzeltmeden Önce**:
- ❌ Worker polling sırasında crash oluyor
- ❌ Database hatalar var
- ❌ Değişiklikler algılanmıyor
- ❌ Alarm oluşmuyor

**Düzeltmeden Sonra**:
- ✅ Worker başarıyla polling yapıyor
- ✅ Database işlemleri sorunsuz
- ✅ Değişiklikler algılanıyor
- ✅ Alarmlar oluşuyor
- ✅ UI'da görünüyor
- ✅ Bildirimler gönderiliyor

## 📞 DESTEK

Sorun devam ederse:

1. **Worker logunu paylaş**:
   ```
   logs/snmp_worker.log (son 100 satır)
   ```

2. **Database durumunu paylaş**:
   ```sql
   DESCRIBE port_status_data;
   SHOW COLUMNS FROM snmp_devices LIKE 'status';
   ```

3. **Worker durumunu paylaş**:
   ```batch
   verify_worker.bat çıktısı
   ```

---

**Hazırlayan**: Copilot  
**Tarih**: 2026-02-15  
**Versiyon**: 1.0
