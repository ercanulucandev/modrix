<?php
// Görünüm > Temalar. Değişkenler: $themes (Theme::all()), $preview (önizlenen tema ya da null)
$form = fn(string $route, string $name, string $label, string $class) =>
    '<form method="post" action="' . Router::url($route, ['name' => $name]) . '">' . View::csrf()
    . '<button class="btn btn-sm ' . $class . '">' . $label . '</button></form>';
?>
<?= View::render('admin/appearance/tabs', ['current' => 'themes']) ?>

<?php if ($preview): ?>
  <div class="alert alert-info flex flex-wrap items-center justify-between gap-3">
    <span><strong><?= View::e($themes[$preview]['name'] ?? $preview) ?></strong> temasını önizliyorsunuz. Ziyaretçiler etkin temayı görmeye devam ediyor.</span>
    <form method="post" action="<?= Router::url('admin.appearance.preview.stop') ?>"><?= View::csrf() ?><button class="btn btn-secondary btn-sm">Önizlemeyi kapat</button></form>
  </div>
<?php endif ?>

<div class="grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
  <?php foreach ($themes as $folder => $t): $shot = Theme::screenshotFile($folder); ?>
    <div class="card flex flex-col overflow-hidden <?= $t['active'] ? 'ring-2 ring-primary' : '' ?>">
      <div class="relative aspect-[4/3] border-b bg-muted">
        <?php if ($shot): ?>
          <img src="<?= Router::url('admin.appearance.screenshot', ['name' => $folder]) ?>?v=<?= Module::assetVersion($shot) ?>" alt="<?= View::e($t['name']) ?>" class="size-full object-cover object-top" loading="lazy">
        <?php else: ?>
          <div class="flex size-full items-center justify-center text-sm text-muted-foreground">Ekran görüntüsü yok</div>
        <?php endif ?>
        <?php if ($t['active']): ?><span class="badge badge-default absolute left-3 top-3 shadow-sm">Etkin</span><?php endif ?>
        <?php if ($preview === $folder): ?><span class="badge badge-yellow absolute left-3 top-3 shadow-sm">Önizleniyor</span><?php endif ?>
      </div>

      <div class="flex flex-1 flex-col p-5">
        <div class="flex items-baseline justify-between gap-3">
          <h2 class="font-semibold"><?= View::e($t['name']) ?></h2>
          <span class="font-mono text-xs text-muted-foreground">v<?= View::e($t['version']) ?></span>
        </div>
        <?php if ($t['description']): ?><p class="mt-1.5 text-sm leading-6 text-muted-foreground"><?= View::e($t['description']) ?></p><?php endif ?>
        <p class="mt-2 font-mono text-xs text-muted-foreground">app/Themes/<?= View::e($folder) ?><?= $t['author'] ? ' · ' . View::e($t['author']) : '' ?></p>
        <?php if ($t['error']): ?><p class="field-error">Hata: <?= View::e($t['error']) ?></p><?php endif ?>

        <div class="mt-auto flex flex-wrap gap-2 pt-5">
          <?php if ($t['active']): ?>
            <a href="<?= Request::url('admin/appearance/customize') ?>" class="btn btn-primary btn-sm">Özelleştir</a>
            <a href="<?= Request::url('admin/appearance/menus') ?>" class="btn btn-secondary btn-sm">Menüler</a>
          <?php elseif (!$t['error']): ?>
            <?= $form('admin.appearance.activate', $folder, 'Etkinleştir', 'btn-primary') ?>
            <a href="<?= Request::url('admin/appearance/customize?theme=' . $folder) ?>" class="btn btn-secondary btn-sm">Özelleştir ve önizle</a>
            <?= $form('admin.appearance.preview', $folder, 'Sitede gez', 'btn-ghost') ?>
          <?php endif ?>
        </div>
      </div>
    </div>
  <?php endforeach ?>
</div>

<p class="mt-8 text-sm text-muted-foreground">
  Yeni tema: <code class="rounded-sm bg-muted px-1 py-0.5 font-mono text-xs text-foreground">app/Themes/</code> klasörüne bir tema klasörü ekleyin.
  Tema sadece değiştirmek istediği dosyaları içerir, eksikler Varsayılan temadan gelir.
</p>
