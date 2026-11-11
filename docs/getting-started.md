# Kurulum

## Gereksinimler

- PHP **8.1+** (8.4 / 8.5 ile denenmiştir); eklentiler: `pdo_mysql`, `mbstring`, `fileinfo`, `gd` (görsel işleme, WebP), `zip` (modül yükleme)
- MySQL 5.7+ / MariaDB 10.3+
- Apache (`mod_rewrite` açık) — `.htaccess` hazırdır. Nginx için [Canlıya alma](deployment.md#nginx).
- Composer **gerekmez** (varsa `composer dump-autoload` ile otomatik yükleyici kullanılır, yoksa çekirdeğin kendi yükleyicisi).

## Kolay yol: kurulum sihirbazı

Dosyaları sunucuya yükleyip siteyi tarayıcıda açın: kurulmamış site kendiliğinden **`/install`** sihirbazına gider.

1. **Gereksinimler** denetlenir (PHP, eklentiler, yazılabilir klasörler).
2. **Veritabanı** bilgileri girilir; bağlantı denenir, veritabanı yoksa oluşturulur, `app/Config/database.local.php` yazılır.
3. **Site adı, yönetici hesabı, tema ve modüller** seçilir; tablolar kurulur, seçilen modül ve tema mağazadan indirilip
   kurulur (internet yoksa sadece çekirdek ve Default tema), panele giriş yapılmış olarak açılır.

Kurulum bitince `storage/installed.lock` oluşur ve `/install` kapanır (404). Aşağıdaki adımlar elle / komut satırıyla kurulum içindir.

## 1. Dosyalar

Projeyi web sunucusunun klasörüne koyun (ör. `C:\pampp\www\dev`) ve bir alan adı bağlayın (ör. `https://dev.test`).
Site alt klasörde de çalışır (`http://localhost/proje/`): adresler `Request::url()` ile kendiliğinden ayarlanır.

`storage/` klasörü yazılabilir olmalıdır (yüklemeler, loglar, geçici dosyalar).

## 2. Veritabanı

Boş bir veritabanı oluşturun (`utf8mb4_unicode_ci`). Bağlantı ayarları `app/Config/database.php` içindedir;
kendi bilgilerinizi git'e girmeyen yerel dosyaya yazın:

```php
<?php
// app/Config/database.local.php
return ['name' => 'modrix', 'user' => 'root', 'pass' => 'sifre'];
```

## 3. Tablolar ve admin hesabı

```bash
php modrix migrate
php modrix admin:create "Ad Soyad" admin@site.com guclu-bir-sifre
```

Modülleri ve temaları panelden kurun: **Sistem > Mağaza** (Sayfalar, Blog, Formlar... tek tıkla).
İndirdiğiniz bir modül ZIP'i için: `php modrix module:zip Blog-2.1.0.zip`.

## 4. Giriş

`https://siteniz/admin` → girişten sonra:

1. **Ayarlar > Genel:** site adı, slogan, tanıtım. **Logo ve simge**, **İletişim**, **SEO**.
2. **Görünüm > Temalar:** bir tema seçin, **Özelleştir** ile renkleri ve ana sayfa bölümlerini düzenleyin.
3. **Sistem > Mağaza:** ihtiyacınız olan modülleri (Sayfalar, Blog, Formlar...) ve temayı kurun.
   Sonra **Sayfalar**'dan Hakkımızda, İletişim (`[form iletisim]` yazarak form ekleyin)…
4. **Görünüm > Menüler:** bir menü oluşturup temanın "Üst menü" konumuna atayın.

## Geliştirme ipuçları

- `app/Config/app.php` → `'debug' => true` iken hatalar ayrıntılı sayfayla gösterilir; canlıda `false` yapın.
- Hata kayıtları: `storage/logs/`.
- Yeni bir şeye başlarken iskelet komutları: `php modrix make:module Ad`, `php modrix make:theme Ad`,
  `php modrix make:section ad --theme=Tema`. Hepsi: [Komut satırı](cli.md).
