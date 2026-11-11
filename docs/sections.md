# Bölümler (sections)

Ana sayfa hazır **bölümlerden** oluşur. Kullanıcı Görünüm > Özelleştir > **Ana sayfa bölümleri**'nde bölümleri sürükleyerek
sıralar, kütüphaneden yenisini ekler, çoğaltır, gizler, siler; her bölümün kendi alanları vardır (yazı, görsel, liste...).
Önizlemede bir bölüme ya da içindeki yazıya tıklayınca paneldeki ilgili alan açılır.

## Bölüm türü nerede yazılır?

| Yer | Kimlik | Kimde görünür | İskelet |
|---|---|---|---|
| `app/Themes/{Tema}/sections/{ad}/` | `{ad}` | sadece o tema | `php modrix make:section ad --theme=Tema` |
| `app/Modules/{Modül}/Sections/{ad}/` | `{modül}.{ad}` | modül aktifken her tema | `php modrix make:section ad --module=Modül` |
| `app/Sections/{ad}/` | `core.{ad}` | her tema | `php modrix make:section ad` |

Hazır genel bölümler: `core.text` (yazı bloğu), `core.cta` (çağrı bandı), `core.image-text` (görsel ve yazı),
`core.modules` (eski tip modül içerikleri). Modüllerden: `blog.latest` (son yazılar), `forms.form` (form).

**Tema mı, genel mi?** Temanın tasarımına sıkı bağlı bölümler (özel renkler, özel sınıflar) temaya; her temada makul görünen
(ortak sınıfları kullanan) bölümler genele ya da modüle.

## schema.php

```php
<?php
return [
    'name'        => 'Hizmetler',                // kütüphanede görünen ad
    'description' => 'Simgeli hizmet kartları.',
    'icon'        => 'briefcase',                // core/Icon.php'deki adlardan
    'requires'    => 'Blog',                     // isteğe bağlı: sadece bu modül aktifken listelenir
    'fields'      => [
        'title' => ['label' => 'Başlık', 'type' => 'text', 'default' => 'Hizmetlerimiz'],
        'items' => [
            'label'      => 'Hizmetler',
            'type'       => 'repeater',
            'item_name'  => 'Hizmet',            // "Hizmet ekle" düğmesi, "Hizmet 3" gibi adlar
            'item_label' => 'title',             // listede öğenin adı olarak gösterilecek alan
            'max'        => 12,
            'fields'     => [
                'icon'  => ['label' => 'Simge', 'type' => 'icon', 'default' => 'briefcase'],
                'title' => ['label' => 'Başlık', 'type' => 'text', 'default' => 'Yeni hizmet'],
                'text'  => ['label' => 'Açıklama', 'type' => 'textarea', 'default' => ''],
            ],
            'default'    => [['icon' => 'rocket', 'title' => 'Uygulama', 'text' => '...']],
        ],
    ],
];
```

Bölüm eklenince alanlar `default` değerleriyle dolu gelir: **her alana anlamlı bir varsayılan verin**, kullanıcı boş bir
bölümle karşılaşmasın.

### Alan türleri

| Tür | Panelde | `$s` içindeki değer | Ek ayarlar |
|---|---|---|---|
| `text` | tek satır | metin (en fazla `max`, varsayılan 300) | `max`, `placeholder`, `help` |
| `textarea` | çok satır | metin (varsayılan en fazla 5000) | `rows`, `max` |
| `richtext` | HTML editör | **temizlenmiş** HTML → `Html::render()` | `max` |
| `link` | bağlantı | geçerli adres ya da `''` → `Menu::href()` | |
| `image` | medya kütüphanesinden seçim | medya yolu ya da `''` → `Request::url('media/' . $x)` | |
| `select` | açılır liste | seçeneklerden biri (geçersizse varsayılan) | `options => [deger => etiket]` |
| `icon` | simge seçici | `core/Icon.php`'deki ad → `Icon::svg($x)` | |
| `checkbox` | aç/kapa | `true` / `false` | |
| `color` | renk | `#rrggbb` (geçersizse varsayılan) | |
| `number` | sayı | sayı (sınırlanmış) | `min`, `max`, `step` |
| `repeater` | sıralanabilir kart listesi | öğe dizisi (her öğe iç alanlara göre temizlenmiş) | `fields`, `max`, `item_name`, `item_label` |

Değerler türüne göre **sunucuda temizlenir**; yine de yazdırırken her zaman `View::e()` kullanın.

## view.php

```php
<?php // $s: değerler · $edit('alan'): tıkla-düzenle işareti · $section: ['id', 'type', 'hidden', 'data'] · $context ?>
<section class="py-20">
  <div class="wrap">
    <h2 <?= $edit('title') ?>><?= View::e($s['title']) ?></h2>
    <div class="mt-10 grid gap-6 md:grid-cols-3">
      <?php foreach ($s['items'] as $i => $item): ?>
        <div class="card p-6">
          <?= Icon::svg($item['icon'], 'size-6') ?>
          <h3 <?= $edit("items.$i.title") ?>><?= View::e($item['title']) ?></h3>
          <?php if ($item['text'] !== ''): ?><p <?= $edit("items.$i.text") ?>><?= View::e($item['text']) ?></p><?php endif ?>
        </div>
      <?php endforeach ?>
    </div>
  </div>
</section>
```

- **Bölüm kendi kabını çizer:** `<section>` + `<div class="wrap">`. Layout ana sayfada kap çizmez.
- **`$edit('alan')`** önizlemede tıklanınca o alanı açar; repeater içinde `$edit("items.$i.title")`. Ziyaretçide boş döner.
- Gösterecek bir şey yoksa erken çıkın: `<?php if (!$s['items']) return; ?>`.
- Menüden bölüme gitmek için bir "anchor" alanı ekleyip `id="..."` verin: `#iletisim`.
- Görünümde hata olursa o bölüm atlanır (önizlemede kırmızı uyarı), sayfa çökmez; hata log'a yazılır.
- `$context['moduleSections']`: ana sayfanın verdiği eski tip modül içerikleri (`core.modules` bölümü kullanır).

## Ana sayfada yazdırma ve varsayılan düzen

```php
<?php // app/Themes/{Tema}/views/home.php ?>
<?= Section::render('home', ['moduleSections' => $sections]) ?>
```

```json
// theme.json
"sections": { "home": [{ "type": "hero" }, { "type": "services" }, { "type": "blog.latest" }, { "type": "forms.form" }] }
```

Kullanıcı ilk kez yayınlayana kadar bu düzen kullanılır. Yayınlanan düzen temaya özel ayar olarak saklanır
(`theme_{tema}_sections_home`, JSON); tema değişince her temanın kendi düzeni korunur. Bulunamayan türler (modül pasife alındı,
bölüm silindi) sessizce atlanır.
