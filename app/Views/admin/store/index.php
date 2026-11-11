<?php // Admin > Mağaza. Değişkenler: $type, $catalog, $items, $q, $category, $categories, $updates
use App\Services\Store;
$themes = $type === 'themes';
?>
<div class="mb-4 flex flex-wrap items-start justify-between gap-4">
  <div>
    <h1 class="text-2xl font-semibold tracking-tight">Mağaza</h1>
    <p class="mt-1 text-sm text-muted-foreground"><?= $themes ? 'Yeni temaları kurun; Görünüm\'den önizleyip etkinleştirin.' : 'Yeni modülleri tek tıkla kurun, kurulu olanları güncelleyin.' ?><?= $updates ? " <strong class=\"text-foreground\">$updates güncelleme var.</strong>" : '' ?></p>
  </div>
  <a href="?type=<?= $type ?>&refresh=1" class="btn btn-secondary btn-sm">Kataloğu yenile</a>
</div>
<nav class="mb-5 flex flex-wrap gap-1 border-b">
  <?php foreach (Store::TYPES as $key => $label): ?>
    <a href="?type=<?= $key ?>" class="-mb-px border-b-2 px-3 py-2 text-sm font-medium <?= $type === $key ? 'border-primary text-foreground' : 'border-transparent text-muted-foreground hover:text-foreground' ?>"><?= $label ?></a>
  <?php endforeach ?>
</nav>

<?php if (!empty($catalog['error'])): ?>
  <div class="alert alert-warning">Katalog alınamadı: <?= View::e($catalog['error']) ?><?= $catalog['items'] ? ' (son alınan liste gösteriliyor)' : '' ?></div>
<?php endif ?>

<form class="mb-5 flex flex-wrap gap-2">
  <input type="hidden" name="type" value="<?= $type ?>">
  <input type="search" name="q" value="<?= View::e($q) ?>" placeholder="<?= $themes ? 'Tema' : 'Modül' ?> ara…" class="input mt-0 max-w-xs">
  <?php if (count($categories) > 1): ?>
    <select name="category" class="input mt-0 w-auto" onchange="this.form.submit()"><option value="">Tüm kategoriler</option>
      <?php foreach ($categories as $c): ?><option <?= $category === $c ? 'selected' : '' ?>><?= View::e($c) ?></option><?php endforeach ?></select>
  <?php endif ?>
</form>

<?php if (!$items): ?>
  <p class="card px-6 py-14 text-center text-muted-foreground"><?= $catalog['items'] ? 'Aramaya uyan sonuç yok.' : 'Mağazada henüz ' . ($themes ? 'tema' : 'modül') . ' yok.' ?></p>
<?php endif ?>
<div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
  <?php foreach ($items as $m): [$state, $extra] = Store::status($m, $type); $paid = $m['price'] > 0; ?>
    <article class="card flex flex-col overflow-hidden">
      <?php if ($themes): ?>
        <div class="aspect-[16/10] border-b bg-muted"><?php if ($m['screenshot']): ?><img src="<?= View::e($m['screenshot']) ?>" alt="" class="size-full object-cover" loading="lazy"><?php endif ?></div>
      <?php endif ?>
      <div class="flex flex-1 flex-col p-5">
        <div class="flex items-start gap-3">
          <?php if (!$themes): ?>
            <div class="size-12 shrink-0 overflow-hidden rounded-xl border bg-muted">
              <?php if ($m['icon']): ?><img src="<?= View::e($m['icon']) ?>" alt="" class="size-full object-cover" loading="lazy">
              <?php else: ?><span class="flex size-full items-center justify-center text-lg font-semibold text-muted-foreground"><?= View::e(mb_substr($m['name'], 0, 1)) ?></span><?php endif ?>
            </div>
          <?php endif ?>
          <div class="min-w-0 flex-1">
            <h2 class="font-semibold"><?= View::e($m['name']) ?></h2>
            <p class="text-xs text-muted-foreground">v<?= View::e($m['version']) ?><?= $m['author'] ? ' · ' . View::e($m['author']) : '' ?> · <?= View::e($m['category']) ?></p>
          </div>
          <span class="shrink-0 text-sm font-semibold"><?= $paid ? number_format($m['price'], 0, ',', '.') . ' ₺' : 'Ücretsiz' ?></span>
        </div>
        <p class="mt-3 flex-1 text-sm leading-6 text-muted-foreground"><?= View::e($m['description']) ?></p>
        <div class="mt-4 border-t pt-4">
          <?php if ($state === 'installed'): ?>
            <p class="flex items-center justify-between text-sm"><span class="badge badge-green">Yüklü · v<?= View::e($extra) ?></span>
              <a href="<?= Request::url($themes ? 'admin/appearance' : 'admin/modules') ?>" class="text-muted-foreground hover:underline"><?= $themes ? 'Görünüm' : 'Modüller' ?></a></p>
          <?php elseif ($state === 'incompatible'): ?>
            <p class="text-sm text-amber-700"><?= View::e($extra) ?></p>
          <?php elseif ($paid && !Store::license($m['slug'])): ?>
            <form method="post" action="<?= Request::url("admin/store/{$m['slug']}/license") ?>" class="flex gap-2"><?= View::csrf() ?><input type="hidden" name="type" value="<?= $type ?>">
              <input name="license" class="input mt-0 h-8 text-xs" placeholder="Lisans anahtarı" required><button class="btn btn-secondary btn-sm">Kaydet</button></form>
            <?php if ($m['buy_url']): ?><a href="<?= View::e($m['buy_url']) ?>" target="_blank" rel="noopener" class="btn btn-primary btn-sm mt-2 w-full">Satın al</a><?php endif ?>
          <?php else: ?>
            <form method="post" action="<?= Request::url("admin/store/{$m['slug']}/install") ?>" onsubmit="this.querySelector('button').disabled = true; this.querySelector('button').textContent = 'Kuruluyor…'"><?= View::csrf() ?><input type="hidden" name="type" value="<?= $type ?>">
              <button class="btn btn-primary btn-sm w-full"><?= $state === 'update' ? 'v' . View::e($extra) . ' → v' . View::e($m['version']) . ' güncelle' : 'Kur' ?></button></form>
          <?php endif ?>
        </div>
      </div>
    </article>
  <?php endforeach ?>
</div>
<p class="mt-6 text-xs text-muted-foreground">Kaynak: <?= View::e($catalog['source'] ?? Store::source($type)) ?> · Son yenileme: <?= !empty($catalog['checked_at']) ? date('d.m.Y H:i', $catalog['checked_at']) : '—' ?>. Paketler SHA-256 ile doğrulanır; PHP kodu sunucunuzda çalışır, yalnız güvendiğiniz mağazayı kullanın.</p>
