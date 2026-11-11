# Mağaza

**Sistem > Mağaza**: iki sekme, **Modüller** ([modrix-store](https://github.com/ercanulucandev/modrix-store)) ve **Temalar**
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

Modüller ve ek temalar çekirdek deposunda değil, kendi depolarındadır; geliştirirken `app/Modules` ve `app/Themes` altında
durur. Sürümü artırıp (`module.json` / `theme.json`) paketleri ve kataloğu üretin:

```bash
php modrix store:build --base=https://raw.githubusercontent.com/ercanulucandev/modrix-store/main/
```

```bash
php modrix store:build --themes --base=https://raw.githubusercontent.com/ercanulucandev/modrix-themes/main/
```

Çıktı `dist/store/` ve `dist/themes/`: bu klasörler mağaza depolarının yerel kopyalarıdır (`catalog.json`, `packages/*.zip`,
simgeler/ekran görüntüleri ve GitHub'da okunabilsin diye kaynak kod). Klasöre girip `git add -A`, `commit`, `push`;
siteler 6 saat içinde yeni sürümü görür. Fiyat, kategori ve `buy_url`'yi `catalog.json`'da elle düzenleyin; sonraki
derleme bunları korur. Ücretli ürünlerin paketlerini açık depoya koymayın (lisans sunucusundan indirilir).

Test için katalog yerel dosya da olabilir: ayarlar tablosunda `store_catalog_modules` / `store_catalog_themes`.
