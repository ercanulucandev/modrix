<?php
// Panel listelerinde içerik dili sekmeleri (Sayfalar, Yazılar). Değişken: $url (liste adresi). Sadece birden çok dil varsa.
if (!Lang::enabled()) return;
$current = Lang::content();
?>
<nav class="mb-4 flex flex-wrap gap-1 border-b" aria-label="İçerik dili">
  <?php foreach (Lang::all() as $code => $l): ?>
    <a href="<?= Request::url($url) ?>?lang=<?= $code ?>" class="-mb-px border-b-2 px-3 py-2 text-sm font-medium <?= $code === $current ? 'border-primary text-foreground' : 'border-transparent text-muted-foreground hover:text-foreground' ?>"><?= View::e($l['name']) ?></a>
  <?php endforeach ?>
</nav>
