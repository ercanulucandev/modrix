<?php // Admin > Diller. Değişkenler: $languages, $counts
$default = Lang::default();
$known = array_diff_key(Lang::KNOWN, $languages);
?>
<div class="mb-4">
  <h1 class="text-2xl font-semibold tracking-tight">Diller</h1>
  <p class="mt-1 text-sm text-muted-foreground">Varsayılan dilin adresleri öneksizdir (/hakkimizda); diğer diller kodla başlar (/en/about).</p>
</div>
<?= View::render('admin/languages/tabs', ['current' => 'languages']) ?>

<div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
  <div class="card divide-y">
    <?php foreach ($languages as $code => $l): ?>
      <div class="flex flex-wrap items-center gap-3 px-4 py-3">
        <span class="flex size-9 items-center justify-center rounded-md bg-muted font-mono text-sm font-semibold uppercase"><?= View::e($code) ?></span>
        <div class="min-w-0 flex-1">
          <p class="font-medium"><?= View::e($l['name']) ?> <?php if ($code === $default): ?><span class="badge badge-default ml-1">Varsayılan</span><?php endif ?></p>
          <p class="text-xs text-muted-foreground">Adres: <?= View::e(Lang::url('', $code)) ?><?= !empty($counts[$code]) ? ' · ' . View::e(implode(', ', $counts[$code])) : '' ?></p>
        </div>
        <?php if ($code !== $default): ?>
          <form method="post" action="<?= Request::url("admin/languages/$code/default") ?>" onsubmit="return confirm('Varsayılan dil değişsin mi? Adresler değişir (önek bu dilden kalkar).')"><?= View::csrf() ?><button class="btn btn-ghost btn-sm">Varsayılan yap</button></form>
          <form method="post" action="<?= Request::url("admin/languages/$code/delete") ?>" onsubmit="return confirm('Dil kaldırılsın mı? İçerikler silinmez.')"><?= View::csrf() ?><button class="btn btn-ghost btn-sm text-destructive">Kaldır</button></form>
        <?php endif ?>
      </div>
    <?php endforeach ?>
  </div>

  <form method="post" action="<?= Request::url('admin/languages') ?>" class="card space-y-4 p-5">
    <?= View::csrf() ?>
    <h2 class="font-semibold">Dil ekle</h2>
    <div>
      <label for="lang-code" class="label">Dil</label>
      <select id="lang-code" name="code" class="input" onchange="this.form.name.value = this.selectedOptions[0].dataset.name || ''">
        <?php foreach ($known as $code => $name): ?><option value="<?= $code ?>" data-name="<?= View::e($name) ?>"><?= View::e($name) ?> (<?= $code ?>)</option><?php endforeach ?>
      </select>
      <?php if ($e = View::error('code')): ?><p class="field-error"><?= View::e($e) ?></p><?php endif ?>
    </div>
    <?= View::render('partials/field', ['name' => 'name', 'label' => 'Görünen ad', 'value' => View::old('name', reset($known) ?: ''), 'help' => 'Dil seçicide görünür.']) ?>
    <button class="btn btn-primary w-full">Ekle</button>
    <p class="text-xs text-muted-foreground">Sonra: Sayfalar ve Yazılar'da dil sekmesinden çevirileri oluşturun; Ayarlar'daki metinlerin altında her dil için kutu çıkar; Görünüm > Menüler'de dile göre menü seçin.</p>
  </form>
</div>
