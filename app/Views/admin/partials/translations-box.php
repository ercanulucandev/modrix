<?php
// Düzenleyicide çeviriler kutusu. Değişkenler: $lang (bu içeriğin dili), $siblings (['en' => satır]), $edit ('admin/pages/{id}/edit'), $translate ('admin/pages/{id}/translate/{lang}')
if (!Lang::enabled()) return;
?>
<section class="card space-y-3 p-4 text-sm">
  <p class="flex items-center justify-between"><span class="font-medium">Dil</span><span class="badge badge-default"><?= View::e(Lang::name($lang)) ?></span></p>
  <?php if (!empty($id)): ?>
    <div class="space-y-2 border-t pt-3">
      <p class="text-xs text-muted-foreground">Çeviriler</p>
      <?php foreach (Lang::all() as $code => $l): if ($code === $lang) continue; $s = $siblings[$code] ?? null; ?>
        <div class="flex items-center justify-between gap-2">
          <span><?= View::e($l['name']) ?></span>
          <?php if ($s): ?>
            <a href="<?= Request::url(str_replace('{id}', (string) $s['id'], $edit)) ?>" class="btn btn-ghost btn-sm">Düzenle<?= $s['status'] !== 'published' ? ' (taslak)' : '' ?></a>
          <?php else: ?>
            <form method="post" action="<?= Request::url(str_replace(['{id}', '{lang}'], [(string) $id, $code], $translate)) ?>"><?= View::csrf() ?><button class="btn btn-secondary btn-sm">Çeviri oluştur</button></form>
          <?php endif ?>
        </div>
      <?php endforeach ?>
      <p class="text-xs text-muted-foreground">"Çeviri oluştur" bu içeriğin taslak kopyasını o dilde açar; metni çevirip yayınlayın.</p>
    </div>
  <?php endif ?>
</section>
