# SNMP Admin Panel - Bildirim Ayarları Kılavuzu

## Telegram Bildirimleri

### Telegram Bot Oluşturma

1. **Telegram'da @BotFather'ı bulun**
   - Telegram'ı açın
   - Arama kısmına `@BotFather` yazın
   - Resmi BotFather'ı seçin (mavi onay işareti olmalı)

2. **Yeni Bot Oluşturun**
   ```
   /newbot komutunu gönderin
   Bot için bir isim girin (örn: SNMP Alarm Bot)
   Bot için kullanıcı adı girin (örn: mysnmpalarm_bot)
   ```

3. **Bot Token'ı Kopyalayın**
   - BotFather size bir token verecek (örn: `123456789:ABCdefGHIjklMNOpqrsTUVwxyz`)
   - Bu token'ı saklayın

4. **Chat ID'nizi Bulun**
   
   **Yöntem 1 - @userinfobot kullanarak:**
   - Telegram'da `@userinfobot` botunu bulun
   - `/start` komutunu gönderin
   - Bot size Chat ID'nizi verecek (örn: `123456789`)
   
   **Yöntem 2 - Grup için:**
   - Botunuzu gruba ekleyin
   - Gruba bir mesaj gönderin
   - Bu URL'yi tarayıcıda açın: `https://api.telegram.org/bot<TOKEN>/getUpdates`
   - `<TOKEN>` yerine bot token'ınızı yazın
   - JSON çıktısında `"chat":{"id":-123456789}` gibi bir değer bulun
   - Grup chat ID'leri negatif sayı olur

### SNMP Admin'de Yapılandırma

1. SNMP Admin Panel'i açın
2. **Telegram** sekmesine gidin
3. **Bot Token**: Kopyaladığınız token'ı yapıştırın
4. **Chat ID**: Bulduğunuz chat ID'yi yazın
5. **"Aktif"** kutusunu işaretleyin
6. **"Test Et"** butonuna tıklayın
7. Telegram'ınızı kontrol edin - test mesajı gelmeli

### Sık Karşılaşılan Hatalar

#### "Bad Request: chat not found"
**Sebep**: Chat ID yanlış veya bot henüz sizinle konuşmamış

**Çözüm**:
1. Botunuzu Telegram'da bulun (kullanıcı adı ile arayın)
2. `/start` komutunu gönderin
3. Chat ID'nizi tekrar kontrol edin (@userinfobot kullanarak)
4. Tekrar test edin

#### "Unauthorized"
**Sebep**: Bot token yanlış

**Çözüm**:
1. BotFather'dan token'ı tekrar kopyalayın
2. Boşluk veya fazladan karakter olmadığından emin olun

## Email Bildirimleri

### Yandex Mail Yapılandırması

Yandex Mail SMTP kullanmak için:

1. **SMTP Ayarları**:
   - **SMTP Host**: `smtp.yandex.com`
   - **SMTP Port**: `587` (TLS için) veya `465` (SSL için)
   - **SMTP User**: Tam email adresiniz (örn: `kullanici@yandex.com`)
   - **From Address**: Gönderici adresi (aynı email)

2. **Uygulama Şifresi Oluşturma** (Önerilen):
   
   Yandex hesap güvenliği için uygulama şifresi kullanın:
   
   a. Yandex hesabınıza giriş yapın
   b. https://passport.yandex.com/profile adresine gidin
   c. **Security** > **App passwords** bölümüne tıklayın
   d. Yeni uygulama şifresi oluşturun
   e. Oluşturulan şifreyi **SMTP Password** alanına yapıştırın

3. **Alıcı Email Adresleri**:
   - **"+"** butonuna tıklayarak birden fazla alıcı ekleyebilirsiniz
   - Her email adresi geçerli olmalı

4. **Test Et**:
   - Ayarları kaydettikten sonra test edin
   - Gelen kutunuzu kontrol edin

### Gmail Yapılandırması

Gmail kullanmak için:

1. **SMTP Ayarları**:
   - **SMTP Host**: `smtp.gmail.com`
   - **SMTP Port**: `587`
   - **SMTP User**: Gmail adresiniz
   - **From Address**: Aynı Gmail adresi

2. **Uygulama Şifresi**:
   Gmail'de 2FA (iki faktörlü doğrulama) aktif olmalı:
   
   a. Google Hesabı > Güvenlik
   b. "Uygulama şifreleri" bölümüne gidin
   c. Yeni uygulama şifresi oluşturun
   d. Şifreyi kopyalayın (boşluksuz)

### Office 365 / Outlook

1. **SMTP Ayarları**:
   - **SMTP Host**: `smtp.office365.com`
   - **SMTP Port**: `587`
   - **SMTP User**: Outlook email adresiniz
   - **From Address**: Aynı email

### Sık Karşılaşılan Email Hataları

#### "SMTP Authentication Failed"
**Sebep**: Yanlış kullanıcı adı veya şifre

**Çözüm**:
1. SMTP User'ın tam email adresi olduğundan emin olun
2. Uygulama şifresi kullandığınızdan emin olun (normal şifre değil)
3. Hesap güvenlik ayarlarını kontrol edin

#### "Connection Timeout"
**Sebep**: Yanlış host veya port

**Çözüm**:
1. SMTP Host'u kontrol edin (smtp. prefix olmalı)
2. Port numarasını kontrol edin (genellikle 587)
3. Firewall kurallarını kontrol edin

#### "TLS/SSL Error"
**Sebep**: Port ve şifreleme uyumsuzluğu

**Çözüm**:
- Port 587 kullanıyorsanız: STARTTLS kullanılmalı
- Port 465 kullanıyorsanız: SSL kullanılmalı

## Alarm Severity (Önem Seviyesi) Ayarları

SNMP Admin Panel'de her alarm tipi için:

1. **Severity (Önem) Seviyesi**:
   - CRITICAL: En yüksek öncelik
   - HIGH: Yüksek öncelik
   - MEDIUM: Orta öncelik
   - LOW: Düşük öncelik

2. **Bildirim Kanalları**:
   - **Telegram**: Bu alarm tipi için Telegram bildirimi gönderilsin mi?
   - **Email**: Bu alarm tipi için Email bildirimi gönderilsin mi?

### Önerilen Yapılandırma

| Alarm Tipi | Severity | Telegram | Email |
|------------|----------|----------|-------|
| device_unreachable | CRITICAL | ✓ | ✓ |
| multiple_ports_down | CRITICAL | ✓ | ✓ |
| mac_moved | HIGH | ✓ | ✓ |
| port_down | HIGH | ✓ | ✓ |
| vlan_changed | MEDIUM | - | ✓ |
| port_up | MEDIUM | - | ✓ |
| description_changed | LOW | - | - |
| mac_added | MEDIUM | - | ✓ |
| snmp_error | HIGH | ✓ | ✓ |

### Notification Spam'i Önleme

Çok fazla bildirim almamak için:
- CRITICAL ve HIGH alarmları için hem Telegram hem Email kullanın
- MEDIUM alarmlar için sadece Email kullanın
- LOW alarmlar için bildirimleri kapatın
- Port up/down gibi geçici durumlar için Telegram kapatılabilir

## Sorun Giderme

### Bildirimlerin Gelmediği Durumlar

1. **Ayarların Kaydedildiğinden Emin Olun**
   - Formu gönderdikten sonra "başarılı" mesajı geldi mi?
   - Sayfayı yenileyip ayarları kontrol edin

2. **SNMP Worker Servisini Kontrol Edin**
   - Servis çalışıyor mu?
   - Log dosyalarında hata var mı?

3. **Test Fonksiyonlarını Kullanın**
   - Telegram için "Test Et" butonu
   - Email ayarlarını kaydedin ve gerçek bir alarm bekleyin

4. **Network Bağlantısını Kontrol Edin**
   - Sunucunun internete erişimi var mı?
   - Firewall SMTP ve Telegram API'yi engelliyor mu?

## Destek

Sorun yaşamaya devam ederseniz:
1. SNMP Worker log dosyalarını kontrol edin
2. Browser console'da JavaScript hataları olup olmadığına bakın
3. PHP error log'larını inceleyin
