# Port Description Değişikliği - Alarm Sistemi Kullanım Kılavuzu

## ✅ Sorun Çözüldü!

**Önceki Sorun**: Port açıklaması (description/connected_to) değiştirildiğinde alarm oluşmuyordu.

**Yeni Davranış**: Port açıklaması web arayüzünden değiştirildiğinde **otomatik olarak alarm oluşur**.

## 🎯 Nasıl Çalışır?

### 1. Port Açıklaması Değiştirme

1. **Index.php**'de bir switch'in port kartına tıklayın
2. "Düzenle" (kalem simgesi) butonuna tıklayın
3. **"Connection Bilgisi"** alanını değiştirin
   - Bu alan port açıklaması olarak kullanılıyor
   - Excel'den gelen ek bağlantı bilgileri
4. **"Kaydet"** butonuna tıklayın

### 2. Alarm Oluşumu

Port kaydedildikten sonra **otomatik olarak**:

✅ **Alarm Oluşturulur**
- Alarm Tipi: `description_changed`
- Severity: `MEDIUM`
- Başlık: "Port X açıklaması değişti"
- Mesaj: Eski ve yeni değerleri içerir

✅ **Kayıt Yapılır**
- `alarms` tablosunda alarm kaydı
- `port_change_history` tablosunda değişiklik geçmişi
- `port_status_data` SNMP tablosu senkronize edilir

✅ **Bildirim Gönderilir** (eğer aktifse)
- Telegram bildirimi
- Email bildirimi

### 3. Alarmı Görüntüleme

Sol menüden:
- **"Port Değişiklik Alarmları"** sayfasına gidin
- Yeni oluşan alarmı göreceksiniz
- Alarm detaylarında:
  - Eski değer (old_value)
  - Yeni değer (new_value)
  - Oluşma zamanı
  - Device ve port bilgisi

## 🔧 Ayarlar ve Konfigürasyon

### Bildirimleri Aktifleştirme

Eğer alarm oluşuyor ama bildirim gelmiyorsa:

```sql
-- SNMP Admin Panel → Alarm Tipleri ve Seviyeler
-- VEYA doğrudan SQL:

UPDATE alarm_severity_config 
SET telegram_enabled = TRUE, 
    email_enabled = TRUE,
    severity = 'MEDIUM'
WHERE alarm_type = 'description_changed';
```

Veya migration dosyasını çalıştırın:
```bash
cd Switchp/snmp_worker/migrations
mysql -u root -p switchdb < enable_description_change_notifications.sql
```

### Alarm Önceliği

- **MEDIUM**: Orta öncelikli (önerilen)
- **LOW**: Düşük öncelikli (sadece kayıt)
- **HIGH**: Yüksek öncelikli (acil)
- **CRITICAL**: Kritik (çok acil)

## 📊 Örnek Senaryo

### Senaryo 1: İlk Kez Açıklama Ekleme

**Adımlar**:
1. Port 12'ye tıkla → Düzenle
2. Connection Bilgisi: `(boş)` → `"Lobby ONU - Ruby 3232"`
3. Kaydet

**Sonuç**:
```
✅ Alarm Oluştu
Başlık: Port 12 açıklaması değişti
Mesaj: Port 12 (SW-BALO) açıklaması manuel olarak değiştirildi.

Eski değer: '(boş)'
Yeni değer: 'Lobby ONU - Ruby 3232'
```

### Senaryo 2: Açıklamayı Güncelleme

**Adımlar**:
1. Port 12'ye tıkla → Düzenle
2. Connection Bilgisi: `"Lobby ONU - Ruby 3232"` → `"Lobby ONU - Ruby 3232 - VLAN 50"`
3. Kaydet

**Sonuç**:
```
✅ Alarm Oluştu
Başlık: Port 12 açıklaması değişti
Mesaj: Port 12 (SW-BALO) açıklaması manuel olarak değiştirildi.

Eski değer: 'Lobby ONU - Ruby 3232'
Yeni değer: 'Lobby ONU - Ruby 3232 - VLAN 50'
```

### Senaryo 3: Aynı Portta Kısa Sürede Birden Fazla Değişiklik

**Adımlar**:
1. Port 12 açıklamasını değiştir (ilk değişiklik)
2. 10 dakika içinde tekrar değiştir (ikinci değişiklik)

**Sonuç**:
```
✅ Mevcut Alarm Güncellendi
- Occurrence count: 2'ye yükseldi
- Last occurrence: Güncel zamana güncellendi
- Message: En son değişikliği gösteriyor
- YENİ ALARM OLUŞMADI (1 saat içinde duplicate engellendi)
```

## ⚠️ Önemli Notlar

### 1. Alarm Oluşma Koşulları

Alarm **sadece şu durumlarda** oluşur:
- ✅ Description gerçekten değiştiğinde
- ✅ Boştan doluya veya doludan boşa değiştiğinde
- ❌ Aynı değer tekrar kaydedilirse oluşmaz

### 2. SNMP Device Gereksinimi

- Alarm oluşabilmesi için switch **SNMP sisteminde kayıtlı olmalı**
- Eğer switch SNMP'de yoksa:
  - Port güncellemesi başarılı
  - Alarm oluşmaz
  - Konsola uyarı yazılır: "Switch not configured in SNMP system"

### 3. Duplicate Engelleme

- Aynı port için **1 saat içinde** birden fazla description değişikliği:
  - Yeni alarm oluşturmaz
  - Mevcut alarmı günceller
  - `occurrence_count` arttırır

### 4. Performans

- Alarm oluşturma **asenkron** (non-blocking)
- Port güncelleme başarısını etkilemez
- Alarm API hatası olursa:
  - Port güncellemesi yine başarılı
  - Hata error log'a yazılır

## 🐛 Sorun Giderme

### Alarm Oluşmuyor

**Kontrol Listesi**:

1. ✅ **Description gerçekten değişti mi?**
   ```sql
   SELECT port_no, connected_to, updated_at 
   FROM ports 
   WHERE switch_id = X AND port_no = Y;
   ```

2. ✅ **Switch SNMP'de kayıtlı mı?**
   ```sql
   SELECT id, name FROM snmp_devices 
   WHERE ip_address = (SELECT ip FROM switches WHERE id = X);
   ```
   
   - Eğer sonuç boşsa → Switch SNMP'ye ekleyin

3. ✅ **Alarm oluştu ama görünmüyor mu?**
   ```sql
   SELECT * FROM alarms 
   WHERE alarm_type = 'description_changed' 
   ORDER BY first_occurrence DESC 
   LIMIT 10;
   ```

4. ✅ **Error log kontrolü**:
   ```bash
   tail -f /path/to/Switchp/port_update_errors.log
   tail -f /path/to/Switchp/port_change_api_errors.log
   ```

### Bildirim Gitmiyor

1. ✅ **Bildirimler aktif mi?**
   ```sql
   SELECT telegram_enabled, email_enabled 
   FROM alarm_severity_config 
   WHERE alarm_type = 'description_changed';
   ```
   
   - FALSE ise → Migration dosyasını çalıştırın

2. ✅ **Telegram/Email yapılandırması doğru mu?**
   - SNMP Admin Panel → Telegram/Email ayarları
   - Test Et butonunu kullanın

### Çok Fazla Alarm Oluşuyor

Eğer spam oluşuyorsa:

1. **Severity'yi düşürün**:
   ```sql
   UPDATE alarm_severity_config 
   SET severity = 'LOW' 
   WHERE alarm_type = 'description_changed';
   ```

2. **Bildirimleri kapatın**:
   ```sql
   UPDATE alarm_severity_config 
   SET telegram_enabled = FALSE, email_enabled = FALSE 
   WHERE alarm_type = 'description_changed';
   ```

3. **Alarmlar yine oluşur** (görünür) ama bildirim gitmez

## 📝 Veritabanı Tabloları

### 1. `alarms` - Alarm Kayıtları

```sql
SELECT 
    id,
    device_id,
    port_number,
    alarm_type,
    severity,
    status,
    title,
    message,
    old_value,      -- Eski description
    new_value,      -- Yeni description
    occurrence_count,
    first_occurrence,
    last_occurrence
FROM alarms 
WHERE alarm_type = 'description_changed';
```

### 2. `port_change_history` - Değişiklik Geçmişi

```sql
SELECT 
    device_id,
    port_number,
    change_type,
    change_timestamp,
    old_description,
    new_description,
    change_details,
    alarm_created,
    alarm_id
FROM port_change_history 
WHERE change_type = 'DESCRIPTION_CHANGED'
ORDER BY change_timestamp DESC;
```

### 3. `port_status_data` - SNMP Port Durumu

```sql
SELECT 
    device_id,
    port_number,
    port_alias,       -- Güncellenen açıklama
    last_seen
FROM port_status_data 
WHERE device_id = X AND port_number = Y;
```

## 🎓 İleri Seviye

### API Doğrudan Kullanımı

Manuel olarak alarm oluşturmak için:

```php
<?php
$alarmData = [
    'action' => 'create_description_alarm',
    'switchId' => 1,
    'portNo' => 12,
    'oldDescription' => 'Eski açıklama',
    'newDescription' => 'Yeni açıklama'
];

$ch = curl_init('http://localhost/Switchp/port_change_api.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($alarmData));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
$result = json_decode($response, true);

echo $result['success'] ? 'Başarılı' : 'Hata: ' . $result['message'];
?>
```

### Webhook Entegrasyonu

Dış sistemlerden alarm oluşturmak için aynı API kullanılabilir.

## 📞 Destek

Sorun devam ederse:
1. Error log dosyalarını kontrol edin
2. `PORT_DESCRIPTION_ALARM_SORUNU.md` dosyasını inceleyin
3. Veritabanı tablolarını kontrol edin
4. SNMP Admin Panel ayarlarını gözden geçirin

---

**Güncelleme Tarihi**: 15 Şubat 2026  
**Versiyon**: 1.0  
**İlgili Dosyalar**: 
- `updatePort.php` - Description değişikliği algılama
- `port_change_api.php` - Alarm oluşturma API
- `enable_description_change_notifications.sql` - Bildirim aktifleştirme
