# Görünümler ve arayüz

## View

```php
View::render('admin/dashboard')                 // app/Views/admin/dashboard.php
View::render('Siparis::index', $veri)           // app/Modules/Siparis/Views/index.php
View::render('home')->setLayout('site')         // layout'a sarılı (controller'da $this->view() bunu yapar)
View::render('partials/pagination', ['page' => $sonuc])   // görünüm içinde parça (layout'suz)
```

**Sitede** görünümler önce etkin temada, sonra başlangıç temasında (`Baslangic`), en son yukarıdaki yerlerde aranır. Böylece bir tema
`Siparis::index`'i `app/Themes/{Tema}/modules/Siparis/index.php` ile ezebilir. Yönetim paneli temadan etkilenmez.
Ayrıntı: [Temalar](themes.md#ezme-override-kuralı).

Görünüm içindeki yardımcılar:

| | |
|---|---|
| `View::e($x)` | Güvenli yazdırma (**her kullanıcı verisinde**) |
| `View::csrf()` | Formların içine CSRF alanı |
| `View::old('alan', $varsayilan)` | Hatalı form sonrası eski değer |
| `View::error('alan')` | Alanın doğrulama hatası |
| `View::date($tarih, true)` | `10.11.2026 14:30` |
| `View::money(1234.5)` | `1.234,50 ₺` |
| `View::asset('admin/x.js')` | `app/Assets/` dosyasının sürümlü adresi |
| `Module::asset('Siparis', 'admin/x.js')` | Modül dosyasının adresi (`app/Modules/Siparis/Assets/...`) |
| `Theme::asset('css/x.css')` | Tema dosyasının adresi |
| `Html::render($html)` | Editör içeriği (temizlenmiş HTML) |
| `Html::text($html, 160)` | Etiketsiz özet |
| `Icon::svg('rocket', 'size-5')` | Ortak simge seti (adlar: `core/Icon.php`) |

Hazır parçalar: `partials/flash` (mesajlar), `partials/pagination`, `partials/field` (form alanı), `partials/nav` (Başlangıç
temadan, alt menülü menü).

## Ortak arayüz sınıfları

Site temaları ve yönetim paneli aynı küçük kiti kullanır (Tailwind v4 + shadcn/ui tarzı köşeli bileşenler).
Sitede `partials/ui-styles` ile gelir; renkleri tema `@theme` ile verir.

| Sınıf | |
|---|---|
| `wrap` | sayfa kabı (ortalanmış, kenar boşluklu) |
| `btn` + `btn-primary` / `btn-secondary` / `btn-ghost` / `btn-danger` / `btn-danger-ghost`, `btn-sm`, `btn-lg` | düğmeler |
| `input`, `label`, `field-error`, `field-help`, `input-error` | form |
| `card` (+ panelde `card-header`, `card-title`, `card-description`, `card-footer`) | kart |
| `badge` (+ panelde `badge-green`, `badge-yellow`, `badge-gray`, `badge-default`) | etiket |
| `alert alert-ok` / `alert-error` / `alert-info` / `alert-warning` | uyarı kutusu |
| `table` (panel) | tablo |
| `prose-content` | editör içeriği (başlık, liste, tablo, görsel biçimleri) |

Renk adları: `bg-primary text-primary-foreground`, `text-muted-foreground`, `bg-muted`, `border` ... (temanın `@theme`'inden).

## Yönetim paneli JS kiti (`@app/lib/...`)

Admin sayfalarında `import` ile kullanılır (import map hazır). Örnek: Blog yazıları, Sayfalar, Medya, Formlar.

```js
import { apiClient, config, url } from '@app/lib/app.js';       // CSRF + oturum düşünce girişe yönlendirme hazır
const api = apiClient('admin/api/siparis');
const { data, meta } = await api.get('/orders', { q: 'ali', page: 2 });
await api.post('/orders', { name: 'A' });   await api.put('/orders/5', {...});   await api.delete('/orders/5');
// Hata: err.status, err.message, err.errors (422'de alan hataları), err.isValidation

import { h, icon, formatDate, debounce } from '@app/lib/dom.js';     // h('div', { class: 'card', onClick }, [çocuklar])
import { openModal, confirmDialog } from '@app/lib/modal.js';         // iç içe açılabilen modallar
import { createForm } from '@app/lib/form.js';                         // sunucu şemasından form (Blog: PostForm::schema())
import { createEditor } from '@app/lib/editor.js';                     // HTML editör (Quill), medya kütüphanesinden görsel
import { openMediaPicker } from '@app/lib/media-picker.js';           // const media = await openMediaPicker({ accept: 'image' })
import { renderPagination } from '@app/lib/pagination.js';
import { toast } from '@app/lib/toast.js';                             // toast('Kaydedildi'); toast('Hata', 'error')
```

Sayfa JS'i modülün `Assets/admin/` klasörüne konur (sadece giriş yapmış admin'e sunulur) ve görünümde:

```php
<script type="module" src="<?= View::e(Module::asset('Siparis', 'admin/orders-page.js')) ?>"></script>
```

Sitede çalışacak JS/CSS `Assets/` içinde **`admin/` dışında** bir klasöre konur.
