# ⚡ HIZLI ÇÖZÜM - Description Alarm Sorunu

## 🔴 Problem
Switch'te description değiştiriyorum ama alarm düşmüyor

## ✅ Çözüm (3 Adım)

### 1️⃣ Migration Çalıştır
```bash
cd /home/runner/work/SW02/SW02/Switchp/snmp_worker
python migrations/add_port_config_columns.py
```

**Beklenen**: ✓ Added column: port_type, port_speed, port_mtu

### 2️⃣ SNMP Worker'ı Yeniden Başlat
```bash
# Durdur
pkill -f "python.*worker.py"

# Başlat
cd /home/runner/work/SW02/SW02/Switchp/snmp_worker
nohup python worker.py > logs/worker_output.log 2>&1 &

# VEYA systemd kullanıyorsan:
sudo systemctl restart snmp-worker
```

### 3️⃣ Test Et
1. Switch'e bağlan
2. Port description değiştir:
   ```
   configure terminal
   interface GigabitEthernet1/0/12
   description TEST DEGISIKLIK
   end
   write memory
   ```
3. 2-3 dakika bekle
4. "Port Değişiklik Alarmları" sayfasını kontrol et
5. Yeni alarm görünmeli ✓

## 🔍 Kontrol

### Log Kontrolü
```bash
tail -f /home/runner/work/SW02/SW02/Switchp/snmp_worker/logs/snmp_worker.log
```

**Aranacaklar**:
- ✅ İYİ: "Successfully polled"
- ✅ İYİ: "Description changed"
- ❌ KÖTÜ: "Unknown column"

### Database Kontrolü
```sql
-- Kolonlar eklendi mi?
SHOW COLUMNS FROM port_status_data LIKE 'port_%';

-- Alarmlar oluşuyor mu?
SELECT * FROM alarms 
WHERE alarm_type = 'description_changed' 
ORDER BY first_occurrence DESC 
LIMIT 5;
```

## ❓ Sorun Devam Ederse

### Hata: "ModuleNotFoundError"
```bash
cd /home/runner/work/SW02/SW02/Switchp/snmp_worker
pip install -r requirements.txt
```

### Hata: "Table doesn't exist"
```bash
python migrations/create_tables.py
```

### Manuel SQL (Son Çare)
```sql
USE switchdb;
ALTER TABLE port_status_data ADD COLUMN port_type VARCHAR(100);
ALTER TABLE port_status_data ADD COLUMN port_speed BIGINT;
ALTER TABLE port_status_data ADD COLUMN port_mtu INTEGER;
```

## 🎯 Neden Gerekli?

**Sorun**: Database'de eksik kolonlar var → SNMP Worker çöküyor → HİÇBİR değişiklik algılanmıyor

**Çözüm**: Eksik kolonları ekle → Worker çalışır → Alarmlar düşer ✓

## 📞 Detaylı Dokümantasyon

Daha fazla bilgi için:
- `SNMP_DESCRIPTION_ALARM_SORUNU_COZUM.md` - Tam kılavuz

---

**Son Güncelleme**: 15 Şubat 2026  
**Durum**: ✅ Çözüm Hazır - Deployment Bekleniyor
