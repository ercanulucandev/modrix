<?php
// Görünüm sayfalarının üst kısmı: başlık + sekmeler. Değişkenler: $current ('themes' | 'customize' | 'menus'), $actions (sağdaki HTML, isteğe bağlı)
$tabs = [
    'themes'    => ['Temalar', 'admin/appearance'],
    'customize' => ['Özelleştir', 'admin/appearance/customize'],
    'menus'     => ['Menüler', 'admin/appearance/menus'],
];
?>
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
  <div>
    <h1 class="text-2xl font-semibold tracking-tight">Görünüm</h1>
    <p class="mt-1 text-sm text-muted-foreground">Sitenin teması, renkleri, bölümleri ve menüleri.</p>
  </div>
  <?= $actions ?? '' ?>
</div>
<nav class="mb-8 flex gap-1 border-b">
  <?php foreach ($tabs as $key => [$label, $url]): ?>
    <a href="<?= Request::url($url) ?>"
       class="-mb-px border-b-2 px-3 pb-2.5 text-sm transition-colors <?= $key === $current ? 'border-primary font-medium text-foreground' : 'border-transparent text-muted-foreground hover:text-foreground' ?>"><?= $label ?></a>
  <?php endforeach ?>
</nav>
