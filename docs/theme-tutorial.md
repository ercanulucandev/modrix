# Tema yazma: adım adım

Bu rehberde **Ajans** adında yeni bir tema yazıyoruz: kendi üst/alt kısmı, renk ayarı, ana sayfa bölümleri, kendine özel
bir bölüm, bir modül sayfasının tema tasarımı ve kendi CSS dosyası. Başvuru bilgileri: [Temalar](themes.md), [Bölümler](sections.md).

## 1. İskelet

```bash
php modrix make:theme Ajans
```

Hemen çalışan bir tema oluşur:

```
app/Themes/Ajans/
├── theme.json                 ad, sürüm, menü konumları, varsayılan ana sayfa düzeni
├── screenshot.svg             Görünüm > Temalar'daki görsel (sonra kendi ekran görüntünüzle değiştirin)
├── settings.php               Özelleştir alanları (renk, alt kısım)
├── views/
│   ├── layouts/site.php       sitenin iskeleti: head, üst kısım, içerik, alt kısım
│   └── home.php               ana sayfa: bölümleri yazdırır
├── sections/hero/             örnek bölüm (schema.php + view.php)
└── assets/                    CSS, JS, görseller
```

**Görünüm > Temalar**'da **Ajans** kartı çıkar. **Özelleştir ve önizle** ile siteyi yayına almadan görebilirsiniz.

## 2. theme.json

**`app/Themes/Ajans/theme.json`**
```json
{
    "name": "Ajans",
    "version": "1.0.0",
    "description": "Ajanslar ve serbest çalışanlar için sade, cesur bir tema.",
    "author": "Sizin adınız",
    "requires": { "php": "8.1" },
    "menus": {
        "header": "Üst menü",
        "footer": "Alt menü"
    },
    "page_templates": {
        "landing": "Açılış sayfası"
    },
    "sections": {
        "home": [{ "type": "hero" }, { "type": "isler" }, { "type": "core.cta" }]
    }
}
```

- `menus`: temanın menü gösterdiği yerler (Görünüm > Menüler'de bunlara menü atanır).
- `page_templates`: Sayfalar modülünün "Şablon" listesine eklenecek şablonlar (adım 7).
- `sections.home`: ana sayfanın **varsayılan** düzeni; kullanıcı Özelleştir'de değiştirip yayınlayınca onunki kullanılır.
  `hero` ve `isler` bu temanın bölümleri, `core.cta` her temadaki genel "Çağrı bandı".

## 3. Layout: sitenin iskeleti

İskeletin ürettiği `views/layouts/site.php` zaten çalışır. Olmazsa olmaz üç parça:

```php
<head>
<?= Theme::head($title ?? null, $metaDescription ?? null, $metaImage ?? null) ?>   // başlık, SEO, favicon, analytics, modül eklentileri
  ...
</head>
<body>
  ...
  <?= $content ?>                                                                 // sayfanın içeriği
  ...
<?= Theme::footer() ?>                                                            // özel kod, WhatsApp düğmesi gibi eklentiler, önizleme
</body>
```

Renkler `@theme` ile verilir; `partials/ui-styles` ortak sınıfları (`wrap`, `btn`, `card`, `input`, `prose-content`...) ekler.
Modüllerin görünümleri bu sınıfları kullandığı için temada olmaları gerekir. Ana sayfada `<main>` kap çizmez, çünkü
her bölüm kendi `<section><div class="wrap">` yapısını kurar:

```php
<main class="flex-1 <?= $isHome ? '' : 'wrap py-12' ?>">
```

**Menü:** `Menu::location('header')` öğeleri verir (`label`, `href`, `active`, `new_tab`, `children`). Alt menülü hazır
çizim: `View::render('partials/nav', ['items' => Menu::location('header')])` (Başlangıç temasından gelir). Kendi tasarımınız
için `views/partials/nav.php` yazın.

**Tıklayarak düzenleme:** önizlemede tıklanınca paneli açmak için öğelere işaret koyun (ziyaretçiye hiçbir şey yazılmaz):

```php
<header <?= Theme::editable('header') ?>>                          <!-- settings.php'deki bölüm -->
<p <?= Theme::editable('footer', 'footer_about') ?>>...</p>        <!-- bölüm + alan -->
<a <?= Theme::editable('identity', 'logo') ?>>...</a>              <!-- Site kimliği: site_name, site_tagline, logo, favicon -->
```

## 4. Özelleştir alanları

**`app/Themes/Ajans/settings.php`**
```php
<?php
return [
    'colors' => [
        'label'  => 'Renk ve yazı',
        'fields' => [
            'primary_color' => ['label' => 'Ana renk', 'type' => 'color', 'rules' => 'required|regex:/^#[0-9a-fA-F]{6}$/', 'default' => '#e11d48'],
            'font'          => ['label' => 'Yazı tipi', 'type' => 'select', 'rules' => 'required|in:sans,serif', 'default' => 'sans',
                                'options' => ['sans' => 'Düz', 'serif' => 'Tırnaklı']],
        ],
    ],
    'header' => [
        'label'  => 'Üst kısım',
        'fields' => [
            'cta_text' => ['label' => 'Düğme yazısı', 'type' => 'text', 'rules' => 'nullable|max:30', 'default' => 'Teklif al'],
            'cta_link' => ['label' => 'Düğme bağlantısı', 'type' => 'text', 'rules' => 'nullable|max:255', 'default' => '#iletisim'],
        ],
    ],
    'footer' => [
        'label'  => 'Alt kısım',
        'fields' => [
            'footer_about' => ['label' => 'Tanıtım yazısı', 'type' => 'textarea', 'rules' => 'nullable|max:300'],
        ],
    ],
];
```

Layout'ta kullanım:

```php
$primary = Theme::color('primary_color', '#e11d48');        // geçersiz değerde yedeği döner, CSS'e güvenle yazılır
$serif   = Theme::get('font') === 'serif';
...
--font-sans: <?= $serif ? 'ui-serif, Georgia, serif' : 'ui-sans-serif, system-ui, sans-serif' ?>;
--color-primary: <?= $primary ?>;
--color-primary-foreground: <?= Theme::contrast($primary) ?>;        // üstündeki yazı rengi (açık/koyu)
...
<?php $ctaLink = (string) Theme::get('cta_link'); if (Theme::get('cta_text') && Menu::validUrl($ctaLink)): ?>
  <a href="<?= View::e(Menu::href($ctaLink)) ?>" class="btn btn-primary" <?= Theme::editable('header', 'cta_text') ?>><?= View::e(Theme::get('cta_text')) ?></a>
<?php endif ?>
```

Kural: kullanıcıdan gelen her değer `View::e()`, renk `Theme::color()`, bağlantı `Menu::validUrl()` + `Menu::href()`.

## 5. Kendi bölümünüz: "İşlerimiz"

```bash
php modrix make:section isler --theme=Ajans
```

**`app/Themes/Ajans/sections/isler/schema.php`**
```php
<?php
return [
    'name'        => 'İşlerimiz',
    'description' => 'Görselli proje kartları.',
    'icon'        => 'grid',
    'fields'      => [
        'title' => ['label' => 'Başlık', 'type' => 'text', 'default' => 'Son işlerimiz'],
        'items' => ['label' => 'Projeler', 'type' => 'repeater', 'item_name' => 'Proje', 'item_label' => 'title', 'max' => 12,
            'fields' => [
                'image' => ['label' => 'Görsel', 'type' => 'image'],
                'title' => ['label' => 'Proje adı', 'type' => 'text', 'default' => 'Yeni proje'],
                'tag'   => ['label' => 'Etiket', 'type' => 'text', 'default' => 'Web'],
                'link'  => ['label' => 'Bağlantı', 'type' => 'link', 'default' => ''],
            ],
            'default' => [
                ['title' => 'Kurumsal site', 'tag' => 'Web'],
                ['title' => 'Marka kimliği', 'tag' => 'Tasarım'],
                ['title' => 'Mobil uygulama', 'tag' => 'Uygulama'],
            ]],
    ],
];
```

**`app/Themes/Ajans/sections/isler/view.php`**
```php
<?php // İşlerimiz. $s: title, items[image, title, tag, link] ?>
<section class="py-20">
  <div class="wrap">
    <h2 class="text-3xl font-semibold tracking-tight" <?= $edit('title') ?>><?= View::e($s['title']) ?></h2>
    <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
      <?php foreach ($s['items'] as $i => $item): ?>
        <a href="<?= $item['link'] !== '' ? View::e(Menu::href($item['link'])) : '#' ?>" class="group block">
          <div class="aspect-[4/3] overflow-hidden rounded-xl bg-muted" <?= $edit("items.$i.image") ?>>
            <?php if ($item['image']): ?>
              <img src="<?= View::e(Request::url('media/' . $item['image'])) ?>" alt="" class="size-full object-cover transition duration-500 group-hover:scale-105">
            <?php endif ?>
          </div>
          <p class="mt-4 text-xs font-medium uppercase tracking-wider text-primary"><?= View::e($item['tag']) ?></p>
          <h3 class="mt-1 text-lg font-semibold" <?= $edit("items.$i.title") ?>><?= View::e($item['title']) ?></h3>
        </a>
      <?php endforeach ?>
    </div>
  </div>
</section>
```

Özelleştir'de "Ana sayfa bölümleri" altında **İşlerimiz** kart kart düzenlenir: proje ekle, sürükleyip sırala, görsel seç.
Ayrıntılar ve tüm alan türleri: [Bölümler](sections.md).

## 6. Bir modülün sayfasını temaya uydurmak

Modül görünümleri temada aynı yolla ezilir. Blog listesini Ajans tarzında göstermek için:

**`app/Themes/Ajans/modules/Blog/index.php`**
```php
<?php // Blog::index'i ezer. Değişkenler modülden: $title, $metaDescription, $posts (sayfalı) ?>
<h1 class="text-4xl font-semibold tracking-tight"><?= View::e($title) ?></h1>
<div class="mt-10 grid gap-8 sm:grid-cols-2">
  <?php foreach ($posts['data'] as $post): ?>
    <a href="<?= Router::url('blog.show', ['slug' => $post['slug']]) ?>" class="card block p-6 transition hover:-translate-y-0.5">
      <p class="text-xs text-muted-foreground"><?= View::date($post['created_at']) ?></p>
      <h2 class="mt-2 text-xl font-semibold"><?= View::e($post['title']) ?></h2>
      <p class="mt-2 line-clamp-2 text-muted-foreground"><?= View::e(Html::text($post['body'], 160)) ?></p>
    </a>
  <?php endforeach ?>
</div>
<div class="mt-10"><?= View::render('partials/pagination', ['page' => $posts]) ?></div>
```

Hangi değişkenlerin geldiğini öğrenmek için modülün kendi görünümüne bakın (`app/Modules/Blog/Views/index.php`).
Temada olmayan her dosya Başlangıç temasından ya da modülün kendisinden gelir; sadece değiştirmek istediğinizi yazın.

## 7. Sayfa şablonu

`theme.json`'daki `"landing"` şablonu için:

**`app/Themes/Ajans/modules/Pages/templates/landing.php`**
```php
<?php // Sayfalar > Şablon: "Açılış sayfası". Başlık büyük ve ortalı, içerik geniş. Değişkenler: $page, $isDraft ?>
<?php if ($isDraft): ?><div class="alert alert-warning">Taslak: sadece siz görüyorsunuz.</div><?php endif ?>
<div class="mx-auto max-w-3xl py-10 text-center">
  <h1 class="text-5xl font-semibold tracking-tight"><?= View::e($page['title']) ?></h1>
  <?php if ($page['meta_description']): ?><p class="mt-4 text-lg text-muted-foreground"><?= View::e($page['meta_description']) ?></p><?php endif ?>
</div>
<div class="prose-content mx-auto max-w-4xl"><?= Hook::filter('content.html', Html::render($page['body']), $page) ?></div>
```

`Hook::filter('content.html', ...)` içerikteki kısa kodların (`[form iletisim]`) çalışmasını sağlar.

Şablon, tema **etkinken** Sayfalar düzenleyicisindeki "Şablon" listesinde çıkar.

## 8. Kendi CSS ve JS dosyanız

`assets/` altındaki dosyalar `/theme/ajans/...` adresinden sunulur (temanın PHP dosyalarına dışarıdan ulaşılamaz):

**`app/Themes/Ajans/assets/css/ajans.css`**
```css
/* Tailwind'in dışında kalan özel stiller */
.ajans-underline { text-decoration: underline; text-decoration-color: var(--color-primary); text-underline-offset: 6px; }
```

Layout'ta: `<link rel="stylesheet" href="<?= Theme::asset('css/ajans.css') ?>">` (adres dosya değişince kendiliğinden yenilenir).

## 9. Ekran görüntüsü ve teslim

- `screenshot.svg` (ya da `.png`, `.webp`, `.jpg`): 4:3 oranında, ör. 1200×900 bir ekran görüntüsü.
- Kontrol listesi: [Temalar](themes.md#kontrol-listesi).
- Tema klasörünü (`app/Themes/Ajans`) başka siteye kopyalamak yeterlidir; Görünüm > Temalar'da çıkar.
