# Hook'lar

Modüller ve temalar çekirdeğe dokunmadan sisteme hook'larla eklenir.

```php
Hook::on('olay.adi', function (...$args) { ... });     // dinle
Hook::fire('olay.adi', $veri);                          // tetikle
$deger = Hook::filter('olay.adi', $deger, $ek);         // değeri dinleyicilerden geçir
```

Kayıt yerleri: `app/Hooks.php` (uygulama), `app/Modules/{Ad}/hooks.php` (modül aktifken), `app/Themes/{Tema}/functions.php`
(tema etkinken, sadece sitede). Bu dosyalar **her istekte** yüklenir: sorguları dosyanın üst seviyesine değil,
`Hook::on(...)` içindeki fonksiyona yazın.

## Filtreler (değer döndürün)

| Hook | Değer | Örnek |
|---|---|---|
| `site.menu` | menü öğeleri (menü atanmamışken) | `fn($m) => [...$m, ['url' => 'siparis', 'label' => 'Sipariş', 'order' => 40]]` (ya da module.json → menu.site) |
| `admin.menu` | panel menüsü | `['url' => 'admin/siparis', 'label' => 'Siparişler', 'icon' => 'package', 'order' => 30, 'badge' => 3]` |
| `admin.dashboard.stats` | genel bakış sayı kutuları | `['label' => 'Sipariş', 'value' => 12, 'hint' => '3 bekliyor', 'url' => 'admin/siparis', 'order' => 30, 'permission' => 'siparis.manage']` |
| `admin.dashboard.widgets` | genel bakış kartları (yarım/tam genişlik) | `['order' => 20, 'size' => 'half', 'html' => (string) View::render('Siparis::admin/widget')]` |
| `admin.dashboard.actions` | genel bakış hızlı işlem düğmeleri | `['label' => 'Yeni sipariş', 'url' => 'admin/siparis/create', 'icon' => 'plus', 'order' => 30]` |
| `admin.dashboard.todos` | "Dikkat isteyenler" listesi | `['text' => '3 sipariş bekliyor', 'url' => 'admin/siparis', 'tone' => 'warning', 'order' => 10]` |
| `admin.dashboard.series` | "Son 30 gün" grafiği sekmesi | `['label' => 'Sipariş', 'points' => Dashboard::daily('siparisler', 'created_at'), 'previous' => Dashboard::previousTotal('siparisler', 'created_at'), 'format' => 'count'\|'money']` |
| `admin.permissions` | Roller ekranındaki yetki listesi | `fn($g) => [...$g, 'Grup' => ['x.y' => 'Açıklama']]` (modüller module.json → permissions kullanır) |
| `admin.settings` | Admin > Ayarlar sekmeleri | (çekirdek için; modüller `settings.php` kullanır) |
| `site.head` | `<head>`'e HTML | `fn($h) => [...$h, '<meta name="x" content="y">']` |
| `site.body_end` | `</body>` öncesine HTML | sabit düğme, bildirim, sohbet kutusu (WhatsApp modülü) |
| `site.home` | ana sayfa içerikleri (eski yöntem) | `['key' => 'x', 'order' => 10, 'html' => '...']`; yenisi: [bölümler](sections.md) |
| `site.front_page` | ana sayfa yerine gösterilecek `View` | Sayfalar modülü: seçilen sayfa ana sayfa olur |
| `content.html` | sayfa/yazı içeriği (HTML) | kısa kodlar: Formlar modülü `[form iletisim]` |
| `sitemap.urls` | /sitemap.xml adresleri | `['loc' => 'blog/x', 'lastmod' => '2026-11-10 10:00:00', 'priority' => '0.6']` |
| `seo.schema` | sayfanın JSON-LD öğeleri | (çoğunlukla `Seo::add([...])` yeterli) |
| `site.search` | /search sonuçları (`$q` ikinci argüman) | `['title' => .., 'url' => .., 'excerpt' => .., 'type' => 'Ürün', 'date' => ..]` |
| `menu.links` | Görünüm > Menüler hazır bağlantılar | `['group' => 'Siparişler', 'label' => 'Sipariş ver', 'url' => 'siparis']` |

## Olaylar (bir şey döndürmeyin)

| Hook | Argümanlar |
|---|---|
| `app.boot` | — (modüller ve tema yüklendikten sonra) |
| `module.installed` / `module.updated` / `module.uninstalled` | `$folder` (güncellemede + eski ve yeni sürüm) |
| `theme.activated` | `$folder` |
| `media.uploaded` / `media.replaced` / `media.deleted` | `$media` |
| `post.created` / `post.updated` / `post.deleted` | `$post` (Blog) |
| `page.created` / `page.updated` / `page.deleted` | `$page` (Sayfalar) |
| `url.moved` | `$from`, `$to` (adres değişti; çekirdek 301 yönlendirme ekler) |
| `form.submitted` | `$form`, `$data` (Formlar) |

Örnek: yeni form mesajında Slack'e bildirim gönderen bir modül:

```php
// app/Modules/Slack/hooks.php
Hook::on('form.submitted', function (array $form, array $data) {
    $text = $form['name'] . ': ' . implode(' · ', array_column($data, 'value'));
    // ... Setting::get('slack_webhook') adresine gönder
});
```

Olay (fire): `cache.hit` ($path) — sayfa önbellekten verildi (bkz. [Performans](performance.md)).

Filtre: `consent.enabled` (false) — çerez onayı modülü true yapar; modül kodunu `type="text/plain" data-consent="..."` ile bekletir.
Olaylar: `collection.saved`, `collection.deleted` ($ad, $öğe) — bkz. [Koleksiyonlar](collections.md).
Filtreler: `cache.skip` (önbelleğe girmeyecek adres önekleri), `site.header_actions` (temanın üst kısmına HTML, ör. sepet simgesi).
Olaylar: `shop.order.created`, `shop.order.paid`, `shop.order.status` — bkz. [Mağaza](https://github.com/ercanulucandev/modrix-store/blob/main/docs/shop.md).
