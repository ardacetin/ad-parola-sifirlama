# Active Directory SSPR / Active Directory Self-Service Password Reset

## Türkçe

### Proje Hakkında
Bu proje, üniversite idari ve akademik personelinin Active Directory parolasını güvenli şekilde yenileyebilmesi için geliştirilmiş web tabanlı bir self-servis parola sıfırlama uygulamasıdır.

Sistem;
- PHP 8+
- Nginx
- MySQL
- Active Directory (LDAPS / 636)
- hCaptcha
- SENAGSM SMS API

ile çalışacak şekilde tasarlanmıştır.

### Temel Akış
1. Kullanıcı ilk ekranda kullanıcı adı, TC Kimlik / Sicil No ve cep telefonu son 4 hanesini girer.
2. hCaptcha doğrulaması yapılır.
3. Sistem Active Directory üzerinde kullanıcıyı `sAMAccountName` ile bulur.
4. AD içindeki `employeeID` ve `mobile` bilgileri, kullanıcının girdiği bilgilerle karşılaştırılır.
5. Bilgiler doğruysa, kullanıcıya SMS ile 6 haneli OTP gönderilir.
6. Kullanıcı OTP kodunu doğrular.
7. Kullanıcı yeni parolasını belirler.
8. Yeni parola Active Directory üzerinde `unicodePwd` alanı üzerinden güncellenir.

### Özellikler
- Türkçe ve İngilizce dil desteği
- Bootstrap 5 tabanlı 3 adımlı arayüz
- CSRF koruması
- PDO ile güvenli veritabanı erişimi
- SQL Injection ve temel XSS önlemleri
- hCaptcha doğrulaması
- LDAPS üzerinden güvenli AD bağlantısı
- Saatlik SMS gönderim limiti
- 3 dakikalık OTP süresi
- Parola karmaşıklık kontrolü
- İşlem loglama

### Dizin Yapısı
```text
config.php          Temel ayarlar
db.sql              MySQL tablo yapısı
index.php           Kullanıcı arayüzü
ajax_handler.php    AJAX isteklerini yöneten backend
DbHelper.php        Veritabanı işlemleri
LdapHelper.php      LDAP / Active Directory işlemleri
SmsHelper.php       SENAGSM SMS gönderim işlemleri
lang/tr.php         Türkçe metinler
lang/en.php         İngilizce metinler
```

### Kurulum
1. Web sunucuda PHP 8+ ve gerekli eklentilerin kurulu olduğundan emin olun.
2. MySQL üzerinde yeni bir veritabanı oluşturun.
3. `db.sql` dosyasını içeri aktarın.
4. `config.php` dosyasındaki aşağıdaki alanları kendi ortamınıza göre doldurun:
   - Veritabanı bilgileri
   - LDAP sunucu ve servis hesabı bilgileri
   - SENAGSM kullanıcı bilgileri
   - hCaptcha site key ve secret key
5. Nginx yapılandırmasında proje dizinini web kökü olarak tanımlayın.
6. Sunucunun Active Directory sunucusuna `636/TCP` üzerinden erişebildiğini doğrulayın.
7. SMS ve LDAP erişimlerini test edin.

### Gerekli PHP Eklentileri
- `pdo`
- `pdo_mysql`
- `ldap`
- `curl`
- `json`
- `mbstring`

### Veritabanı Tabloları
#### `otp_requests`
OTP kayıtlarını tutar.

Alanlar:
- `username`
- `tc_kimlik`
- `otp_code`
- `phone_number`
- `expires_at`
- `is_used`
- `created_at`

#### `reset_logs`
İşlem loglarını tutar.

Alanlar:
- `username`
- `ip_address`
- `status`
- `created_at`

### Dil Desteği
Sistem varsayılan olarak Türkçe açılır. Dil seçimi URL üzerinden yapılabilir:

- `?lang=tr`
- `?lang=en`

Seçilen dil session içinde saklanır.

### Güvenlik Notları
- LDAP bağlantısı sadece `ldaps://` üzerinden yapılmalıdır.
- `config.php` içindeki servis hesabı bilgileri korunmalıdır.
- Sunucuda HTTPS kullanılmalıdır.
- hCaptcha anahtarları gerçek ortam değerleri ile değiştirilmelidir.
- SMS API erişim bilgileri üçüncü kişilerle paylaşılmamalıdır.
- Log ve hata kayıtları düzenli kontrol edilmelidir.

### Önemli Not
Bu proje temel uygulama yapısını sağlar. Canlıya alınmadan önce aşağıdaki testlerin yapılması önerilir:
- LDAP kullanıcı doğrulama testi
- OTP üretim ve doğrulama testi
- SMS gönderim testi
- AD parola değiştirme testi
- HTTPS ve oturum güvenliği testi

---

## English

### About the Project
This project is a web-based self-service password reset solution designed for university administrative and academic staff to securely reset their Active Directory passwords.

The system is designed to work with:
- PHP 8+
- Nginx
- MySQL
- Active Directory (LDAPS / 636)
- hCaptcha
- SENAGSM SMS API

### Main Flow
1. The user enters username, National ID / Employee No, and the last 4 digits of the mobile phone.
2. hCaptcha verification is performed.
3. The system finds the user in Active Directory by `sAMAccountName`.
4. The values in `employeeID` and `mobile` are compared with the user input.
5. If the information matches, a 6-digit OTP is sent by SMS.
6. The user verifies the OTP code.
7. The user sets a new password.
8. The password is updated in Active Directory using the `unicodePwd` attribute.

### Features
- Turkish and English language support
- 3-step Bootstrap 5 interface
- CSRF protection
- Secure database access with PDO
- SQL Injection and basic XSS protection
- hCaptcha verification
- Secure AD connection over LDAPS
- Hourly SMS limit
- 3-minute OTP expiration
- Password complexity validation
- Action logging

### Project Structure
```text
config.php          Main configuration
db.sql              MySQL schema
index.php           User interface
ajax_handler.php    Backend router for AJAX requests
DbHelper.php        Database operations
LdapHelper.php      LDAP / Active Directory operations
SmsHelper.php       SENAGSM SMS operations
lang/tr.php         Turkish texts
lang/en.php         English texts
```

### Installation
1. Make sure PHP 8+ and required extensions are installed on the server.
2. Create a new MySQL database.
3. Import the `db.sql` file.
4. Update the following values in `config.php`:
   - Database settings
   - LDAP server and service account settings
   - SENAGSM credentials
   - hCaptcha site key and secret key
5. Configure Nginx to serve the project directory.
6. Verify that the server can reach the Active Directory server on `636/TCP`.
7. Test LDAP and SMS connectivity.

### Required PHP Extensions
- `pdo`
- `pdo_mysql`
- `ldap`
- `curl`
- `json`
- `mbstring`

### Database Tables
#### `otp_requests`
Stores OTP request records.

#### `reset_logs`
Stores action logs.

### Language Support
The system opens in Turkish by default. Language can be changed through the URL:

- `?lang=tr`
- `?lang=en`

The selected language is stored in session.

### Security Notes
- LDAP must be used only over `ldaps://`.
- Service account credentials in `config.php` must be protected.
- HTTPS should be enabled on the web server.
- Replace hCaptcha keys with real production values.
- SMS API credentials should be kept confidential.
- Logs and error records should be reviewed regularly.

### Important Note
This project provides the core application structure. Before production use, it is recommended to test:
- LDAP user verification
- OTP creation and validation
- SMS delivery
- Active Directory password update
- HTTPS and session security
