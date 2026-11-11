# Koleksiyonlar

Basit içerik listeleri (SSS, ekip, referanslar, şubeler, hizmetler...) için hazır altyapı: tablo, controller ve admin
sayfası yazmadan liste, form, sürükle-bırak sıralama ve gizle/göster. SSS, Ekip, Referanslar ve Portfolyo modülleri bununla yazılmıştır.

## Kendi koleksiyonunuz

Basit içerik listeleri (hizmetler, şubeler, ürün özellikleri...) için tablo, controller, admin sayfası yazmanız gerekmez.
Modülün `hooks.php`'sinde tanımlayın; Admin menüsünde liste, form, sürükle-bırak sıralama ve gizle/göster hazır gelir:

```php
use App\Services\Collection;

Collection::register('branches', [
    'label'      => 'Şubeler',            // menü ve sayfa başlığı
    'singular'   => 'Şube',               // "Şube ekle"
    'icon'       => 'pin',                // core/Icon.php
    'permission' => 'branches.manage',    // module.json "permissions" içinde tanımlayın
    'order'      => 46,                   // admin menüsünde sıra
    'title'      => 'name',               // listede başlık olan alan
    'subtitle'   => 'city',               // isteğe bağlı
    'image'      => 'photo',              // isteğe bağlı: listede küçük resim
    'slug'       => true,                 // isteğe bağlı: kendi sayfası olacaksa adres üretilir
    'url'        => fn($slug) => "branches/$slug",   // adres değişince otomatik 301 için
    'fields'     => [
        'name'  => ['label' => 'Şube adı', 'type' => 'text', 'rules' => 'required|max:100'],
        'city'  => ['label' => 'Şehir', 'type' => 'select', 'options' => ['ist' => 'İstanbul', 'ank' => 'Ankara']],
        'photo' => ['label' => 'Fotoğraf', 'type' => 'image'],
        'gallery' => ['label' => 'Galeri', 'type' => 'gallery', 'max' => 20],
        'phone' => ['label' => 'Telefon', 'type' => 'tel', 'rules' => 'nullable|phone'],
        'open'  => ['label' => 'Hafta sonu açık', 'type' => 'checkbox'],
    ],
]);
```

Alan türleri: `text`, `textarea`, `email`, `url`, `tel`, `number`, `select`, `checkbox`, `color`, `image`, `gallery`.
Kurallar `Validator` ile aynı (`required|max:100`...). Görseller medya kütüphanesinden seçilir ve sunucuda doğrulanır.

```php
Collection::items('branches');                                  // görünürler, sırayla: [['id', 'title', 'slug', 'name', 'city', ...], ...]
Collection::items('branches', fn($b) => $b['city'] === 'ist');  // süzerek
Collection::findBySlug('branches', 'kadikoy');                  // tek öğe (görünürse)
Collection::imageUrl($b['photo']);                              // görsel adresi
```

Olaylar: `collection.saved` ($ad, $öğe), `collection.deleted` ($ad, $öğe). Veriler `collection_items` tablosunda (JSON);
modül kaldırılınca silinmesi için modülün `.down.sql` migration'ına: `DELETE FROM collection_items WHERE collection = 'branches';`
Hazır örnekler: [modrix-store](https://github.com/ercanulucandev/modrix-store) → `modules/Faq`, `Team`, `References`, `Portfolio`.
