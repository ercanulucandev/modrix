# Modüller (başvuru)

> Sıfırdan bir modül yazmak için adım adım rehber: [Modül yazma: adım adım](module-tutorial.md). İskelet: `php modrix make:module Ad`.

Örnekler: [modrix-modules](https://github.com/ercanulucandev/modrix-modules) deposundaki `modules/` klasörü (Blog, Mağaza, Randevu...) —
her başlıktaki kuralın çalışan hali orada. Modüller çekirdek deposunda tutulmaz; yayınlamak: [Mağaza](store.md#yayınlamak-geliştirici).
Tek paket: `php modrix module:pack Blog` → `dist/Blog-1.0.0.zip`

## 1. Başlangıç

```bash
php modrix make:module Fatura          # iskelet + module.json + hooks.php
php modrix module:install Fatura       # ya da Admin > Modüller > Kur
```

## 2. Klasör yapısı

```
app/Modules/Fatura/
├── module.json          ZORUNLU: ad, sürüm, menüler
├── routes.php           site ve admin sayfaları
├── api.php              /api/... route'ları (isteğe bağlı)
├── hooks.php            ana sayfa / panel bölümleri (isteğe bağlı)
├── settings.php         modülün ayarları (isteğe bağlı) -> Modüller > {Ad} > Ayarlar
├── Sections/            ana sayfaya eklenebilen bölümler (isteğe bağlı; bkz. docs/themes.md 4.1)
├── icon.svg             modül görseli (isteğe bağlı; .svg/.png/.webp/.jpg, kare, en az 128 px)
├── Controllers/         site controller'ları
│   └── Admin/           admin controller'ları
├── Models/
├── Views/
│   └── admin/
├── Migrations/          .sql + .down.sql çiftleri
└── Middlewares/
```

**Klasör adı:** Büyük harfle başlar, sadece harf/rakam: `Fatura`, `AidatTakip`. Namespace bununla birebir aynı olmalı:
`App\Modules\Fatura\Controllers\FaturaController` → `app/Modules/Fatura/Controllers/FaturaController.php`

> Linux sunucuda büyük/küçük harf fark eder. `controllers/` yazarsan Windows'ta çalışır, sunucuda çalışmaz.

## 3. module.json

```json
{
    "name": "Fatura",
    "version": "1.0.0",
    "description": "Fatura takibi",
    "author": "Modrix",
    "requires": { "php": "8.1" },
    "menu": {
        "site":  [{ "url": "faturalar", "label": "Faturalar", "order": 30 }],
        "admin": [{ "url": "admin/faturalar", "label": "Faturalar", "icon": "document", "order": 30, "group": "content" }]
    }
}
```

- `version` mutlaka `1.0.0` biçiminde. **Her yeni pakette artır.** Aynı ya da eski sürüm yüklenemez.
- Menü sırası: `order` küçük olan üstte (grup içinde de). Modüllere 10–80 arası ver.
- **Sade menü:** kenar çubuğunda sadece gruplar görünür; grubun sayfalarında üstte grubun sekmeleri çıkar. Öğeye `"group"`
  verin: `content` (İçerik), `shop` (Mağaza), `showcase` (Tanıtım), `marketing` (Pazarlama), `system` (Sistem).
  `"tab": "Kısa ad"` sekmedeki yazı (yoksa `label`). Grupsuz öğe kenar çubuğunda tek başına durur; sadece gerçekten ana
  bölüm olan modüller grupsuz kalsın. Yeni grup: `Hook::on('admin.menu.groups', fn($g) => [...$g, 'crm' => ['label' => 'Müşteriler', 'icon' => 'users', 'order' => 25]])`.
  Öğeye ait başka adresler (ör. alt sayfalar farklı adresteyse): `"match": ["admin/faturalar-raporlar"]`.
- Admin ikonları: `home`, `document`, `users`, `cog`, `chat`, `chart`, `mail`, `image`, `package` (yenisi için `app/Views/layouts/admin.php` → `$icons`).
- **Admin menüsüne sadece yönetilecek içeriği olan modüller girer** (yazılar, faturalar, siparişler...). Sadece ayarı olan
  modül (WhatsApp düğmesi, analiz kodu, çerez bildirimi...) menü eklemez; ayarları `settings.php` ile tanımlar (bkz. 3.1).

### 3.1 Modül ayarları (settings.php)

Ayarı olan modülün Admin > Modüller listesinde **Ayarlar** düğmesi çıkar, ayarlar orada ayrı bir sayfada düzenlenir
(`/admin/modules/{Ad}/settings`). Genel Ayarlar sayfası sadece sitenin kendi ayarlarına ayrılmıştır; modül sayısı
artınca kalabalıklaşmaz.

```php
<?php
// app/Modules/Fatura/settings.php
return [
    'description' => 'Fatura numaralandırma ve vergi ayarları.',
    'fields' => [
        'fatura_prefix' => ['label' => 'Fatura ön eki', 'type' => 'text', 'rules' => 'required|max:10', 'default' => 'FT'],
        'fatura_vat'    => ['label' => 'KDV oranı (%)', 'type' => 'number', 'rules' => 'required|integer|between:0,100', 'default' => '20'],
        'fatura_email'  => ['label' => 'Kopya gönder', 'type' => 'checkbox', 'default' => '0'],
    ],
];
```

- Alan türleri ve kuralları `app/Config/settings.php` ile aynı: text, textarea, email, url, tel, number, color, checkbox, select, image, code.
- **Anahtarlar modül adıyla başlamalı** (`fatura_...`). Başlamayan alan yok sayılır; böylece modül başka bir ayarı ezemez.
- Okuma her yerde aynı: `Setting::get('fatura_prefix')`, `Setting::bool('fatura_email')`. Kayıt yoksa tanımdaki `default` döner.
- Pasif modülün ayarları da düzenlenebilir; kaldırınca (uninstall) ayar değerleri silinmez, tekrar kurulunca geri gelir.

## 4. Route'lar

```php
// routes.php
use App\Modules\Fatura\Controllers\FaturaController;
use App\Modules\Fatura\Controllers\Admin\FaturaController as AdminFaturaController;

Router::group(['prefix' => '/faturalar'], function () {
    Router::get('/', [FaturaController::class, 'index'])->name('fatura.index');
});

Router::group(['prefix' => '/admin/faturalar', 'middleware' => ['admin']], function () {
    Router::get('/', [AdminFaturaController::class, 'index'])->name('admin.fatura.index');
    Router::post('/{id}/delete', [AdminFaturaController::class, 'destroy'])->name('admin.fatura.destroy');
});
```

- **Adresleri modül adıyla başlat** (`/faturalar`, `/admin/faturalar`); başka modülün adresiyle çakışmasın.
- **Route adlarını modül adıyla başlat** (`fatura.index`, `admin.fatura.index`).
- **Admin sayfalarında `'middleware' => ['admin']` unutma**, yoksa herkes girer.
- Adreslerde İngilizce terim: `create`, `edit`, `delete`, `?page=`, `?status=`.
- Sabit adresler (`/create`) parametreli olanlardan (`/{slug}`) **önce** tanımlanmalı.

## 5. Controller ve view

```php
namespace App\Modules\Fatura\Controllers\Admin;

use App\Controllers\Admin\AdminController;   // admin: layout 'admin'
use Core\View;

class FaturaController extends AdminController
{
    public function index(): View
    {
        return $this->view('Fatura::admin/index', [
            'faturalar' => Fatura::query()->latest()->paginate(20),
        ]);
    }
}
```

- Site controller'ı `Core\Controller`'dan, admin controller'ı `AdminController`'dan türet.
- View adı: `'Fatura::admin/index'` → `app/Modules/Fatura/Views/admin/index.php`
- Ortak parçalar: `View::render('partials/field', [...])`, `View::render('partials/pagination', ['page' => $x])`

## 6. Veritabanı ve migration'lar

```
Migrations/2026_12_01_100000_fatura_create_invoices.sql
Migrations/2026_12_01_100000_fatura_create_invoices.down.sql
```

- **Tablo adlarına modül öneki koy:** `fatura_invoices`, `fatura_items`. Başka modülün ya da müşterinin tablosuyla çakışmaz.
- **Dosya adına da modül adını ekle.** Migration'lar dosya adıyla takip edilir; iki modülde aynı ad olursa biri çalışmaz.
- **Her `.sql` için bir `.down.sql` yaz** (`DROP TABLE IF EXISTS ...`). Yoksa modül kaldırılınca tablo kalır.
- **Yayınlanmış bir migration'ı asla değiştirme.** Müşteride zaten çalıştı, değişiklik gitmez. Yeni dosya ekle:
  `2026_12_15_090000_fatura_add_due_date.sql` → `ALTER TABLE fatura_invoices ADD due_date DATE NULL;`
  Güncellemede sadece yeni dosyalar çalışır.
- Her ifade `;` ile satır sonunda bitsin.
- `php modrix make:migration fatura_create_invoices Fatura` ikisini birden oluşturur.

## 7. Sorgular ve güvenlik

| Yap | Yapma |
|---|---|
| `Fatura::query()->where('status', $s)->get()` | `DB::all("... WHERE status = '$s'")` |
| `$this->validate([...])` | `$_POST` değerini doğrudan kullanmak |
| `<?= View::e($x) ?>` | `<?= $x ?>` (XSS) |
| Formlarda `<?= View::csrf() ?>` | CSRF'siz POST formu (419 alırsın) |
| `Upload::save('file', 'fatura', ['types' => ['pdf']])` | `move_uploaded_file` ile kendi yüklemen |

- Ham SQL gerekiyorsa değerleri **her zaman** `?` ile bağla: `DB::all('... WHERE id = ?', [$id])`.
- Kolon adını kullanıcıdan alma (sıralama vb.); izinli listeden seç.

## 8. JS / CSS (Assets) ve API ile çalışan admin sayfaları

Modülün kendi dosyaları `Assets/` altında durur ve `/assets/{modül}/...` adresinden sunulur (modül adı küçük harfle: `/assets/blog/admin/posts-page.js`)
(sadece modül aktifken; izinli türler: js, css, json, resim, font).
Yolunda `admin` klasörü geçen dosyalar (ör. `Assets/admin/...`) sadece giriş yapmış admin'e sunulur; sitede kullanılan JS/CSS'i oraya koyma.

```php
<script type="module" src="<?= View::e(Module::asset('Fatura', 'admin/invoices-page.js')) ?>"></script>
```

`Module::asset()` adrese sürüm etiketi ekler; dosya değişince tarayıcı yenisini alır. ES modülleri birbirini
göreli yolla import edebilir (`import { x } from './posts-api.js'`).

### Ortak arayüz kiti (`@app/lib/...`)

Core'un hazır JS bileşenleri `app/Assets/lib/` altındadır ve admin sayfalarında `@app/` kısa adıyla import edilir
(admin layout'undaki import map sayesinde site hangi klasörde kurulu olursa olsun çalışır). Kendi kopyanı yapma:

| Dosya | İçerik |
|---|---|
| `@app/lib/app.js` | `apiClient('admin/api/fatura')` (CSRF + oturum hazır), `url()`, `config` |
| `@app/lib/http.js` | fetch istemcisi, `ApiError` (422 / 401 / 419 / 404) |
| `@app/lib/dom.js` | `h()` güvenli eleman oluşturma, `icon()`, `formatDate()`, `debounce()` |
| `@app/lib/modal.js` | `openModal()`, `confirmDialog()` (iç içe açılabilir) |
| `@app/lib/form.js` | şemadan form (`text`, `textarea`, `select`, `richtext`...) |
| `@app/lib/editor.js` | HTML editör (görsel ekleme medya kütüphanesinden) |
| `@app/lib/media-picker.js` | `openMediaPicker({ accept: 'image' })` -> seçilen dosya ya da `null` |
| `@app/lib/pagination.js` | `renderPagination(container, meta, onPage)` |
| `@app/lib/toast.js` | `toast('Kaydedildi')`, `toast('Hata', 'error')` |

```js
import { openMediaPicker } from '@app/lib/media-picker.js';

const media = await openMediaPicker({ accept: 'image' });   // { id, url, thumb_url, path, name, alt, width, height, ... }
if (media) input.value = media.path;
```

Formda kütüphane görseli saklarken `path` alanını kaydet ve sunucuda `App\Models\Media::findByPath()` ile doğrula.

Blog'daki yapı örnek alınabilir:

```
Forms/PostForm.php                 alanların TEK tanımı: doğrulama kuralları + JS'e giden şema
Services/PostService.php           iş mantığı (listele, oluştur, güncelle, sil)
Resources/PostResource.php         API'nin döndüğü biçim
Controllers/Admin/PostApiController.php   ince controller: /admin/api/blog/...
Views/admin/index.php              sadece iskelet + data-* ayarlar
(ortak kit: @app/lib/...)
Assets/admin/posts-api.js          uç adresleri tek yerde
Assets/admin/posts-page.js         sayfa: liste, filtre, sayfalama, modal, kaydet, sil
```

- Admin API route'ları `'middleware' => ['admin']` ile korunur; JS her istekte `X-CSRF-TOKEN` başlığı gönderir.
- Yanıt biçimi: `{ data, meta }` / `{ data, message }`; hatalar `422 { error, errors: {alan: mesaj} }`, `401`, `404`, `419`.
- Doğrulama hataları `$this->validate()` ile otomatik 422 JSON olarak döner.
- Kullanıcı verisini DOM'a `textContent` ile yaz (`h()` yardımcısı bunu yapar); `innerHTML` kullanma.

## 8.1 Bölüm (section) eklemek

Modül, temaların ana sayfasına sürükle-bırak ile eklenebilen bölümler sağlayabilir:
`app/Modules/Fatura/Sections/{ad}/schema.php` + `view.php`. Kimliği `fatura.{ad}` olur ve modül aktifken
Özelleştir'deki "Bölüm ekle" listesinde çıkar. Biçim: docs/themes.md, "Bölümler". Örnekler: Blog (`Sections/latest`),
Formlar (`Sections/form`).

Sayfa ve yazı içeriğine kısa kod eklemek için `content.html` hook'u: içerik yazdırılmadan önce bu filtreden geçer
(Formlar modülü `[form iletisim]`'i böyle forma çevirir).

## 9. Hook'lar (hooks.php)

```php
// Ana sayfaya bölüm
Hook::on('site.home', fn(array $s) => [...$s, ['order' => 30, 'html' => (string) View::render('Fatura::partials/home')]]);

// Admin genel bakış kutusu
Hook::on('admin.dashboard.stats', fn(array $s) => [...$s, ['label' => 'Fatura', 'value' => Fatura::count(), 'url' => 'admin/faturalar', 'order' => 30]]);
```

- `hooks.php` **her istekte** yüklenir: sorguları dosyanın üst seviyesine değil, `Hook::on(...)` içindeki fonksiyona yaz.
- Tüm hook noktaları: `app/Hooks.php` açıklamasında.

## 10. Diğer modüllerle ilişki

- **Core'a ve başka modüllerin dosyalarına dokunma.** Paket sadece kendi klasörünü içerir.
- Başka bir modülü kullanacaksan önce kontrol et:
  ```php
  if (Core\Module::isActive('Blog')) { ... App\Modules\Blog\Models\Post::count() ... }
  ```
- Modüller arası iletişim için hook kullan: `Hook::fire('fatura.paid', $fatura)` — dinleyen modül tepki verir.

## 11. Paketleme ve teslim

```bash
php modrix module:pack Fatura      # dist/Fatura-1.0.0.zip
```

ZIP'e sadece izinli türler girer: `php sql json css js png jpg jpeg gif webp svg ico woff woff2 ttf txt md`.
Gizli dosyalar (`.htaccess`, `.gitkeep`...) paketlenmez ve yüklenmez.

**Teslimden önce kontrol listesi**
- [ ] `module.json` sürümü artırıldı
- [ ] Yeni tablo/kolon için yeni migration + `.down.sql` var; eski migration'lar değişmedi
- [ ] Admin route'larında `admin` middleware var
- [ ] `icon.svg` (ya da png/webp/jpg) var; yoksa listede baş harfli kutu çıkar
- [ ] Ayarlar `settings.php` içinde, anahtarlar modül adıyla başlıyor; menüye sadece içerik sayfası eklendi
- [ ] Temiz bir kurulumda: Admin > Modüller > ZIP yükle → Kur → sayfalar açılıyor
- [ ] Önceki sürüm kuruluyken yeni ZIP → Güncelle çalışıyor, veriler duruyor
- [ ] Pasife al → menü ve sayfalar kayboluyor, site çalışıyor
- [ ] Kaldır → tablolar siliniyor, site çalışıyor

## Bilinen sınırlar


- Lisans/imza kontrolü yok: ZIP'i alan herkes kurabilir.
- Modül bağımlılığı (`requires.modules`) henüz kontrol edilmiyor; `Module::isActive()` ile elle kontrol et.
