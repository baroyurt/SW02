# Port Description Değişiklik Alarmı Çalışmıyor - Çözüm

## 🔴 Sorun

**Kullanıcı Şikayeti**: 
> "SW üzerinde Description değiştirdim ama değişiklik alarm olarak yansımadı normalde yansıyordu"

**Ne Oldu**:
- Switch cihazının kendisinde port açıklaması değiştirildi
- SNMP monitoring sistemi bu değişikliği algılamadı
- Alarm oluşmadı
- Önceden çalışıyordu, şimdi çalışmıyor

## 🔍 Kök Neden

SNMP Worker **çöküyor** çünkü veritabanında **eksik kolonlar var**:

```
ERROR: Unknown column 'port_status_data.port_type' in 'field list'
```

### Neden Alarm Oluşmuyor?

1. SNMP Worker cihazları poll etmeye çalışıyor
2. Database'den port bilgilerini çekmeye çalışıyor
3. `port_type`, `port_speed`, `port_mtu` kolonları yok
4. SQL hatası → Worker çöküyor
5. Worker çöktüğü için hiçbir değişiklik algılanamıyor
6. **Sonuç**: MAC değişiklikleri, VLAN değişiklikleri, Description değişiklikleri - HİÇBİRİ ALGILANMIYOR

### Neden Önceden Çalışıyordu?

Bu kolonlar Python kodunda (SQLAlchemy model) tanımlı ama veritabanına eklenmemiş. Muhtemelen:
- Kod güncellemesi yapıldı
- Migration çalıştırılmadı
- Worker yeniden başlatıldı
- Boom! Çöküş başladı

## ✅ Çözüm

### Adım 1: Veritabanı Migration

Eksik kolonları eklemek için migration scriptini çalıştır:

```bash
cd /home/runner/work/SW02/SW02/Switchp/snmp_worker
python migrations/add_port_config_columns.py
```

**Beklenen Çıktı**:
```
======================================================================
SNMP Worker - Add Port Configuration Columns Migration
======================================================================

✓ Configuration loaded successfully
  Database: switchdb
  Host: localhost:3306
  User: root

✓ Database engine created

✓ Database connection successful
  MySQL version: ...

→ Checking existing columns...
  ✗ Missing: port_type
  ✗ Missing: port_speed
  ✗ Missing: port_mtu

  Total missing columns: 3

→ Adding missing columns to port_status_data...

  ✓ Added column: port_type (VARCHAR(100))
  ✓ Added column: port_speed (BIGINT)
  ✓ Added column: port_mtu (INTEGER)

✓ Migration completed successfully!
```

### Adım 2: SNMP Worker'ı Yeniden Başlat

```bash
# Mevcut worker'ı durdur (eğer çalışıyorsa)
pkill -f "python.*worker.py" 

# Yeni worker başlat
cd /home/runner/work/SW02/SW02/Switchp/snmp_worker
nohup python worker.py > logs/worker_output.log 2>&1 &

# Veya systemd kullanıyorsan:
sudo systemctl restart snmp-worker
```

### Adım 3: Log Kontrolü

Worker'ın düzgün çalıştığını doğrula:

```bash
cd /home/runner/work/SW02/SW02/Switchp/snmp_worker
tail -f logs/snmp_worker.log
```

**Aranan Mesajlar**:
```
✓ İYİ: "Successfully polled SW35-BALO"
✓ İYİ: "Description changed on SW35-BALO port 12"
✓ İYİ: "Created alarm for description change"

✗ KÖTÜ: "Unknown column 'port_type'" - Hala varsa migration çalışmadı
✗ KÖTÜ: "Failed to poll device" - Başka bir sorun var
```

### Adım 4: Test

1. **Bir switch'e bağlan** (SSH/console)
2. **Port açıklamasını değiştir**:
   ```
   configure terminal
   interface GigabitEthernet1/0/12
   description TEST ALARM DEGISIKLIK
   end
   write memory
   ```

3. **2-3 dakika bekle** (polling interval)

4. **Port Alarmları sayfasını kontrol et**:
   - Sol menüden "Port Değişiklik Alarmları"
   - Yeni alarm görünmeli
   - Alarm tipi: `description_changed`
   - Eski ve yeni değerler gösterilmeli

5. **Veritabanını kontrol et**:
   ```sql
   SELECT * FROM alarms 
   WHERE alarm_type = 'description_changed' 
   ORDER BY first_occurrence DESC 
   LIMIT 5;
   ```

## 📊 Migration Detayları

### Eklenen Kolonlar

| Kolon | Tip | Açıklama |
|-------|-----|----------|
| `port_type` | VARCHAR(100) | Port interface tipi (ör: "ethernetCsmacd", "gigabitEthernet") |
| `port_speed` | BIGINT | Port hızı bps cinsinden (büyük sayılar için BIGINT) |
| `port_mtu` | INTEGER | Maximum Transmission Unit boyutu |

### SQL Komutu (Manuel)

Eğer Python scripti çalışmazsa manuel olarak:

```sql
USE switchdb;

-- Kolonları ekle
ALTER TABLE port_status_data ADD COLUMN port_type VARCHAR(100);
ALTER TABLE port_status_data ADD COLUMN port_speed BIGINT;
ALTER TABLE port_status_data ADD COLUMN port_mtu INTEGER;

-- Kontrol et
DESCRIBE port_status_data;
```

## 🔧 Sorun Giderme

### Problem: Migration Çalışmıyor

**Hata**: `ModuleNotFoundError: No module named 'config'`

**Çözüm**:
```bash
cd /home/runner/work/SW02/SW02/Switchp/snmp_worker
python -m venv venv
source venv/bin/activate  # Linux
# veya
venv\Scripts\activate  # Windows
pip install -r requirements.txt
python migrations/add_port_config_columns.py
```

### Problem: "Table doesn't exist"

**Hata**: `Table 'switchdb.port_status_data' doesn't exist`

**Çözüm**: İlk önce tabloları oluştur
```bash
python migrations/create_tables.py
```

### Problem: Worker Hala Çöküyor

**Kontrol Listesi**:

1. ✅ **Kolonlar gerçekten eklendi mi?**
   ```sql
   SHOW COLUMNS FROM port_status_data LIKE 'port_%';
   ```

2. ✅ **Worker yeniden başlatıldı mı?**
   ```bash
   ps aux | grep worker.py
   ```

3. ✅ **Config doğru mu?**
   ```bash
   cat config/config.yaml
   ```

4. ✅ **Başka eksik kolon var mı?**
   ```bash
   tail -100 logs/snmp_worker.log | grep "Unknown column"
   ```

### Problem: Alarm Oluşuyor Ama Bildirim Gitmiyor

**Neden**: `description_changed` alarmları için bildirimler kapalı

**Çözüm**: Bildirimleri aktifleştir
```sql
USE switchdb;

UPDATE alarm_severity_config 
SET telegram_enabled = TRUE, 
    email_enabled = TRUE,
    severity = 'MEDIUM'
WHERE alarm_type = 'description_changed';
```

Veya migration dosyasını kullan:
```bash
mysql -u root -p switchdb < migrations/enable_description_change_notifications.sql
```

## 📝 Neden Bu Kadar Önemli?

### Etkilenen İşlevler

Bu bug **sadece description değil**, TÜM SNMP monitoring'i devre dışı bırakıyor:

- ❌ Description değişiklikleri algılanmıyor
- ❌ MAC adresi değişiklikleri algılanmıyor  
- ❌ VLAN değişiklikleri algılanmıyor
- ❌ Port up/down durumu algılanmıyor
- ❌ Cihaz erişilebilirlik kontrolü yapılamıyor
- ❌ HİÇBİR ALARM OLUŞMUYOR

### Sistemin Kör Noktası

SNMP Worker çöktüğü için sistem "kör" kalmış durumda:
- Ağdaki değişiklikleri göremiyor
- Anormal durumları algılayamıyor
- IT ekibine bilgi veremiyor
- Security riski (yetkisiz değişiklikler farkedilmiyor)

## 🎯 Özet

### Sorunu Çözen 3 Adım

1. **Migration**: `python migrations/add_port_config_columns.py`
2. **Restart**: Worker'ı yeniden başlat
3. **Test**: Port description değiştir, alarm bekle

### Başarı Kriterleri

✅ Migration script başarıyla tamamlandı  
✅ Worker log'unda "Successfully polled" mesajları var  
✅ Description değişikliği alarm olarak yansıdı  
✅ Alarm Port Alarmları sayfasında görünüyor  
✅ (Opsiyonel) Bildirimler geliyor

### Tekrar Etmemesi İçin

1. **Her kod güncellemesinde migration kontrol et**
2. **Worker loglarını düzenli izle**
3. **Test ortamında önce dene**
4. **Database backup al**

## 📞 Destek

Sorun devam ederse:

1. **Log dosyalarını kontrol et**:
   - `Switchp/snmp_worker/logs/snmp_worker.log`
   - `Switchp/snmp_worker/logs/worker_output.log`

2. **Database durumunu kontrol et**:
   ```sql
   SHOW COLUMNS FROM port_status_data;
   SELECT COUNT(*) FROM alarms WHERE alarm_type = 'description_changed';
   ```

3. **Worker durumunu kontrol et**:
   ```bash
   ps aux | grep worker.py
   systemctl status snmp-worker
   ```

---

**Güncelleme Tarihi**: 15 Şubat 2026  
**Çözüm Durumu**: ✅ HAZIR  
**İlgili Dosyalar**: 
- `migrations/add_port_config_columns.py` - Migration script
- `models/database.py` - PortStatusData model tanımı
- `core/port_change_detector.py` - Description change detector
