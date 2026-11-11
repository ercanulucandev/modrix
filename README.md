# Modrix

**Framework'süz, modüler PHP içerik yönetim sistemi.** Sade bir çekirdek, tek tıkla kurulan modüller ve temalar,
herkesin kullanabileceği Türkçe bir yönetim paneli.

```
modrix          çekirdek + başlangıç teması (bu depo)
modrix-store    modüller: blog, sayfalar, formlar, mağaza, randevu, SSS, bülten...   → panelden tek tıkla
modrix-themes   temalar: Kurumsal, Lacivert...                                         → panelden tek tıkla
```

## Neler var?

**Çekirdek**
- Yönetim paneli: sade menü, genel bakış (grafikler, dikkat isteyenler), gece modu
- Medya kütüphanesi (WebP, görsel düzenleyici, duyarlı görseller), menüler, canlı tema özelleştirme, sürükle-bırak bölümler
- Kullanıcılar, roller ve yetkiler, iki adımlı doğrulama, işlem kaydı
- Çoklu dil (`/en/...`), SEO (site haritası, yönlendirmeler, yapılandırılmış veri), sayfa önbelleği, site içi arama
- Yedekler, **tek tıkla güncelleme** (önce yedek, hata olursa otomatik geri alma), **mağaza** (modül ve tema)
- Kurulum sihirbazı, komut satırı (`php modrix`), testler

**Mağazadaki modüller** ([modrix-store](https://github.com/ercanulucandev/modrix-store))
Sayfalar · Blog · Formlar · Mağaza (iyzico, PayTR, havale, kapıda ödeme) · Randevu · SSS · Ekip · Referanslar ·
Portfolyo · Bülten · Çerez onayı (KVKK) · Canlı destek · WhatsApp

**Temalar** ([modrix-themes](https://github.com/ercanulucandev/modrix-themes)) · Çekirdekte sade bir başlangıç teması (Default) gelir.

## Kurulum

Gereken: PHP 8.1+, MySQL 5.7+ / MariaDB 10.3+, Apache (`mod_rewrite`).

1. [Son sürümü](https://github.com/ercanulucandev/modrix/releases/latest) indirip sunucuya yükleyin.
2. Siteyi tarayıcıda açın: **kurulum sihirbazı** gereksinimleri denetler, veritabanını ve yönetici hesabını kurar,
   istediğiniz modülleri ve temayı mağazadan indirip kurar.

Ayrıntı: [Kurulum](docs/getting-started.md) · Canlıya alma: [docs/deployment.md](docs/deployment.md)

## Belgeler

[docs/](docs/README.md): kullanım, modül ve tema geliştirme, hook'lar, güncellemeler, mağaza.

## Geliştirme

```bash
php modrix test          # testler
php modrix               # tüm komutlar
```

Yeni sürüm: `core/App.php` → `VERSION`, `CHANGELOG.md`, sonra `git tag vX.Y.Z && git push origin vX.Y.Z`
(GitHub Actions paketi hazırlayıp yayınlar). Ayrıntı: [Güncellemeler](docs/updates.md).
