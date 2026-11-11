# Modül yazma: adım adım

Bu rehberde sıfırdan bir **Duyuru** modülü yazıyoruz: admin panelinde duyuru ekleyip silme, sitede `/duyurular` sayfası,
ana sayfaya eklenebilen "Son duyurular" bölümü, bir ayar ve genel bakışta sayı kutusu. Bitince ZIP'leyip başka bir siteye
kurabilirsiniz. Başvuru bilgileri: [Modüller](modules.md).

> Rehberdeki kod olduğu gibi çalışır; dosya adları başlıklarda yazıyor.

## 1. İskelet

```bash
php modrix make:module Duyuru
```

`app/Modules/Duyuru/` oluşur. İskeletin dosyalarını aşağıdakilerle değiştireceğiz. Bitince klasör şöyle görünecek:

```
app/Modules/Duyuru/
├── module.json
├── routes.php
├── hooks.php
├── settings.php
├── Migrations/2026_11_12_100000_create_duyurular_table.sql (+ .down.sql)
├── Models/Duyuru.php
├── Controllers/DuyuruController.php
├── Controllers/Admin/DuyuruController.php
├── Views/index.php
├── Views/admin/index.php
└── Sections/son/schema.php, view.php
```

## 2. module.json

Ad, sürüm ve menüler. `menu.admin` panel menüsüne, `menu.site` (menü atanmamış sitelerde) site menüsüne öğe ekler.

**`app/Modules/Duyuru/module.json`**
```json
{
    "name": "Duyuru",
    "version": "1.0.0",
    "description": "Kısa duyurular: panelde ekle, sitede /duyurular sayfasında ve ana sayfa bölümünde göster.",
    "author": "Sizin adınız",
    "requires": { "php": "8.1" },
    "permissions": { "duyuru.manage": "Duyuruları yönet" },
    "menu": {
        "admin": [{ "url": "admin/duyurular", "label": "Duyurular", "icon": "mail", "order": 40, "permission": "duyuru.manage" }],
        "site": [{ "url": "duyurular", "label": "Duyurular", "order": 40 }]
    }
}
```

Panel simgeleri: `home`, `document`, `pages`, `users`, `cog`, `package`, `image`, `chart`, `mail`, `chat`, `palette`.

## 3. Tablo (migration)

Her `.sql` dosyasının yanında, modül kaldırılınca çalışacak bir `.down.sql` olur.

**`app/Modules/Duyuru/Migrations/2026_11_12_100000_create_duyurular_table.sql`**
```sql
CREATE TABLE duyurular (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title      VARCHAR(150) NOT NULL,
    body       TEXT NULL,
    is_pinned  TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    INDEX (is_pinned, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**`app/Modules/Duyuru/Migrations/2026_11_12_100000_create_duyurular_table.down.sql`**
```sql
DROP TABLE IF EXISTS duyurular;
```

## 4. Model

**`app/Modules/Duyuru/Models/Duyuru.php`**
```php
<?php
namespace App\Modules\Duyuru\Models;

use Core\Model;

class Duyuru extends Model
{
    protected static string $table = 'duyurular';
    protected static array $fillable = ['title', 'body', 'is_pinned'];

    // Sabitlenenler önce, sonra en yeniler
    public static function latestList(?int $limit = null): array
    {
        return static::query()->orderByDesc('is_pinned')->latest()->when($limit, fn($q) => $q->limit($limit))->get();
    }
}
```

## 5. Rotalar

**`app/Modules/Duyuru/routes.php`**
```php
<?php
// Duyuru modülü: site /duyurular, admin /admin/duyurular
use App\Modules\Duyuru\Controllers\Admin\DuyuruController as AdminDuyuruController;
use App\Modules\Duyuru\Controllers\DuyuruController;

Router::get('/duyurular', [DuyuruController::class, 'index'])->name('duyuru.index');

Router::group(['prefix' => '/admin/duyurular', 'middleware' => ['admin', 'can:duyuru.manage']], function () {
    Router::get('/', [AdminDuyuruController::class, 'index'])->name('admin.duyuru.index');
    Router::post('/', [AdminDuyuruController::class, 'store'])->name('admin.duyuru.store');
    Router::post('/{id}/delete', [AdminDuyuruController::class, 'destroy'])->name('admin.duyuru.delete');
});
```

Admin rotaları mutlaka **`admin` middleware'i** ile korunur; `can:duyuru.manage` ise sadece bu yetkisi olan rollerin
(Admin > Kullanıcılar > Roller) girmesini sağlar. POST'lar CSRF korumalıdır (formda `View::csrf()`).

## 6. Site: controller ve görünüm

**`app/Modules/Duyuru/Controllers/DuyuruController.php`**
```php
<?php
namespace App\Modules\Duyuru\Controllers;

use App\Modules\Duyuru\Models\Duyuru;
use Core\Controller;
use Core\View;

class DuyuruController extends Controller
{
    public function index(): View
    {
        return $this->view('Duyuru::index', [
            'title' => 'Duyurular',
            'items' => Duyuru::latestList(),
        ]);
    }
}
```

Görünüm temanın ortak sınıflarını (`card`, `badge`, `text-muted-foreground`...) kullanır, böylece her temada düzgün görünür.
Tema isterse `app/Themes/{Tema}/modules/Duyuru/index.php` ile bunu kendi tasarımıyla ezer.

**`app/Modules/Duyuru/Views/index.php`**
```php
<?php // Site: /duyurular. Değişkenler: $title, $items ?>
<div class="mx-auto max-w-3xl">
  <h1 class="text-3xl font-semibold tracking-tight"><?= View::e($title) ?></h1>
  <?php if (!$items): ?>
    <p class="mt-6 text-muted-foreground">Henüz duyuru yok.</p>
  <?php endif ?>
  <div class="mt-8 space-y-4">
    <?php foreach ($items as $item): ?>
      <article class="card p-5">
        <p class="text-xs text-muted-foreground">
          <?= View::date($item['created_at']) ?>
          <?php if ($item['is_pinned']): ?><span class="badge ml-2">Sabit</span><?php endif ?>
        </p>
        <h2 class="mt-1 text-lg font-semibold"><?= View::e($item['title']) ?></h2>
        <?php if ($item['body']): ?><p class="mt-2 leading-7 text-muted-foreground"><?= nl2br(View::e($item['body'])) ?></p><?php endif ?>
      </article>
    <?php endforeach ?>
  </div>
</div>
```

## 7. Admin: controller ve sayfa

Basit bir panel sayfası: üstte ekleme formu, altta liste. (Daha gelişmiş, sayfa yenilemeden çalışan sayfalar için Blog
modülündeki API + JS örneğine bakın.)

**`app/Modules/Duyuru/Controllers/Admin/DuyuruController.php`**
```php
<?php
namespace App\Modules\Duyuru\Controllers\Admin;

use App\Controllers\Admin\AdminController;
use App\Modules\Duyuru\Models\Duyuru;
use Core\View;

class DuyuruController extends AdminController
{
    public function index(): View
    {
        return $this->view('Duyuru::admin/index', ['title' => 'Duyurular', 'items' => Duyuru::latestList()]);
    }

    public function store(): void
    {
        $data = $this->validate(
            ['title' => 'required|max:150', 'body' => 'nullable|max:2000'],
            ['title' => 'Başlık', 'body' => 'Metin']
        );
        $data['is_pinned'] = Request::post('is_pinned') ? 1 : 0;
        Duyuru::create($data);
        Hook::fire('duyuru.created', $data);
        $this->redirect('admin/duyurular', 'Duyuru eklendi.');
    }

    public function destroy(string $id): void
    {
        Duyuru::find((int) $id) ?? $this->abort(404);
        Duyuru::delete((int) $id);
        $this->back('Duyuru silindi.');
    }
}
```

`validate()` hata bulursa forma geri döner; `partials/field` hataları ve eski değerleri kendisi gösterir.

**`app/Modules/Duyuru/Views/admin/index.php`**
```php
<?php // Admin > Duyurular. Değişkenler: $items ?>
<div class="mb-6">
  <h1 class="text-2xl font-semibold tracking-tight">Duyurular</h1>
  <p class="mt-1 text-sm text-muted-foreground">Sitede /duyurular sayfasında ve "Son duyurular" bölümünde görünür.</p>
</div>

<form method="post" action="<?= Router::url('admin.duyuru.store') ?>" class="card mb-6 space-y-4 p-5">
  <?= View::csrf() ?>
  <?= View::render('partials/field', ['name' => 'title', 'label' => 'Başlık', 'required' => true]) ?>
  <?= View::render('partials/field', ['name' => 'body', 'label' => 'Metin', 'type' => 'textarea', 'rows' => 3]) ?>
  <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_pinned" value="1" class="size-4"> Üstte sabitle</label>
  <button class="btn btn-primary">Ekle</button>
</form>

<div class="card divide-y">
  <?php foreach ($items as $item): ?>
    <div class="flex items-center justify-between gap-4 p-4">
      <div class="min-w-0">
        <p class="truncate font-medium"><?= View::e($item['title']) ?><?= $item['is_pinned'] ? ' <span class="badge badge-default">Sabit</span>' : '' ?></p>
        <p class="text-xs text-muted-foreground"><?= View::date($item['created_at'], true) ?></p>
      </div>
      <form method="post" action="<?= Router::url('admin.duyuru.delete', ['id' => $item['id']]) ?>" data-confirm="Duyuru silinsin mi?">
        <?= View::csrf() ?><button class="btn btn-danger-ghost btn-sm">Sil</button>
      </form>
    </div>
  <?php endforeach ?>
  <?php if (!$items): ?><p class="p-6 text-center text-sm text-muted-foreground">Henüz duyuru yok.</p><?php endif ?>
</div>
```

`data-confirm` olan formlar gönderilmeden önce onay sorar (layout'ta hazır).

## 8. Ayar

`settings.php` olunca Admin > Modüller'de modülün yanında **Ayarlar** düğmesi çıkar. Anahtarlar modül adıyla başlar.

**`app/Modules/Duyuru/settings.php`**
```php
<?php
return [
    'description' => 'Duyuruların görünümü.',
    'fields'      => [
        'duyuru_show_date' => ['label' => 'Tarihi göster', 'type' => 'checkbox', 'default' => '1'],
    ],
];
```

Okuma her yerde: `Setting::bool('duyuru_show_date')`. (Görünümlerde tarihi bu ayara bağlamak size alıştırma.)

## 9. Hook: genel bakışta sayı kutusu

**`app/Modules/Duyuru/hooks.php`**
```php
<?php
// Duyuru modülü (aktifken her istekte yüklenir; sorgular fonksiyonların içinde)
use App\Modules\Duyuru\Models\Duyuru;

Hook::on('admin.dashboard.stats', fn(array $stats) => [...$stats,
    ['label' => 'Duyuru', 'value' => Duyuru::count(), 'url' => 'admin/duyurular', 'order' => 40],
]);
```

Tüm hook noktaları: [Hook'lar](hooks.md).

## 10. Ana sayfa bölümü

Modülün `Sections/` klasöründeki bölümler, modül aktifken **her temanın** Özelleştir > Ana sayfa bölümleri > Bölüm ekle
listesinde çıkar. Kimliği `duyuru.son` olur.

```bash
php modrix make:section son --module=Duyuru
```

**`app/Modules/Duyuru/Sections/son/schema.php`**
```php
<?php
return [
    'name'        => 'Son duyurular',
    'description' => 'Duyuru modülündeki son duyurular.',
    'icon'        => 'megaphone',
    'requires'    => 'Duyuru',
    'fields'      => [
        'title' => ['label' => 'Başlık', 'type' => 'text', 'default' => 'Duyurular'],
        'count' => ['label' => 'Kaç duyuru', 'type' => 'select', 'default' => '3', 'options' => ['3' => '3', '5' => '5', '10' => '10']],
    ],
];
```

**`app/Modules/Duyuru/Sections/son/view.php`**
```php
<?php
// Son duyurular bölümü. $s: title, count. $edit('alan'): önizlemede tıklayınca paneldeki alan açılır.
use App\Modules\Duyuru\Models\Duyuru;

$items = Duyuru::latestList((int) $s['count']);
if (!$items) return;
?>
<section class="py-14">
  <div class="wrap">
    <h2 class="text-2xl font-semibold tracking-tight" <?= $edit('title') ?>><?= View::e($s['title']) ?></h2>
    <ul class="mt-6 divide-y rounded-lg border">
      <?php foreach ($items as $item): ?>
        <li class="flex items-center justify-between gap-4 px-4 py-3">
          <span class="font-medium"><?= View::e($item['title']) ?></span>
          <span class="shrink-0 text-xs text-muted-foreground"><?= View::date($item['created_at']) ?></span>
        </li>
      <?php endforeach ?>
    </ul>
    <a href="<?= Router::url('duyuru.index') ?>" class="mt-4 inline-block text-sm font-medium hover:underline">Tüm duyurular →</a>
  </div>
</section>
```

Alan türleri ve ayrıntılar: [Bölümler](sections.md).

## 11. Kur ve dene

İskeletin dosyalarını yukarıdakilerle değiştirdiyseniz kurun:

```bash
php modrix module:install Duyuru
```

- `https://siteniz/admin/duyurular` → duyuru ekleyin
- `https://siteniz/duyurular` → sitede görün
- Görünüm > Özelleştir > Ana sayfa bölümleri > Bölüm ekle > **Son duyurular**
- Genel bakışta "Duyuru" kutusu

## 12. Yeni sürüm

Değişiklik yaptınız: `module.json` → `"version": "1.1.0"`; tabloya kolon gerekiyorsa **yeni** bir migration
(`ALTER TABLE duyurular ADD ...`) + `.down.sql`. Sonra:

```bash
php modrix module:update Duyuru
```

## 13. Paketle ve teslim et

```bash
php modrix module:pack Duyuru        # dist/Duyuru-1.0.0.zip
```

ZIP'i alan kişi Admin > Modüller > **Yükle** ile kurar; daha yeni sürümü yüklerse güncellenir. Teslimden önce
[Modüller](modules.md) rehberinin sonundaki kontrol listesine ve [Güvenlik](security.md) kurallarına bakın.
