# Yedekler, işlem kaydı, iki adımlı doğrulama

| | Nerede | Yetki |
|---|---|---|
| Yedek al / indir / sil | **Admin > Yedekler** | `backups.manage` |
| Kim ne yaptı | **Admin > Kullanıcılar > İşlem kaydı** | `activity.view` |
| İki adımlı doğrulamayı açma | **Profilim > Güvenlik** (herkes kendisi için) | — |
| Zorunlu 2FA, kayıt saklama süresi, yedek sayısı | Ayarlar > Güvenlik | `settings.manage` |

## Yedekler

Tek ZIP: `database.sql` (tüm tablolar), `uploads/` (yüklenen dosyalar), `config/key.local.php` (şifreleme anahtarı),
`backup.json` (tarih, tablo/dosya sayısı). Dosyalar `storage/backups/` altında, web'den açılamaz. "Sadece veritabanı"
seçeneği küçük ve hızlıdır. Ayarlar > Güvenlik'teki sayıdan eski yedekler kendiliğinden silinir.

**Otomatik yedek (cron):**

```
0 3 * * * php /site/yolu/modrix backup          # her gece tam yedek
0 * * * * php /site/yolu/modrix backup --db     # her saat veritabanı
```

Yedekleri sunucu dışına da alın (indirin ya da bir bulut klasörüne kopyalayın); sunucu giderse yedekler de gider.

**Geri yükleme** (veritabanının ve dosyaların üzerine yazar; önce mevcut hal kendiliğinden yedeklenir):

```
php modrix backup:restore storage/backups/yedek-2026-11-11-030000.zip
```

Elle: ZIP'i açın, `database.sql`'i phpMyAdmin/`mysql` ile içe aktarın, `uploads/` → `storage/uploads/`,
`config/key.local.php` → `app/Config/key.local.php`.

## İşlem kaydı

Giriş/çıkış, başarısız girişler, yazı/sayfa/medya/form değişiklikleri, ayarlar, tema, menü, modül, kullanıcı/rol, SEO ve
yedek işlemleri kişi, IP ve zamanla kaydedilir. Kişiye, işlem türüne göre süzülür; açıklamada ve IP'de aranır.
Modül kendi kaydını ekler:

```php
App\Services\Activity::log('order.shipped', "#{$order['id']} siparişini kargoya verdi", "admin/orders/{$order['id']}");
```

Eylem adının ilk kısmı (`order`) gruptur; yeni grubu listede göstermek için `Activity::GROUPS`'a ad verilir.

## İki adımlı doğrulama (2FA)

Profilim > Güvenlik'te QR kodu doğrulayıcı uygulamayla (Google/Microsoft Authenticator, 1Password...) okutulur, kod girilip açılır.
8 tek kullanımlık **kurtarma kodu** bir kez gösterilir. Girişte şifreden sonra 6 haneli kod istenir (5 dakika, 5 deneme).

- Anahtar veritabanında şifreli durur; anahtar `app/Config/key.local.php` (git'e girmez, ilk kullanımda oluşur).
  **Sunucu taşırken bu dosyayı da kopyalayın**; kaybolursa herkesin 2FA'sı sıfırlanmalıdır.
- Telefonunu kaybeden kullanıcı: Kullanıcılar > düzenle > "İki adımlı doğrulamayı sıfırla".
- Tek admin siz iseniz: `php modrix 2fa:reset email@site.com`
- Ayarlar > Güvenlik > "zorunlu" açıkken 2FA'sı olmayan kullanıcı panelde yalnızca Güvenlik sayfasına ulaşır.
