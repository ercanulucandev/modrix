# Temalar (başvuru)

> Sıfırdan bir tema yazmak için adım adım rehber: [Tema yazma: adım adım](theme-tutorial.md). İskelet: `php modrix make:theme Ad`.

Tema sitenin **görünümüdür**: layout, sayfa şablonları, renkler, yazı tipleri ve Özelleştir alanları.
İçerik (yazılar, ayarlar, menüler) temadan bağımsızdır; tema değişince kaybolmaz.

Yönetim: **Görünüm** menüsü → Temalar (önizle, etkinleştir), Özelleştir (canlı önizlemeli), Menüler.
Çekirdekle sadece **Başlangıç** (`app/Themes/Baslangic`) gelir (diğer temaların eksik dosyaları da ondan alınır); başka temalar **Sistem > Mağaza > Temalar**'dan
kurulur ([modrix-themes](https://github.com/ercanulucandev/modrix-themes)).

## 1. Klasör yapısı

```
app/Themes/Redmo/
├── theme.json            ZORUNLU: ad, sürüm, menü konumları
├── screenshot.svg        Temalar sayfasındaki görsel (svg/png/webp/jpg, 4:3 önerilir, ör. 1200×900)
├── settings.php          Özelleştir alanları (bölüm bölüm)
├── functions.php         temanın hook'ları (isteğe bağlı; sadece etkin temada, sadece sitede yüklenir)
├── views/                app/Views ile AYNI yollar
│   ├── layouts/site.php  sitenin iskeleti (head, üst kısım, $content, alt kısım)
│   ├── home.php          ana sayfa
│   ├── errors/404.php    (isteğe bağlı)
│   └── partials/...      kendi parçaların ya da ezdiğin ortak parçalar (pagination.php vb.)
├── modules/              modül görünümlerini ezer
│   └── Blog/index.php    Blog::index yerine kullanılır (app/Modules/Blog/Views/index.php)
└── assets/               css, js, görseller, fontlar  ->  /theme/redmo/...
```

**Klasör adı** büyük harfle başlar, sadece harf/rakam (`Redmo`, `Magaza`). Adreslerde küçük harfle geçer.

### Ezme (override) kuralı

Sitede bir görünüm şu sırayla aranır:

1. Etkin tema: `app/Themes/{Tema}/views/home.php` (modüller için `app/Themes/{Tema}/modules/Blog/index.php`)
2. Başlangıç teması: `app/Themes/Baslangic/...`
3. Çekirdek ya da modül: `app/Views/...`, `app/Modules/Blog/Views/...`

Yani bir tema **sadece değiştirmek istediği dosyaları** içerir. Redmo teması `partials/nav.php` içermez,
menüyü Başlangıç temasının dosyasıyla çizer. Başlangıç teması silinemez (diğerlerinin yedeğidir).

Etkin olmayan temalar **Görünüm > Temalar**'dan silinebilir; temanın ayarları ve menü atamaları da silinir.

Yönetim paneli (`/admin`) temadan **hiç** etkilenmez.

## 2. theme.json

```json
{
    "name": "Redmo",
    "version": "1.0.0",
    "description": "Şirketler için...",
    "author": "Modrix",
    "requires": { "php": "8.1" },
    "menus": {
        "header": "Üst menü",
        "footer": "Alt menü"
    }
}
```

`menus`: temanın menü gösterdiği **konumlar**. Görünüm > Menüler'de her konuma bir menü atanır.
İlk konuma menü atanmamışsa modüllerin site menüsü (`module.json` → `menu.site`) gösterilir.

## 3. Layout (views/layouts/site.php)

En az şu üç parçayı içermeli:

```php
<!doctype html>
<html lang="tr">
<head>
<?= Theme::head($title ?? null, $metaDescription ?? null, $metaImage ?? null) ?>   <!-- başlık, SEO, favicon, analytics, özel kod, modül eklentileri -->
  ... temanın CSS'i ...
</head>
<body>
  ... üst kısım, menü ...
  <?= View::render('partials/flash') ?>
  <?= $content ?>
  ... alt kısım ...
<?= Theme::footer() ?>                                          <!-- özel kod, modül eklentileri (WhatsApp düğmesi), önizleme çubuğu -->
</body>
</html>
```

`Theme::head()` / `Theme::footer()` olmadan modüllerin eklentileri (ör. WhatsApp düğmesi) ve
Ayarlar > SEO / Özel kod çalışmaz.

### Tailwind ve ortak sınıflar

Hazır temalar Tailwind (tarayıcı CDN) kullanır. Renkleri `@theme` ile verip ortak sınıfları
(`wrap`, `btn`, `btn-primary`, `input`, `card`, `alert-*`, `prose-content`...) ekleyin:

```php
<script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4.1.13"></script>
<style type="text/tailwindcss">
  @theme { --color-primary: <?= Theme::color('primary_color', '#1d4ed8') ?>; ... }
</style>
<?= View::render('partials/ui-styles') ?>
```

Modül görünümleri bu sınıfları kullandığı için temada tanımlı olmaları gerekir.
Tailwind istemeyen tema kendi CSS'ini `assets/` içine koyup `Theme::asset('css/style.css')` ile ekler
ve `partials/ui-styles.php`'yi kendi sınıflarıyla ezer.

## 4. Özelleştir alanları (settings.php)

```php
<?php
return [
    'hero' => [
        'label'       => 'Giriş bölümü',
        'description' => 'Ana sayfanın üstü.',
        'fields'      => [
            'hero_title' => ['label' => 'Başlık', 'type' => 'text', 'rules' => 'nullable|max:120', 'default' => 'Merhaba'],
            'hero_image' => ['label' => 'Görsel', 'type' => 'image'],
            'show_hero'  => ['label' => 'Göster', 'type' => 'checkbox', 'default' => '1'],
        ],
    ],
];
```

- Alan türleri ve kurallar `app/Config/settings.php` ile aynı: text, textarea, email, url, tel, number, color, checkbox, select, image, code.
- Değerler temaya özel saklanır (`theme_kurumsal_hero_title`): tema değişince eski temanın ayarları kaybolmaz.
- Okuma (sadece tema dosyalarında):

| | |
|---|---|
| `Theme::get('hero_title')` | değer; kayıt yoksa `default` |
| `Theme::bool('show_hero')` | checkbox |
| `Theme::url('hero_image')` | görselin adresi ya da null |
| `Theme::color('primary_color', '#000000')` | geçerli renk (CSS'e güvenle yazılır) |
| `Theme::contrast('#1d4ed8')` | üstündeki yazı rengi (açık/koyu) |

Sitenin genel bilgileri tema ayarı değildir, her temada aynıdır:
`Setting::get('site_name')`, `Setting::url('logo')`, `Setting::get('contact_phone')`, sosyal medya bağlantıları vb.

Bağlantı alanlarını (düğme adresi) yazdırmadan önce doğrulayın:
`Menu::validUrl($url)` ve `Menu::href($url)` (site içi yolları alt klasörde de doğru adrese çevirir).

## 4.1 Bölümler (sections)

Ana sayfanın içeriği tema ayarı değil, sürükle-bırak **bölümlerdir**. Tema kendi bölümlerini `sections/` altında yazar,
varsayılan düzeni `theme.json` → `"sections"` ile verir ve `views/home.php` içinde `Section::render('home', ...)` ile yazdırır.
Tüm ayrıntılar: [Bölümler](sections.md).

## 5. Menüler

```php
<?php foreach (Menu::location('header') as $item): ?>
  <a href="<?= View::e($item['href']) ?>" <?= $item['new_tab'] ? 'target="_blank" rel="noopener"' : '' ?>
     class="<?= $item['active'] ? 'font-medium' : '' ?>"><?= View::e($item['label']) ?></a>
  <?php // $item['children'] aynı yapıda (en fazla 3 seviye) ?>
<?php endforeach ?>
```

Alt menülü hazır çizim: `View::render('partials/nav', ['items' => Menu::location('header')])` (Başlangıç temasından).

Modüller "Hazır bağlantılar" listesine öğe ekleyebilir (sadece kalıcı sayfalar; yazı gibi çok sayıda içerik eklemeyin):

```php
Hook::on('menu.links', fn(array $l) => [...$l, ['group' => 'Ürünler', 'label' => 'Vitrin', 'url' => 'urunler']]);
```

## 6. Dosyalar (assets)

`app/Themes/Redmo/assets/css/style.css` → `<link rel="stylesheet" href="<?= Theme::asset('css/style.css') ?>">`

İzinli türler: css, js, json, png, jpg, jpeg, gif, webp, svg, ico, woff, woff2, ttf. Temanın PHP dosyalarına
ve `assets/` dışına dışarıdan ulaşılamaz. Etkin temada olmayan dosya Başlangıç temasından verilir.

## 7. functions.php

Etkin temanın hook'ları; modüllerden sonra, sadece sitede yüklenir:

```php
<?php
// Ana sayfaya temaya özel bölüm
Hook::on('site.home', fn(array $s) => [...$s, ['order' => 5, 'html' => (string) View::render('partials/banner')]]);
```

## 8. Canlı düzenleme (Özelleştir)

Görünüm > Özelleştir tam ekran açılır: solda panel (Site kimliği + temanın `settings.php` bölümleri), sağda site.
Bir alan değişince değerler **taslak** olarak oturuma yazılır ve önizleme yenilenir (kaydırma ve gezilen sayfa korunur).
Taslak sadece o önizlemede görünür; ziyaretçiler **Yayınla**'ya kadar eski hâli görür. Etkin olmayan bir temada
düğme **Etkinleştir ve yayınla** olur.

Tema için ek iş yok: `Theme::get()` / `Setting::get()` önizlemede taslak değerleri kendiliğinden döner.

### Tıklayarak düzenleme

Önizlemede bölümlerin üzerine gelince "… · düzenle" düğmesi çıkar; işaretli bir yazıya/görsele tıklayınca
paneldeki alan açılır. Tema bunu işaretlerle sağlar (ziyaretçiye hiçbir şey yazılmaz):

```php
<section class="..." <?= Theme::editable('hero') ?>>                    <!-- bölüm: settings.php'deki anahtar -->
  <h1 <?= Theme::editable('hero', 'hero_title') ?>>...</h1>              <!-- alan: settings.php'deki alan adı -->
  <img <?= Theme::editable('identity', 'logo') ?> ...>                    <!-- Site kimliği: site_name, site_tagline, logo, favicon -->
```

## 9. Sayfa şablonları (Sayfalar modülü)

Sayfalar modülünün iki şablonu vardır: `default` (başlık + içerik + alt sayfalar) ve `full` (başlıksız, geniş).
Tema kendi şablonlarını ekleyebilir; düzenleyicideki "Şablon" listesine eklenir:

```json
"page_templates": { "landing": "Açılış sayfası" }
```

Dosya: `app/Themes/{Tema}/modules/Pages/templates/landing.php`. Varsayılanları ezmek için `modules/Pages/show.php`.
Değişkenler: `$page` (title, body, image, meta_description, path...), `$ancestors`, `$children`, `$isDraft`, `$isFront`.

Layout `$metaImage` değişkenini `Theme::head()`'e üçüncü parametre olarak verir (sayfanın öne çıkan görseli paylaşımda görünür).

## 10. Önizleme (sitede gezerek)

Görünüm > Temalar'da **Sitede gez** ya da **Özelleştir ve önizle**: tema sadece o admin için açılır,
ziyaretçiler etkin temayı görmeye devam eder. Önizlemede sayfanın altında çubuk çıkar (Etkinleştir / Kapat)
ve sayfa arama motorlarına kapatılır.

## Kontrol listesi

- [ ] `theme.json` geçerli, sürüm `1.0.0` biçiminde, menü konumları tanımlı
- [ ] `views/layouts/site.php` içinde `Theme::head()`, `$content`, `Theme::footer()` var
- [ ] Ortak sınıflar (`partials/ui-styles`) eklenmiş ya da tema kendi karşılıklarını tanımlıyor
- [ ] Kullanıcı verisi her yerde `View::e()` ile yazdırılıyor; renkler `Theme::color()` ile
- [ ] Özelleştir alanlarının hepsinin makul bir `default`'u var (kurulur kurulmaz düzgün görünsün)
- [ ] Menü konumu boşken de düzgün görünüyor
- [ ] Mobilde menü açılıyor, taşma yok
- [ ] `screenshot` eklendi
