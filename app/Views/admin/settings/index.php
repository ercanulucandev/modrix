<?php
// Sitenin genel ayarları. Modül ayarları: Admin > Modüller > Ayarlar (admin/modules/settings.php)
?>
<div class="mb-8">
  <h1 class="text-2xl font-semibold tracking-tight">Ayarlar</h1>
  <p class="mt-1 text-sm text-muted-foreground">Sitenin her detayını buradan yönetin.</p>
</div>

<div class="flex flex-col gap-8 lg:flex-row">
  <!-- Sekmeler -->
  <nav class="flex flex-wrap gap-1 lg:w-52 lg:shrink-0 lg:flex-col lg:flex-nowrap">
    <?php foreach ($groups as $key => $g): ?>
      <a href="<?= Request::url('admin/settings?tab=' . $key) ?>"
         class="whitespace-nowrap rounded-md px-3 py-2 text-sm <?= $key === $tab ? 'bg-accent font-medium text-accent-foreground' : 'text-muted-foreground hover:bg-accent/60 hover:text-foreground' ?>">
        <?= View::e($g['label']) ?>
      </a>
    <?php endforeach ?>
  </nav>

  <div class="min-w-0 flex-1">
    <?= View::render('admin/settings/form', [
        'group'  => $groups[$tab],
        'values' => $values,
        'action' => Router::url('admin.settings.update', ['group' => $tab]),
    ]) ?>

    <?php if ($tab === 'performance'): $cache = App\Services\PageCache::stats(); ?>
      <form method="post" action="<?= Router::url('admin.settings.cacheClear') ?>" class="card mt-4 flex flex-wrap items-center justify-between gap-3 p-5">
        <?= View::csrf() ?>
        <p class="text-sm"><span class="font-medium">Önbellekte <?= $cache['count'] ?> sayfa</span>
          <span class="text-muted-foreground">· <?= round($cache['size'] / 1024) ?> KB</span></p>
        <button class="btn btn-secondary">Önbelleği temizle</button>
      </form>
    <?php endif ?>

    <?php if ($tab === 'mail'): ?>
      <form method="post" action="<?= Router::url('admin.settings.mailTest') ?>" class="card mt-4 flex flex-col gap-3 p-5 sm:flex-row sm:items-end">
        <?= View::csrf() ?>
        <div class="flex-1">
          <label class="label" for="test-to">Deneme e-postası</label>
          <input id="test-to" type="email" name="to" required class="input" value="<?= View::e(App\Models\User::admin()['email'] ?? '') ?>">
          <p class="field-help text-xs">Önce ayarları kaydedin. Hata olursa sebebi burada gösterilir.</p>
        </div>
        <button class="btn btn-secondary sm:mb-[1.6rem]">Gönder</button>
      </form>
    <?php endif ?>
  </div>
</div>
