# Çoklu dil

Admin > Sistem > **Diller**'den dil ekleyin. İlk dil varsayılandır, adresleri öneksizdir; diğerleri kodla başlar:
`/hakkimizda` (Türkçe) · `/en/about` (İngilizce). Tek dil varken hiçbir şey değişmez (önek, dil seçici, hreflang yok).

| Ne | Nasıl çevrilir |
|---|---|
| Sayfalar, yazılar | Listede dil sekmesi; düzenleyicide **Çeviriler** kutusu → "Çeviri oluştur" (taslak kopya açılır, çevirip yayınlayın). Adres her dilde farklı olabilir (`hakkimizda` ↔ `about`). |
| Çevrilmemiş içerik | `/en/hakkimizda` çevirisi yoksa Türkçe sayfaya yönlenir (404 olmaz). |
| Site adı, slogan, alt bilgi, SEO açıklaması, modül metinleri... | Ayarlar'da her yazı alanının altında dil kutuları (boşsa varsayılan dildeki görünür). |
| Menüler | Görünüm > Menüler > konumlar: "Üst menü · English" (boşsa varsayılan dildeki menü). |
| Ana sayfa (Sayfalar modülü) | Seçilen sayfanın o dildeki çevirisi kendiliğinden kullanılır. |
| Düğmeler, başlıklar ("Sepete ekle", "Tüm yazılar") | Diller > **Arayüz metinleri**. |

Dil seçici temaların üst kısmındadır; sayfanın çevirisi varsa ona, yoksa o dilin ana sayfasına gider. Her sayfaya `hreflang`
etiketleri eklenir, site haritası tüm dillerdeki adresleri içerir. Arapça/Farsça seçilince sayfa `dir="rtl"` olur.

## Geliştirici

```php
Lang::current();                    // 'en'
Lang::t('Sepete ekle');             // arayüz metni (kaynak metin Türkçe yazılır; Diller > Arayüz metinleri'nde listelenir)
Lang::t(':n ürün', ['n' => 3]);
Lang::url('blog', 'de');            // /de/blog
Request::url('blog');               // geçerli dilde: İngilizce sayfada /en/blog (panel, medya, dosya adresleri önek almaz)
Request::path();                    // önek olmadan: /en/blog -> /blog
Lang::setAlternates(['tr' => 'urunler/x', 'en' => 'products/x']);   // dil seçici ve hreflang için bu sayfanın karşılıkları
```

- **Ayarlar:** `Setting::get('x')` İngilizce sayfada `x__en` doluysa onu döndürür. Tema ayarları, ana sayfa bölümleri ve menü
  konumları da Setting'den okunduğu için aynı kuralla dile göre değişir. Ayar alanını çeviriden çıkarmak: `'translatable' => false`
  (alan ya da Ayarlar grubu).
- **İçerik tabloları:** `lang` ve `translation_group` kolonları (grup = ilk satırın id'si). Yardımcı: `App\Services\Translations::siblings($tablo, $satır)`.
  Sorgularda `Lang::content()` (sitede geçerli dil, panelde düzenlenen dil) ile süzün.
- Henüz dile göre ayrılmayanlar: blog kategorileri/etiketleri, koleksiyonlar (SSS, ekip...), mağaza ürünleri, ana sayfa
  bölümlerinin içeriği (Özelleştir'de dil seçimi). Bunlar varsayılan dildeki haliyle tüm dillerde görünür.
