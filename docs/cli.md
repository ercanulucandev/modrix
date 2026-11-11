# Komut satırı (php modrix)

Proje kökünde `php modrix` tüm komutları listeler.

## Veritabanı

| Komut | Ne yapar |
|---|---|
| `php modrix migrate` | Çekirdeğin ve kurulu modüllerin bekleyen migration'larını çalıştırır |
| `php modrix migrate:status` | Hangi migration çalıştı, hangisi bekliyor |
| `php modrix make:migration create_orders_table` | Çekirdek için yeni migration: `app/Database/Migrations/{tarih}_create_orders_table.sql` |
| `php modrix make:migration create_orders_table Siparis` | Modül için: `app/Modules/Siparis/Migrations/...` (+ `.down.sql`) |

## Modüller

| Komut | Ne yapar |
|---|---|
| `php modrix make:module Siparis` | Yeni modül iskeleti: `app/Modules/Siparis/` (routes, controller, view, module.json) |
| `php modrix module:list` | Modüller, sürümleri ve durumları |
| `php modrix module:install Siparis` | Migration'ları çalıştırır ve aktif eder |
| `php modrix module:update Siparis` | Yeni sürümün migration'larını çalıştırır, sürümü günceller |
| `php modrix module:enable / module:disable Siparis` | Aktif / pasif (pasifte veriler durur) |
| `php modrix module:uninstall Siparis` | `.down.sql` dosyalarıyla **tabloları siler** ve kaydı kaldırır |
| `php modrix module:pack Siparis` | Satış/teslim için ZIP: `dist/Siparis-1.0.0.zip` |
| `php modrix module:zip dosya.zip` | ZIP'ten kurar ya da günceller (Admin > Modüller > Yükle ile aynı) |

## Temalar ve bölümler

| Komut | Ne yapar |
|---|---|
| `php modrix make:theme Ajans` | Çalışan bir tema iskeleti: `app/Themes/Ajans/` (layout, ana sayfa, örnek bölüm, ayarlar, ekran görüntüsü) |
| `php modrix make:section takvim --theme=Ajans` | Temaya bölüm: `app/Themes/Ajans/sections/takvim/` (kimlik `takvim`) |
| `php modrix make:section takvim --module=Siparis` | Modüle bölüm: `app/Modules/Siparis/Sections/takvim/` (kimlik `siparis.takvim`) |
| `php modrix make:section takvim` | Genel bölüm (her tema): `app/Sections/takvim/` (kimlik `core.takvim`) |

## Kullanıcı

| Komut | Ne yapar |
|---|---|
| `php modrix admin:create "Ad Soyad" e@posta.com sifre` | Yönetim paneli hesabı oluşturur |
| `php modrix 2fa:reset e@posta.com` | Kullanıcının iki adımlı doğrulamasını kapatır (telefon kaybolursa) |
| `php modrix media:sizes [--all]` | Görsellerin srcset kopyalarını üretir (eski yüklemeler için) |
| `php modrix cache:clear` | Sayfa önbelleğini temizler (her komuttan sonra zaten temizlenir) |
| `php modrix version` | Modrix sürümü |
| `php modrix test [süzgeç]` | Testleri çalıştırır (`tests/`); süzgeç: sınıf ya da metot adının bir parçası |
| `php modrix update:check` | Yeni sürüm var mı (GitHub Releases) |
| `php modrix update [--file=paket.zip]` | Günceller (önce tam yedek; hata olursa geri alır) |
| `php modrix release:pack` | Sürüm paketi: `dist/modrix-{sürüm}.zip` + `.sha256` |
| `php modrix store:build --base=https://.../` | Modül mağazası: tüm modüllerin ZIP'leri + `dist/store/catalog.json` |
| `php modrix backup [--db]` | Yedek alır (cron için; `--db`: sadece veritabanı) |
| `php modrix backup:restore dosya.zip` | Yedeği geri yükler (önce mevcut hali yedekler) |
