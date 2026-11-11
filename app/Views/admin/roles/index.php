<?php // Admin > Roller. Değişkenler: $roles (slug => rol), $counts (slug => kullanıcı sayısı), $permissions (yetki => açıklama) ?>
<div class="mb-4 flex flex-wrap items-end justify-between gap-4">
  <div>
    <h1 class="text-2xl font-semibold tracking-tight">Kullanıcılar</h1>
    <p class="mt-1 text-sm text-muted-foreground">Rol, kullanıcının panelde neleri görüp değiştirebileceğini belirler.</p>
  </div>
  <a href="<?= Router::url('admin.roles.create') ?>" class="btn btn-primary">
    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg> Yeni rol
  </a>
</div>
<?= View::render('admin/users/tabs', ['current' => 'roles']) ?>

<div class="grid gap-4 md:grid-cols-2">
  <?php foreach ($roles as $slug => $role): $all = in_array('*', $role['permissions'], true); ?>
    <div class="card flex flex-col p-5">
      <div class="flex items-start justify-between gap-3">
        <div>
          <h2 class="font-semibold"><?= View::e($role['name']) ?> <?php if ($role['is_system']): ?><span class="badge badge-gray ml-1">Sistem</span><?php endif ?></h2>
          <p class="text-sm text-muted-foreground"><?= (int) ($counts[$slug] ?? 0) ?> kullanıcı</p>
        </div>
        <div class="flex gap-2">
          <a href="<?= Router::url('admin.roles.edit', ['id' => $role['id']]) ?>" class="btn btn-secondary btn-sm">Düzenle</a>
          <?php if (!$role['is_system']): ?>
            <form method="post" action="<?= Router::url('admin.roles.delete', ['id' => $role['id']]) ?>" data-confirm="<?= View::e($role['name']) ?> rolü silinsin mi?"><?= View::csrf() ?><button class="btn btn-danger-ghost btn-sm">Sil</button></form>
          <?php endif ?>
        </div>
      </div>
      <div class="mt-4 flex flex-wrap gap-1.5">
        <?php if ($all): ?>
          <span class="badge badge-default">Her şey</span>
        <?php else: ?>
          <?php foreach ($role['permissions'] as $p): if (!isset($permissions[$p])) continue; ?><span class="badge"><?= View::e($permissions[$p]) ?></span><?php endforeach ?>
          <?php if (!array_intersect($role['permissions'], array_keys($permissions))): ?><span class="text-sm text-muted-foreground">Sadece genel bakış ve profil</span><?php endif ?>
        <?php endif ?>
      </div>
    </div>
  <?php endforeach ?>
</div>
