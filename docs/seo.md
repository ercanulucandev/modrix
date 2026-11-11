# SEO

| | Nerede |
|---|---|
| Sayfa başlığı biçimi, meta açıklama, Google Analytics, "arama motorlarından gizle", site sahibi türü, robots.txt ek kuralları | Ayarlar > SEO |
| Yönlendirmeler, bulunamayan adresler (404) | **Admin > SEO** (yetki: `seo.manage`) |
| Site haritası | `/sitemap.xml` (kendiliğinden) |
| robots.txt | `/robots.txt` (kendiliğinden; "gizle" açıkken her şeyi kapatır) |
| Sayfaya özel açıklama ve paylaşım görseli | Sayfa düzenleyici > Arama motorları, Öne çıkan görsel |

## Site haritası

`/sitemap.xml` ana sayfa + modüllerin adresleri (yayındaki sayfalar, blog ve yazılar). Google Search Console'a bu adresi
bildirin. Modül eklemek için:

```php
Hook::on('sitemap.urls', fn(array $urls) => [...$urls, ['loc' => 'urunler/x', 'lastmod' => $row['updated_at'], 'priority' => '0.6']]);
```

## Yönlendirmeler

- **Elle:** Admin > SEO > eski adres → yeni adres, 301 (kalıcı) ya da 302 (geçici). `eski-klasor/*` → `yeni-klasor/*`
  altındaki her adresi kalan kısmı koruyarak yönlendirir; hedef tam bir dış adres de olabilir.
- **Otomatik:** yayındaki bir sayfanın ya da yazının adresi değişince (alt sayfalar dahil) eski adres yenisine 301 olur.
  Zincir oluşmaz: tekrar değişirse eski yönlendirmeler doğrudan en yeni adrese çevrilir.
- Yönlendirme sadece **hiçbir sayfanın karşılamadığı** adreslerde çalışır; var olan bir sayfayı ezmez.
- Modül kendi adres değişikliğini bildirir: `Hook::fire('url.moved', 'eski/adres', 'yeni/adres')`.

## Bulunamayanlar (404)

Ziyaretçilerin ulaşamadığı adresler kaydedilir (kaç kez, en son nereden gelindiği). **Yönlendir** düğmesi adresi
yönlendirme formuna taşır. Bot taramaları (`wp-login.php`, `.env`...) ve panel/dosya adresleri kaydedilmez.

## Yapılandırılmış veri (schema.org)

Her sayfanın `<head>`'ine JSON-LD olarak eklenir (`Theme::head()`): **WebSite** + site sahibi (Ayarlar > SEO > "Site kimdir":
şirket, yerel işletme ya da kişi; ad, logo, iletişim, sosyal medya). Blog yazılarında **BlogPosting** (başlık, tarih, kapak).
Modül kendi verisini ekler:

```php
Seo::add(['@type' => 'Product', 'name' => $p['name'], 'offers' => ['@type' => 'Offer', 'price' => $p['price'], 'priceCurrency' => 'TRY']]);
```
