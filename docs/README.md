# Modrix belgeleri

Modrix üç parçadan oluşur: **çekirdek** (bu depo, başlangıç teması Başlangıç ile), **modüller**
([modrix-modules](https://github.com/ercanulucandev/modrix-modules)) ve **temalar** ([modrix-themes](https://github.com/ercanulucandev/modrix-themes)).
Modül ve temalar panelde **Sistem > Mağaza**'dan tek tıkla kurulur ve güncellenir.

## Başlarken

| | |
|---|---|
| [Kurulum](getting-started.md) | Sihirbazla ya da komut satırıyla kurulum, ilk ayarlar |
| [Canlıya alma](deployment.md) | Sunucu, HTTPS, izinler, yedek, kontrol listesi |
| [Komut satırı](cli.md) | `php modrix ...` komutları |

## Siteyi yönetmek

| | |
|---|---|
| [Mağaza](store.md) | Modül ve tema kurma/güncelleme, doğrulama, ücretli ürünler |
| [Güncellemeler](updates.md) | Tek tıkla güncelleme, yedek ve geri alma, sürüm yayınlama |
| [Çoklu dil](languages.md) | Diller, `/en` adresleri, sayfa/yazı/ayar/menü çevirileri, arayüz metinleri |
| [SEO](seo.md) | Site haritası, robots.txt, yönlendirmeler, 404 kaydı, yapılandırılmış veri |
| [Kullanıcılar ve roller](users.md) | Roller, yetkiler, davet |
| [Yedekler ve güvenlik](backups.md) | Yedek alma/geri yükleme, işlem kaydı, iki adımlı doğrulama |
| [Performans](performance.md) | Sayfa önbelleği, duyarlı görseller, içerik geçmişi, site içi arama |
| [E-posta](mail.md) | SMTP ayarları, şifre sıfırlama |

## Modül ve tema geliştirmek

| | |
|---|---|
| [Modül yazma: adım adım](module-tutorial.md) | Sıfırdan çalışan bir modül |
| [Modüller (başvuru)](modules.md) | Klasör, `module.json`, yaşam döngüsü, menü grupları, paketleme |
| [Tema yazma: adım adım](theme-tutorial.md) | Sıfırdan çalışan bir tema |
| [Temalar (başvuru)](themes.md) | `theme.json`, layout, Özelleştir alanları, menüler, ezme kuralı |
| [Bölümler](sections.md) | Ana sayfaya eklenen bölümler, alan türleri |
| [Koleksiyonlar](collections.md) | Hazır liste/form/sıralama altyapısı (SSS, ekip gibi modüller için) |
| [Rotalar ve controller'lar](routing.md) | Route, grup, middleware, istek/yanıt, oturum |
| [Veritabanı](database.md) | Sorgu oluşturucu, model, migration |
| [Görünümler ve arayüz](views.md) | View, layout, ortak sınıflar, panel JS kiti |
| [Ayarlar](settings.md) | `Setting`, alan türleri, modül ve tema ayarları |
| [Hook'lar](hooks.md) | Tüm hook noktaları |
| [Güvenlik](security.md) | Kaçırma, CSRF, HTML temizleme, yükleme, yetki |
| [Testler](testing.md) | `php modrix test`, test yazmak, GitHub Actions |

Modüllerin kendi kullanım rehberleri (Mağaza, Randevu...) [modrix-modules](https://github.com/ercanulucandev/modrix-modules) deposundadır.

## Klasör yapısı

Sitenin her şeyi `app/` altında; çatı `core/` altında. Kökte sadece başlangıç dosyaları durur.

```
app/
├── Config/        ayarlar: app.php (middleware, takma adlar), database.php, settings.php (Admin > Ayarlar alanları)
│                  *.local.php dosyaları yereldir, git'e girmez (veritabanı şifresi, test hesapları)
├── Routes/        web.php (site), admin.php (/admin), api.php (/api)
├── Database/
│   └── Migrations/  çekirdek tablolar (.sql); modüllerin tabloları kendi Migrations/ klasöründe
├── Controllers/   site ve yönetim paneli (Admin/) controller'ları
├── Models/  Services/  Resources/  Middlewares/
├── Views/         yönetim paneli görünümleri + temaların kullandığı ortak parçalar (partials/)
├── Sections/      her temada kullanılabilen genel bölümler (yazı bloğu, çağrı bandı...)
├── Assets/        yönetim panelinin JS/CSS'i ve ortak arayüz kiti (lib/) -> /assets/app/...
├── Modules/       kurulu modüller (mağazadan gelir; çekirdek deposunda yoktur)
├── Themes/        temalar: Baslangic (çekirdekle gelir) + mağazadan kurulanlar
└── Hooks.php      uygulama genelindeki hook kayıtları ve hook noktalarının listesi
core/              çatı: Router, View, Database, Theme, Section, Module... (güncellemeyle değişir, dokunmayın)
storage/           yüklenen dosyalar (uploads/), loglar (logs/), geçici dosyalar
docs/              bu rehberler
dist/              paketler: sürüm, modül ve tema paketleri (git'e girmez)
index.php  .htaccess  modrix (komut satırı)  composer.json
```

## Neyi nerede bulurum?

| Aradığım | Yer |
|---|---|
| Bir adres hangi controller'a gidiyor? | `app/Routes/*.php`, modüller için `app/Modules/{Ad}/routes.php` |
| Admin > Ayarlar'daki alanlar | `app/Config/settings.php` |
| Bir modülün ayarları | `app/Modules/{Ad}/settings.php` |
| Bir temanın renk/yazı ayarları | `app/Themes/{Tema}/settings.php` |
| Ana sayfadaki bir bölümün HTML'i | `app/Themes/{Tema}/sections/{ad}/view.php` (ya da `app/Sections/`, `app/Modules/{Ad}/Sections/`) |
| Sitenin üst/alt kısmı | `app/Themes/{Tema}/views/layouts/site.php` |
| Yönetim panelinin iskeleti | `app/Views/layouts/admin.php` |
| Tablolar | `app/Database/Migrations/`, `app/Modules/{Ad}/Migrations/` |
| Hata kayıtları | `storage/logs/` |

Dışarıdan erişim: `app/`, `core/`, `storage/`, `docs/`, `dist/` ve gizli dosyalar `.htaccess` ile kapalıdır.
Modül ve tema dosyaları (JS, CSS, görsel) sadece route üzerinden ve izinli türlerle sunulur:
`/assets/{modül}/...` ve `/theme/{tema}/...`.

## Temel kurallar (kısaca)

- **Kod yazma biçimi:** statik sınıflar (`Session::get()`, `View::render()`, `Response::redirect()`); yardımcı fonksiyon (`view()`, `config()`) yoktur.
  Sınıflar global takma adlarla her dosyada `use` yazmadan kullanılır (`app/Config/app.php` -> `aliases`).
- **Adresler İngilizce** (`/admin/pages`, `/blog`), **ekrandaki yazılar Türkçe**.
- **Her çıktı kaçırılır:** `<?= View::e($deger) ?>`. Ayrıntı: [Güvenlik](security.md).
- **Çekirdeğe (`core/`) dokunmayın;** bir şey eklemek için modül, tema ya da hook kullanın.
