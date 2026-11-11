<?php
// Modül ayar sayfası: alanlar app/Modules/{Ad}/settings.php içinde.
// Düzen Admin > Ayarlar ile aynı: solda modül bilgisi, sağda form. Değişkenler: $folder, $module, $group, $values
$states = [
    'active'   => ['Aktif', 'badge-green'],
    'inactive' => ['Pasif', 'badge-yellow'],
];
[$stateLabel, $stateClass] = $states[$module['state']] ?? ['?', 'badge-gray'];
?>
<div class="mb-8">
  <nav class="flex items-center gap-1.5 text-sm text-muted-foreground">
    <a href="<?= Request::url('admin/modules') ?>" class="hover:text-foreground">Modüller</a>
    <span>/</span>
    <span class="text-foreground"><?= View::e($module['name']) ?></span>
  </nav>
  <div class="mt-4 flex items-center gap-4">
    <?= View::render('admin/modules/icon', ['module' => $module, 'size' => 'size-14']) ?>
    <div class="min-w-0">
      <h1 class="text-2xl font-semibold tracking-tight"><?= View::e($module['name']) ?> ayarları</h1>
      <?php if ($module['description']): ?><p class="mt-1 text-sm text-muted-foreground"><?= View::e($module['description']) ?></p><?php endif ?>
    </div>
  </div>
</div>

<div class="flex flex-col gap-8 lg:flex-row">
  <!-- Modül bilgisi -->
  <aside class="lg:w-52 lg:shrink-0">
    <dl class="card divide-y text-sm">
      <div class="flex items-center justify-between gap-3 px-4 py-3">
        <dt class="text-muted-foreground">Durum</dt>
        <dd><span class="badge <?= $stateClass ?>"><?= $stateLabel ?></span></dd>
      </div>
      <div class="flex items-center justify-between gap-3 px-4 py-3">
        <dt class="text-muted-foreground">Sürüm</dt>
        <dd class="font-mono text-xs">v<?= View::e($module['installed_version'] ?? $module['version']) ?></dd>
      </div>
      <?php if ($module['author']): ?>
        <div class="flex items-center justify-between gap-3 px-4 py-3">
          <dt class="text-muted-foreground">Geliştirici</dt>
          <dd class="truncate"><?= View::e($module['author']) ?></dd>
        </div>
      <?php endif ?>
    </dl>
    <?php if ($module['state'] !== 'active'): ?>
      <p class="mt-3 text-xs leading-5 text-muted-foreground">Modül pasif: ayarlar kaydedilir ama aktif edilene kadar sitede etkisi olmaz.</p>
    <?php endif ?>
    <a href="<?= Request::url('admin/modules') ?>" class="btn btn-ghost btn-sm mt-3 w-full">&larr; Modüllere dön</a>
  </aside>

  <div class="min-w-0 flex-1">
    <?= View::render('admin/settings/form', [
        'group'  => ['label' => 'Ayarlar'] + $group,
        'values' => $values,
        'action' => Router::url('admin.modules.settings', ['name' => $folder]),
    ]) ?>
  </div>
</div>
