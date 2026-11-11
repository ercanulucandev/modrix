# Ayarlar

Tüm ayarlar tek bir `settings` tablosunda anahtar/değer olarak durur; nereden tanımlandıklarına göre üç yerde düzenlenir.

| Tanım | Düzenlendiği yer | Anahtar | Okuma |
|---|---|---|---|
| `app/Config/settings.php` (sekmeler) | Admin > Ayarlar | `site_name` | `Setting::get('site_name')` |
| `app/Modules/{Ad}/settings.php` | Admin > Modüller > {Ad} > Ayarlar | modül adıyla başlar: `blog_per_page` | `Setting::get('blog_per_page')` |
| `app/Themes/{Tema}/settings.php` | Görünüm > Özelleştir | kayıtta `theme_{tema}_...` | `Theme::get('primary_color')` |

## Okuma

```php
Setting::get('site_name')                 // kayıt yoksa tanımdaki 'default'
Setting::get('x', 'yedek')                // tanımda varsayılan da yoksa
Setting::bool('maintenance_mode')         // checkbox
Setting::url('logo')                      // görsel alanı -> /media/... adresi ya da null
Setting::title('Blog')                    // "Blog · Site adı" (SEO > başlık biçimine göre)
Setting::set(['site_name' => 'X'])        // kaydetme (formlar bunu kendisi yapar)

Theme::get('hero_title')   Theme::bool('sticky_header')   Theme::url('hero_image')
Theme::color('primary_color', '#000000')  // geçersizse yedek: CSS'e güvenle yazılır
```

## Alan tanımı

Üç dosyada da aynı biçim:

```php
'blog_per_page' => [
    'label'       => 'Sayfa başına yazı',
    'type'        => 'number',
    'rules'       => 'required|integer|between:1,50',   // doğrulama (bkz. routing.md)
    'default'     => '10',
    'help'        => 'Alanın altında görünen açıklama.',
    'placeholder' => '10',
],
```

| Tür | |
|---|---|
| `text`, `email`, `url`, `tel`, `number`, `textarea` | yazı alanları |
| `select` | `'options' => ['deger' => 'Etiket']` |
| `checkbox` | aç/kapa; değer `'1'` / `'0'` |
| `color` | renk seçici + `#rrggbb` |
| `image` | medya kütüphanesinden seçilir; değer dosya yolu (`Setting::url()` ile adres) |
| `code` | tek aralıklı yazı (özel kod alanları) |

**Modül ayarı** (`settings.php`):

```php
<?php
return [
    'description' => 'Sipariş bildirimleri.',
    'fields' => [
        'siparis_notify' => ['label' => 'Yeni siparişte e-posta gönder', 'type' => 'checkbox', 'default' => '1'],
    ],
];
```

Anahtarlar **modül adıyla başlamalı** (`siparis_...`); başlamayan alan yok sayılır, böylece modül başka ayarı ezemez.
Dosya varsa Admin > Modüller'de modülün yanında **Ayarlar** düğmesi çıkar.

**Tema ayarı** bölümlere ayrılır; her bölüm Özelleştir panelinde bir sekmedir. Ayrıntı: [Temalar](themes.md#4-özelleştir-alanları-settingsphp).
Ana sayfanın içeriği tema ayarı değil, [bölümlerdir](sections.md).
