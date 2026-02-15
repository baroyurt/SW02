# Alarm Yönetim Sistemi Uygulama Kılavuzu

## Genel Bakış
Bu güncelleme, ağ port izleme için kapsamlı bir alarm yönetim sistemi uygular ve aşağıdaki temel özellikleri içerir:

### ✅ Uygulanan Özellikler

#### 1. Alarm Benzersizliği & Çoğaltmayı Önleme
- **Alarm Parmak İzi**: Her alarm artık benzersiz bir parmak izine sahip:
  - cihaz_adı (device_name)
  - port_numarası (port_number)
  - mac_adresi (mac_address)
  - kaynak_port (from_port)
  - hedef_port (to_port)
  - alarm_tipi (alarm_type)

- **Çoğaltmayı Önleme**: Aynı alarmlar artık birden fazla kez oluşturulmaz
- **Sayaç Takibi**: Yinelenen alarm denemeleri `occurrence_count` değerini artırır ve `last_occurrence` değerini günceller

#### 2. MAC+Port Beyaz Liste Sistemi
- **Veritabanı Tablosu**: `acknowledged_port_mac` kalıcı olarak beyaz listeye alınmış kombinasyonları saklar
- **Otomatik Engelleme**: Beyaz listeye alınmış MAC+Port kombinasyonları yeni alarm tetiklemez
- **Onaylama Davranışı**: Kullanıcı "Bilgi Dahilinde Kapat" butonuna tıkladığında:
  - Alarm durumu → ACKNOWLEDGED (ONAYLANDI)
  - MAC+Port kombinasyonu beyaz listeye eklenir
  - Aynı MAC+Port için gelecek alarmlar oluşturulmaz

#### 3. Geliştirilmiş Port Alarmları Arayüzü
- **Gömülü Bileşen**: Port alarmları artık ana panoya (index.php) entegre
- **Tutarlı Tasarım**: Mevcut index.php stil sistemiyle uyumlu
- **Gerçek Zamanlı Güncellemeler**: Her 30 saniyede bir otomatik yenilenir
- **Ana Özellikler**:
  - Alarm tipine göre filtreleme (Tümü, MAC Taşındı, VLAN Değişti, Açıklama)
  - Tekrarlanan alarmlar için oluşma sayacı gösterir
  - Ana görünümde cihaz/port'a gitmek için "Portu Görüntüle" butonu
  - İlk görülme ve son görülme zaman damgaları

#### 4. Toplu İşlemler
- **Çoklu Seçim**: Her alarm kartında onay kutusu
- **Toplu Onaylama**: Birden fazla alarm seçin ve hepsini aynı anda onaylayın
- **Otomatik Beyaz Listeye Alma**: Seçilen tüm MAC+Port kombinasyonları beyaz listeye eklenir

#### 5. Backend İyileştirmeleri (Python)
- **database_manager.py**:
  - Beyaz liste kontrolü ile güncellenmiş `get_or_create_alarm()`
  - Benzersizlik için parmak izi oluşturma eklendi
  - from_port/to_port parametreleri için destek eklendi

- **port_change_detector.py**:
  - Tüm gerekli parametreleri geçirmek için MAC hareket algılama güncellendi

#### 6. Backend İyileştirmeleri (PHP)
- **port_change_api.php**:
  - Çoklu alarm işlemleri için yeni `bulk_acknowledge` aksiyonu
  - Beyaz listeye eklemek için geliştirilmiş `acknowledgeAlarm()`
  - `addToWhitelist()` yardımcı fonksiyonu eklendi
  - ACTIVE durumunu kullanmak için alarm sorgusu güncellendi (büyük harf)
  - **Geriye Dönük Uyumluluk**: from_port ve to_port kolonları kontrolü eklendi

### 📦 Veritabanı Migrasyonu

**Dosya**: `Switchp/snmp_worker/migrations/add_acknowledged_port_mac_table.sql`

**Değişiklikler**:
- Yeni tablo: `acknowledged_port_mac` kolonlarıyla:
  - device_name, port_number, mac_address
  - acknowledged_by, acknowledged_at
  - note (isteğe bağlı kullanıcı yorumu)
  - (device_name, port_number, mac_address) üzerinde benzersiz kısıtlama

- `alarms` tablosuna eklenen kolonlar:
  - `from_port` - MAC hareketleri için kaynak port
  - `to_port` - MAC hareketleri için hedef port
  - `alarm_fingerprint` - çoğaltmayı önleme için benzersiz tanımlayıcı

**Migrasyonu Uygulamak İçin**:
```bash
cd Switchp
php apply_migration.php
```

Veya manuel olarak:
```bash
mysql -u root -p switchdb < snmp_worker/migrations/add_acknowledged_port_mac_table.sql
```

### 🎯 Nasıl Çalışır

#### Alarm Yaşam Döngüsü

1. **Algılama**: Port değişikliği algılandı (MAC taşındı, VLAN değişti, vb.)

2. **Beyaz Liste Kontrolü**: 
   - MAC+Port `acknowledged_port_mac` içindeyse → **ALARM OLUŞTURULMAZ**
   - Aksi takdirde, 3. adıma geç

3. **Benzersizlik Kontrolü**:
   - Cihaz, port, MAC, from_port, to_port'tan parmak izi oluştur
   - Aynı parmak izine sahip aktif alarm var mı kontrol et
   - EVET ise → `occurrence_count` artır ve `last_occurrence` güncelle
   - HAYIR ise → Yeni alarm oluştur

4. **Kullanıcı Aksiyonu**:
   - Kullanıcı "Bilgi Dahilinde Kapat" butonuna tıklar
   - Alarm durumu → ACKNOWLEDGED
   - MAC+Port → `acknowledged_port_mac` beyaz listesine eklenir
   - Bu kombinasyon için gelecek alarmlar → Engellenir

#### Örnek Senaryolar

**Senaryo 1: Aynı MAC aynı port'ta tekrar görünüyor**
- İlk sefer: Alarm oluşturuldu
- İkinci sefer: occurrence_count = 2, last_occurrence güncellendi
- Kullanıcı onayladı → Beyaz listeye eklendi
- Üçüncü sefer: ALARM YOK (beyaz listede)

**Senaryo 2: Aynı MAC farklı port'a taşındı**
- MAC Port 1'de → Alarm 1
- Kullanıcı Port 1'i onayladı → Beyaz listede
- MAC Port 2'ye taşındı → YENİ ALARM (farklı port)
- Kullanıcı Port 2'yi onayladı → O da beyaz listede

**Senaryo 3: Farklı MAC aynı port'ta**
- MAC-A Port 1'de → Alarm 1
- Kullanıcı onayladı → MAC-A + Port 1 beyaz listede
- MAC-B Port 1'de → YENİ ALARM (farklı MAC)

### 📁 Dosya Değişiklikleri

**Yeni Dosyalar**:
- `Switchp/port_alarms_component.php` - Gömülü alarm arayüz bileşeni
- `Switchp/snmp_worker/migrations/add_acknowledged_port_mac_table.sql` - Veritabanı migrasyonu
- `Switchp/apply_migration.php` - Migrasyon uygulama scripti

**Değiştirilen Dosyalar**:
- `Switchp/index.php` - Alarm bileşeni dahil edildi
- `Switchp/port_change_api.php` - Beyaz liste yönetimi, toplu işlemler, geriye dönük uyumluluk
- `Switchp/snmp_worker/core/database_manager.py` - Alarm benzersizlik mantığı
- `Switchp/snmp_worker/core/port_change_detector.py` - Ek alarm parametrelerini geçir

### 🔧 Yapılandırma

Ek yapılandırma gerekmez. Sistem mevcut veritabanı bağlantı ayarlarını kullanır.

### 🚀 Dağıtım Adımları

1. **Veritabanı Migrasyonunu Uygula**:
   ```bash
   cd Switchp
   php apply_migration.php
   ```

2. **SNMP Worker'ı Yeniden Başlat** (çalışıyorsa):
   ```bash
   cd Switchp/snmp_worker
   python main.py
   ```

3. **Panele Eriş**:
   - `index.php` sayfasına git
   - Port Alarmları bölümü ana panoda görünür
   - Alarmlar her 30 saniyede bir otomatik yenilenir

### 📊 Test Kontrol Listesi

- [ ] Veritabanı migrasyonu başarıyla uygulandı
- [ ] Alarmlar gömülü bileşende görünür
- [ ] Onayla butonu çalışıyor ve beyaz listeye ekliyor
- [ ] Toplu onaylama birden fazla alarm için çalışıyor
- [ ] Beyaz listeye alınmış MAC+Port kombinasyonları yeni alarm oluşturmuyor
- [ ] Yinelenen alarmlar için oluşma sayacı artıyor
- [ ] Alarm kartından cihaz/port'a gitme çalışıyor
- [ ] Gerçek zamanlı yenileme alarm listesini güncelliyor

### 🔍 Kalan Görevler

Gereksinimlerdeki aşağıdaki özellikler henüz uygulanmadı ancak eklenebilir:

1. **Fiber Port Desteği**: Fiber portlar için MAC bilgisi olmadan FDB/LLDP/ARP yedek mantığı
2. **URL Parametreleri**: Doğrudan navigasyon için ?device=XXX&port=YY desteği
3. **Kaydır/Vurgula**: Port'a giderken kaydırma ve vurgulama animasyonu iyileştirmeleri
4. **Alarmı Sessize Al**: Geçici sessize alma özelliği (UI var ama backend tamamlanması gerekiyor)
5. **Onay Modalı**: Ayrıntılı onay mesajı ile geliştirilmiş modal

### 📝 Notlar

- Tüm durum değerleri artık AlarmStatus enum ile tutarlılık için büyük harf (ACTIVE, ACKNOWLEDGED, RESOLVED)
- Beyaz liste kontrolü uyumluluk için ham SQL kullanır (daha sonra SQLAlchemy modeline dönüştürülebilir)
- Bileşen kolay bakım için PHP include kullanılarak gömülür
- Otomatik yenileme port_alarms_component.php içinde interval değiştirilerek devre dışı bırakılabilir

### 🐛 Bilinen Sorunlar

- Geliştirme ortamında veritabanı çalışmıyor olabilir - migrasyon dağıtımda uygulanmalı
- Bazı Python debug print ifadeleri kalıyor (üretimde kaldırılabilir)
- `from_port` ve `to_port` kolonları opsiyonel - migrasyon uygulanmadan geriye dönük uyumlu
