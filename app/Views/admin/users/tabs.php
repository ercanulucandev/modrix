<?php // Kullanıcılar / Roller sekmeleri. Değişken: $current ?>
<nav class="mb-6 flex gap-1 border-b">
  <?php
  $tabs = ['users' => ['Kullanıcılar', 'admin/users', 'users.manage'], 'roles' => ['Roller', 'admin/roles', 'users.manage'], 'activity' => ['İşlem kaydı', 'admin/activity', 'activity.view']];
  foreach ($tabs as $key => [$label, $url, $permission]): if (!App\Services\Access::can($permission)) continue; ?>
    <a href="<?= Request::url($url) ?>" class="-mb-px border-b-2 px-3 pb-2.5 text-sm <?= $key === $current ? 'border-primary font-medium' : 'border-transparent text-muted-foreground hover:text-foreground' ?>"><?= $label ?></a>
  <?php endforeach ?>
</nav>
