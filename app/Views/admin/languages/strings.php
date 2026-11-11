<?php // Arayüz metinleri. Değişkenler: $lang, $targets, $strings, $existing, $q, $done, $total ?>
<div class="mb-4">
  <h1 class="text-2xl font-semibold tracking-tight">Diller</h1>
  <p class="mt-1 text-sm text-muted-foreground">Temalarda ve modüllerde yazılı düğme, başlık gibi metinler. Boş bırakılan Türkçe görünür.</p>
</div>
<?= View::render('admin/languages/tabs', ['current' => 'strings']) ?>

<?php if (!$targets): ?>
  <p class="card px-6 py-14 text-center text-muted-foreground">Önce Diller sekmesinden bir dil ekleyin.</p>
<?php else: ?>
  <form class="mb-4 flex flex-wrap items-center gap-2">
    <select name="lang" class="input mt-0 w-auto" onchange="this.form.submit()">
      <?php foreach ($targets as $code): ?><option value="<?= $code ?>" <?= $code === $lang ? 'selected' : '' ?>><?= View::e(Lang::name($code)) ?></option><?php endforeach ?>
    </select>
    <input type="search" name="q" value="<?= View::e($q) ?>" placeholder="Metinlerde ara…" class="input mt-0 max-w-xs">
    <span class="text-sm text-muted-foreground"><?= $done ?> / <?= $total ?> çevrildi</span>
  </form>
  <form method="post" action="<?= Request::url('admin/languages/strings') ?>" class="card">
    <?= View::csrf() ?>
    <input type="hidden" name="lang" value="<?= View::e($lang) ?>"><input type="hidden" name="q" value="<?= View::e($q) ?>">
    <div class="divide-y">
      <?php foreach ($strings as $i => $s): ?>
        <div class="grid gap-2 px-4 py-3 md:grid-cols-2 md:items-center">
          <p class="text-sm"><?= View::e($s) ?></p>
          <input type="hidden" name="s[<?= $i ?>]" value="<?= View::e($s) ?>">
          <input name="t[<?= $i ?>]" value="<?= View::e($existing[$s] ?? '') ?>" class="input mt-0" placeholder="<?= View::e($s) ?>" dir="<?= Lang::rtl($lang) ? 'rtl' : 'ltr' ?>">
        </div>
      <?php endforeach ?>
      <?php if (!$strings): ?><p class="px-4 py-10 text-center text-sm text-muted-foreground">Metin bulunamadı.</p><?php endif ?>
    </div>
    <div class="card-footer justify-end"><button class="btn btn-primary">Kaydet</button></div>
  </form>
<?php endif ?>
