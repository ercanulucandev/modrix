<?php // Diller sekmeleri. Değişken: $current ?>
<nav class="mb-6 flex flex-wrap gap-1 border-b">
  <?php foreach (['languages' => ['Diller', 'admin/languages'], 'strings' => ['Arayüz metinleri', 'admin/languages/strings']] as $key => [$label, $url]): ?>
    <a href="<?= Request::url($url) ?>" class="-mb-px border-b-2 px-3 py-2 text-sm font-medium <?= $current === $key ? 'border-primary text-foreground' : 'border-transparent text-muted-foreground hover:text-foreground' ?>"><?= $label ?></a>
  <?php endforeach ?>
</nav>
