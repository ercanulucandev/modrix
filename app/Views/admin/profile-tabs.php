<?php // Profilim sekmeleri. Değişken: $current ('profile' | 'security') ?>
<nav class="mb-6 flex gap-1 border-b">
  <?php foreach (['profile' => ['Bilgilerim', 'admin/profile'], 'security' => ['Güvenlik', 'admin/profile/security']] as $key => [$label, $url]): ?>
    <a href="<?= Request::url($url) ?>" class="-mb-px border-b-2 px-3 py-2 text-sm font-medium <?= $current === $key ? 'border-primary text-foreground' : 'border-transparent text-muted-foreground hover:text-foreground' ?>"><?= $label ?></a>
  <?php endforeach ?>
</nav>
