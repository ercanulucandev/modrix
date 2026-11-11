# Güvenlik

Kurallar kısa; uyulmazsa satılan bir modül müşterinin sitesini açık bırakır.

## 1. Her çıktıyı kaçırın

```php
<?= View::e($order['name']) ?>                  <!-- doğru -->
<?= $order['name'] ?>                            <!-- YANLIŞ: XSS -->
<a href="<?= View::e(Menu::href($url)) ?>">      <!-- bağlantı: önce Menu::validUrl($url) -->
<div style="color: <?= Theme::color('x', '#000') ?>">   <!-- renk: Theme::color doğrular -->
```

Ham HTML sadece **temizlenmiş** içerik için: `Html::render($post['body'])`. JS'e veri: `json_encode($x, JSON_HEX_TAG | JSON_HEX_AMP)`.

## 2. Editör içeriği temizlenir

Kullanıcıdan gelen HTML kaydetmeden önce `Html::sanitize()` ile temizlenir: izinli etiketler (p, h2–h4, strong, em, a, listeler,
alıntı, kod, tablo, görsel), `<script>`, `<iframe>`, `on*` olayları ve `javascript:` bağlantıları silinir; görseller sadece medya
kütüphanesinden (`/media/...`) olabilir. Bölümlerin `richtext` alanları ve sayfa/yazı içerikleri bunu otomatik yapar.

## 3. CSRF

Site ve admin rotaları (`web` grubu) POST/PUT/DELETE'te CSRF anahtarı ister:

- Formlarda: `<?= View::csrf() ?>`
- JS'te: `X-CSRF-TOKEN` başlığı (`@app/lib/app.js` → `apiClient` otomatik gönderir; anahtar `meta[name=csrf-token]`)

## 4. Yetki

- Admin sayfaları ve API'leri: rotada **`admin` middleware**. Admin controller'ları `AdminController`'dan türer.
- Modül JS/CSS'inde yolunda `admin/` klasörü geçen dosyalar sadece giriş yapmış admin'e sunulur.
- Kayıt/silme işlemlerinde id'nin gerçekten o kayda (ve o formdaki/üst kayda) ait olduğunu kontrol edin:
  `->where('form_id', $formId)->where('id', $entryId)`.

## 5. Veritabanı

Sorgu oluşturucu değerleri her zaman bağlar. **Kullanıcı girdisini SQL metnine eklemeyin**; `whereRaw` kullanacaksanız
bağlam parametresiyle: `->whereRaw('YEAR(created_at) = ?', [$yil])`. Kolon adı kullanıcıdan geliyorsa beyaz listeden seçin.

## 6. Dosya yükleme

`Upload::save('alan', 'klasor', ['types' => ['pdf'], 'max' => 2 * 1024 * 1024])`: uzantı **ve içerik** (MIME) kontrol edilir,
dosya rastgele adla `storage/uploads/` altına (dışarıya kapalı) yazılır. Görseller için medya kütüphanesi (`MediaService`).
Kullanıcıya dosya vermek: `Response::download(Upload::path($path), $ad)`.

## 7. Formlar ve spam

Herkese açık formlar için Formlar modülünün yaklaşımı: gizli tuzak alanı, imzalı zaman damgası (çok hızlı gönderim = bot),
IP başına sınır. Kendi formlarınızda da en azından CSRF + doğrulama + sınır kullanın.

## 8. E-posta başlıkları

Kullanıcıdan gelen değeri e-posta başlığına (`Reply-To`, konu) koymadan önce doğrulayın / satır sonlarını silin
(`filter_var($e, FILTER_VALIDATE_EMAIL)`, `str_replace(["\r", "\n"], ' ', $x)`).

## 9. Gizli bilgiler

Şifre, API anahtarı gibi bilgileri koda yazmayın: `app/Config/*.local.php` (git'e girmez) ya da modül ayarı.
Log'a yazarken `Log::mask()` ile gizleyin.
