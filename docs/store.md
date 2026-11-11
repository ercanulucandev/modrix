# Mağaza

**Sistem > Mağaza**: iki sekme, **Modüller** ([modrix-modules](https://github.com/ercanulucandev/modrix-modules)) ve **Temalar**
([modrix-themes](https://github.com/ercanulucandev/modrix-themes)). Her kartta sürüm, yazar, kategori, fiyat ve durum:
**Kur**, **Güncelle** (yeni sürüm varsa), **Yüklü**, ya da uyumsuzsa nedeni (daha yeni Modrix ya da PHP gerekir).

- **Kurulum:** paket indirilir, **SHA-256 özeti katalogdakiyle karşılaştırılır** (tutmazsa kurulmaz), güvenli ZIP kurulumu
  (zip-slip, dosya türü ve boyut denetimi). Modülün migration'ları çalışır ve modül etkinleşir; tema kurulur, etkinleştirmek
  **Görünüm**'den. Güncellemede hata olursa eski sürüm geri gelir. Kaldırılmış (klasörü duran) modül de tekrar kurulabilir.
- **Ücretli ürün:** **Satın al** bağlantısı ve **lisans anahtarı** kutusu; anahtar şifreli saklanır, indirmede
  `X-Modrix-License` başlığıyla gönderilir (lisans sunucusu doğrular).
- Katalog 6 saatte bir yenilenir; "Kataloğu yenile" ile hemen.

## Katalog

Adresler `app/Config/app.php` → `store.modules` ve `store.themes` (HTTPS). Biçim:

```json
{
  "modules": [
    {
      "slug": "Blog", "name": "Blog", "version": "2.1.0", "author": "Modrix", "category": "İçerik",
      "description": "Yazılar, kategoriler, etiketler...",
      "icon": "icons/Blog.svg", "download": "packages/Blog-2.1.0.zip", "sha256": "28df21…",
      "requires": { "modrix": "1.0.0", "php": "8.1" }, "price": 0, "buy_url": ""
    }
  ]
}
```

Temalarda kök anahtar `themes`, simge yerine `screenshot`. Göreli adresler katalog dosyasının yanından alınır.
`slug` = klasör adı.

## Yayınlamak (geliştirici)

Modüller ve ek temalar çekirdek deposunda değil, kendi depolarındadır. Önerilen klasör düzeni:

```
modrix-base/
├── modrix/           çekirdek (bu depo)
├── modrix-modules/   modules/<Ad>  kaynak, packages/, icons/, catalog.json
└── modrix-themes/    themes/<Ad>   kaynak, packages/, screenshots/, catalog.json
```

Geliştirirken çekirdekteki `app/Modules/<Ad>` ve `app/Themes/<Ad>` klasörleri depodaki kaynağa bağlantıdır
(Windows: `mklink /J modrix\app\Modules\Blog modrix-modules\modules\Blog`, Linux/macOS: `ln -s`). Böylece tek kopya
üzerinde çalışırsınız; bağlantılı bir modül ya da tema panelden silinse bile kaynağa dokunulmaz.

Sürümü artırıp (`module.json` / `theme.json`) paketleri ve kataloğu üretin:

```bash
php modrix store:build
```

```bash
php modrix store:build --themes
```

Çıktı varsayılan olarak yandaki `modrix-modules` / `modrix-themes` klasörüdür (yoksa `dist/modules`, `dist/themes`;
başka yer için `--out=klasör`, farklı indirme adresi için `--base=https://.../`). Klasöre girip `git add -A`, `commit`, `push`;
siteler 6 saat içinde yeni sürümü görür. Fiyat, kategori ve `buy_url`'yi `catalog.json`'da elle düzenleyin; sonraki
derleme bunları korur. Ücretli ürünlerin paketlerini açık depoya koymayın (lisans sunucusundan indirilir).

Test için katalog yerel dosya da olabilir: ayarlar tablosunda `store_catalog_modules` / `store_catalog_themes`.
