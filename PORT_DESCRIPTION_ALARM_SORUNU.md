# Port Description Değişikliği - Alarm Oluşmama Sorunu

## 🔍 Sorun Tanımı

**Kullanıcı Bildirimi**: "Test için Description değiştirdim alarm olarak düşmedi ama sw içindeki açıklamada değişti alarm neden düşmedi kontrol et"

**Gerçekleşen**:
- ✅ Web UI'dan port "Connection Bilgisi" alanı güncellendi
- ✅ Switch içinde açıklama görünüyor (UI'da)
- ❌ Port description değişikliği için alarm oluşmadı
- ❌ Kullanıcı bilgilendirilmedi

## 🔬 Teknik Analiz

### Problem 1: İki Farklı Veritabanı Sistemi

Sistemde **iki ayrı port yönetimi** var:

#### 1. Web UI Sistemi (Eski)
- **Tablo**: `ports`
- **Kullanım**: Web arayüzü (index.php) manuel girişler
- **Alanlar**: `device`, `ip`, `mac`, `connected_to` (açıklama alanı)
- **API**: `updatePort.php` - Manuel güncellemeler

#### 2. SNMP Worker Sistemi (Yeni)
- **Tablo**: `port_status_data`
- **Kullanım**: SNMP worker otomatik polling
- **Alanlar**: `port_alias`, `port_description` (SNMP'den gelen açıklamalar)
- **Change Detector**: `port_change_detector.py` - Otomatik değişiklik algılama

### Problem 2: Alarm Oluşturma Süreci

Alarm oluşturması için gerekli adımlar:

```
1. SNMP Worker → Switch'i SNMP ile poll eder
2. Switch'ten → port_alias / port_description alır
3. Database → port_status_data tablosuna yazar
4. PortChangeDetector → Yeni veri ile eski snapshot'ı karşılaştırır
5. Farklılık varsa → _detect_description_change() tetiklenir
6. DatabaseManager → get_or_create_alarm() ile alarm oluşturur
7. AlarmManager → Bildirim gönderir (eğer aktifse)
```

**SORUN**: Web UI'dan yapılan manuel değişiklik bu akışın dışında kalıyor!

```
Manuel Değişiklik:
Web UI → updatePort.php → ports tablosu (SNMP Worker bu tabloyu okumuyor!)
                               ↓
                          ❌ Alarm sistemi bypass ediliyor
```

### Problem 3: Alarm Bildirimleri Kapalı

`create_alarm_severity_config.sql` (Line 25):
```sql
('description_changed', 'LOW', FALSE, FALSE, 'Port açıklaması değişti'),
```

- `telegram_enabled`: **FALSE** → Telegram bildirimi YOK
- `email_enabled`: **FALSE** → Email bildirimi YOK
- `severity`: **LOW** → Düşük öncelikli alarm

Bu ayar ile alarm oluşsa bile **kullanıcı bilgilendirilmiyor**.

## 💡 Çözüm Seçenekleri

### ⭐ Seçenek 1: Manuel Değişiklikleri SNMP Sistemine Entegre Et (ÖNERİLEN)

Web UI'dan yapılan değişiklikleri SNMP alarm sistemine bildirin.

#### Uygulama:

**1. `updatePort.php` Güncellemesi:**
```php
// Port güncellendikten sonra
if ($connected_to değişti) {
    // SNMP tablosunu da güncelle
    $updateSNMP = $conn->prepare("
        UPDATE port_status_data 
        SET port_alias = ?, 
            last_seen = NOW() 
        WHERE device_id = (
            SELECT id FROM snmp_devices 
            WHERE ip_address = (SELECT ip FROM switches WHERE id = ?)
        ) 
        AND port_number = ?
    ");
    $updateSNMP->bind_param("sii", $connected_to, $switchId, $portNo);
    $updateSNMP->execute();
    
    // Alarm oluştur (PHP'den)
    require_once 'port_change_api.php';
    create_port_alarm(
        $switchId, 
        $portNo, 
        'description_changed',
        'MEDIUM',
        "Port $portNo açıklaması değişti",
        "Eski: $old_value\nYeni: $connected_to"
    );
}
```

**Avantajlar**:
- ✅ Tüm değişiklikler tek sistemde izlenir
- ✅ Manuel ve SNMP değişiklikleri eşit işlenir
- ✅ Alarm mekanizması tutarlı çalışır
- ✅ Kullanıcı anında bilgilendirilir

**Dezavantajlar**:
- ⚠️ `updatePort.php` kod ekleme gerektirir
- ⚠️ `port_change_api.php` API oluşturulmalı

### Seçenek 2: Alarm Bildirimlerini Aktif Et (HIZLI ÇÖZÜM)

Sadece bildirimleri aktifleştirin.

#### Uygulama:

```sql
UPDATE alarm_severity_config 
SET telegram_enabled = TRUE, 
    email_enabled = TRUE,
    severity = 'MEDIUM'
WHERE alarm_type = 'description_changed';
```

**Avantajlar**:
- ✅ Hızlı uygulama (SQL komutu)
- ✅ Kod değişikliği yok

**Dezavantajlar**:
- ❌ Manuel değişiklikler hala algılanmıyor
- ❌ Sadece SNMP polling sonrası alarmlar çalışır
- ❌ Gerçek zamanlı değil (polling aralığı kadar gecikme)

### Seçenek 3: SNMP ile Switch'e Yazma Özelliği Ekle

Switch'in kendi description'ını güncelleyin, SNMP polling'de algıla.

#### Uygulama:

```php
// updatePort.php
if ($connected_to değişti) {
    // SNMP SET ile switch'e yaz
    snmpset($switch_ip, $community, "ifAlias.$port_index", 's', $connected_to);
    
    // Sonraki SNMP poll'da otomatik algılanacak
}
```

**Avantajlar**:
- ✅ Switch'in gerçek durumunu günceller
- ✅ SNMP sistemi doğal olarak algılar
- ✅ Tutarlı veri kaynağı

**Dezavantajlar**:
- ⚠️ SNMP write yetkisi gerekir (güvenlik riski)
- ⚠️ Her vendor için farklı OID
- ⚠️ Karmaşık uygulama

### Seçenek 4: Yeni API Endpoint Oluştur

Özel description update API'si.

#### Uygulama:

```php
// port_description_api.php (YENİ DOSYA)
<?php
include "db.php";

$data = json_decode(file_get_contents("php://input"), true);

$switchId = $data['switchId'];
$portNo = $data['port'];
$newDescription = $data['description'];

// 1. Eski değeri al
$old = getOldDescription($switchId, $portNo);

// 2. Web UI tablosunu güncelle
updatePortsTable($switchId, $portNo, $newDescription);

// 3. SNMP tablosunu güncelle
updateSNMPTable($switchId, $portNo, $newDescription);

// 4. Alarm oluştur
createDescriptionAlarm($switchId, $portNo, $old, $newDescription);

// 5. Response
echo json_encode(['success' => true]);
?>
```

**Avantajlar**:
- ✅ Temiz kod yapısı
- ✅ Tek sorumluluk prensibi
- ✅ Test edilebilir

**Dezavantajlar**:
- ⚠️ Yeni dosya oluşturma
- ⚠️ Mevcut kod refactoring

## 🚀 Önerilen Uygulama Planı

### Aşama 1: Hızlı Test (5 dakika)

Alarm bildirimlerini aktifleştirin:

```sql
UPDATE alarm_severity_config 
SET telegram_enabled = TRUE, 
    email_enabled = TRUE,
    severity = 'MEDIUM'
WHERE alarm_type = 'description_changed';
```

**Test**: SNMP worker'ın bir sonraki poll'unda alarm oluşup oluşmadığını kontrol edin.

### Aşama 2: Kalıcı Çözüm (1-2 saat)

**Seçenek 1**'i uygulayın - Manuel değişiklikleri SNMP sistemine entegre edin.

#### Dosyalar:

1. **`Switchp/port_change_api.php`** (YENİ)
   - Alarm oluşturma API'si
   - PHP'den Python SNMP sistemine köprü

2. **`Switchp/updatePort.php`** (GÜNCELLEME)
   - `connected_to` değişikliği algılama
   - Alarm API'sini çağırma

3. **`Switchp/snmp_worker/migrations/add_description_tracking.sql`** (YENİ)
   - Manuel değişiklik tracking tablosu (opsiyonel)

### Aşama 3: UI İyileştirmesi (30 dakika)

Port modal'ında bilgilendirme mesajı:

```html
<small style="color: var(--warning);">
    <i class="fas fa-bell"></i> 
    Açıklama değiştirildiğinde alarm oluşacak ve bildirim gönderilecek.
</small>
```

## 📊 Etki Analizi

### Mevcut Durum:
- ❌ Manuel değişiklikler izlenmiyor
- ❌ Alarmlar oluşmuyor
- ❌ Kullanıcılar bilgilendirilmiyor
- ⚠️ Veri tutarsızlığı (2 farklı tablo)

### Seçenek 1 Uygulandıktan Sonra:
- ✅ Tüm değişiklikler izleniyor
- ✅ Alarmlar gerçek zamanlı oluşuyor
- ✅ Kullanıcılar anında bilgilendiriliyor
- ✅ Tek veri kaynağı (tutarlı)

### Seçenek 2 Uygulandıktan Sonra:
- 🟡 Sadece SNMP değişiklikleri izleniyor
- 🟡 Alarmlar polling sonrası oluşuyor
- 🟡 Bildirimler gecikmeyle gidiyor
- ❌ Veri tutarsızlığı devam ediyor

## 🎯 Sonuç ve Tavsiye

**Önerilen**: **Seçenek 1** - Manuel değişiklikleri SNMP sistemine entegre et

**Sebep**:
1. Kalıcı ve kapsamlı çözüm
2. Kullanıcı deneyimi optimum
3. Veri tutarlılığı sağlanır
4. Gelecek geliştirmeler için temel oluşturur

**Hızlı Başlangıç**: Önce **Seçenek 2** ile test edin, ardından **Seçenek 1**'e geçin.

## 📝 Ek Notlar

### SNMP Worker Log Kontrol

Alarm oluşup oluşmadığını kontrol etmek için:

```bash
tail -f /home/runner/work/SW02/SW02/Switchp/snmp_worker/logs/snmp_worker.log | grep description
```

### Veritabanı Kontrol

Manuel test için:

```sql
-- Alarmları listele
SELECT * FROM alarms 
WHERE alarm_type = 'description_changed' 
ORDER BY first_occurrence DESC 
LIMIT 10;

-- Port değişiklik geçmişi
SELECT * FROM port_change_history 
WHERE change_type = 'DESCRIPTION_CHANGED' 
ORDER BY change_timestamp DESC 
LIMIT 10;
```

### Debugging

`port_change_detector.py` dosyasında (Line 495-546):
```python
def _detect_description_change(self, ...):
    # Buraya debug log ekleyin:
    self.logger.info(f"🔍 Description check: '{current_desc}' vs '{previous_desc}'")
```

---

**Oluşturulma Tarihi**: 15 Şubat 2026
**Sorun Önceliği**: ORTA
**Tahmini Çözüm Süresi**: 2-3 saat (Seçenek 1 için)
