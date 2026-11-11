# Performans, görseller, geçmiş ve arama

## Sayfa önbelleği

Ayarlar > **Performans** > "Sayfa önbelleği". Açıkken giriş yapmamış ziyaretçiler sayfaların hazır HTML kopyasını görür
(`storage/cache/pages`); veritabanı ve şablonlar çalışmaz. Yanıtta `X-Cache: HIT` / `MISS` başlığı vardır.

- Panelde yapılan **her kayıt** ve her `php modrix` komutu önbelleği temizler; ayrıca süre (varsayılan 60 dk) dolunca yenilenir.
  Zamanlanmış yazılar en geç bu sürede görünür. Elle: Ayarlar > Performans > "Önbelleği temizle" ya da `php modrix cache:clear`.
- Önbelleğe girmeyenler: admin ve site kullanıcısı oturumları, flash mesajlı sayfalar ("Mesajınız gönderildi"), 200 dışı yanıtlar,
  `?page=` dışında sorgulu adresler (`utm_*`, `fbclid`, `gclid` yok sayılır), `/admin`, `/api`, `/media`, `/assets`, site haritası, RSS.
- Formlar çalışır: CSRF anahtarı önbellekte yer tutucuyla saklanır, her ziyaretçiye kendi anahtarı yazılır.
- Önbellekten verilen sayfada `cache.hit` hook'u çalışır (Blog bununla okunma sayısını artırır):
  `Hook::on('cache.hit', fn(string $path) => ...)`.
- Modülünüz ziyaretçiye göre değişen bir şey gösteriyorsa (sepet, "merhaba Ayşe") o sayfayı önbelleğe aldırmayın:
  oturuma `user_id` yazın ya da içeriği JS ile sonradan yükleyin.

## Duyarlı görseller (srcset)

Yüklenen JPG/PNG/WebP görsellerin 640, 1024 ve 1600 piksel genişlikte WebP kopyaları üretilir (orijinalden küçük olanlar;
`MediaService::SIZES`). Telefon küçük kopyayı indirir. Yazı ve sayfa içeriğindeki görsellere `srcset`, `sizes`, `width/height`
ve `loading="lazy"` kendiliğinden eklenir (`content.html`). Şablonda:

```php
<img src="<?= View::e($url) ?>"<?= App\Services\ResponsiveImages::attrs($url, '(max-width: 640px) 100vw, 33vw') ?> alt="">
```

Bu özellikten önce yüklenmiş görseller için: `php modrix media:sizes` (hepsini yeniden: `--all`).

## İçerik geçmişi (revizyonlar)

Yazı ve sayfa düzenleyicisinde **Geçmiş**: her kaydetmede bir sürüm saklanır (içerik başına son 30; aynı içerik tekrar saklanmaz).
Sürüm seçilip önizlenir, "Bu sürümü yükle" başlık/içerik/özeti düzenleyiciye koyar; **kaydedince** geçerli olur.
Modülde:

```php
App\Services\Revision::register('product', 'Ürün', 'shop.manage', ['title', 'body', 'price']);
Hook::on('product.updated', fn(array $p) => App\Services\Revision::save('product', $p));
```

JS: `import { openRevisions } from '@app/lib/revisions.js'` → `openRevisions({ type: 'product', id, onRestore: (fields) => ... })`.

## Site içi arama

`/search?q=...` (arama motorlarına kapalı). Temalarda menünün yanında arama simgesi (Özelleştir > Üst kısım > "Arama simgesi").
Yazılar ve sayfalar aranır; başlıkta geçenler önce gelir. Tema sonuç sayfasını ezebilir: `app/Themes/{Tema}/views/search.php`.
Modül sonuç ekler:

```php
Hook::on('site.search', fn(array $r, string $q) => [...$r,
    ['title' => $p['name'], 'url' => Router::url('shop.show', ['slug' => $p['slug']]), 'excerpt' => '...', 'type' => 'Ürün', 'date' => null]]);
```
