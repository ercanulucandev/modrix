<?php // Admin > Kullanıcılar. Değişkenler: $users (sayfalı), $roles, $q, $role
$me = App\Models\User::admin();
?>
<div class="mb-4 flex flex-wrap items-end justify-between gap-4">
  <div>
    <h1 class="text-2xl font-semibold tracking-tight">Kullanıcılar</h1>
    <p class="mt-1 text-sm text-muted-foreground">Yönetim paneline girebilen kişiler ve rolleri.</p>
  </div>
  <a href="<?= Router::url('admin.users.create') ?>" class="btn btn-primary">
    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg> Yeni kullanıcı
  </a>
</div>
<?= View::render('admin/users/tabs', ['current' => 'users']) ?>

<form class="mb-4 flex flex-wrap gap-2">
  <input type="search" name="q" value="<?= View::e($q) ?>" placeholder="Ad ya da e-posta…" class="input mt-0 max-w-xs">
  <select name="role" class="input mt-0 w-auto" onchange="this.form.submit()">
    <option value="">Tüm roller</option>
    <?php foreach ($roles as $key => $label): ?><option value="<?= View::e($key) ?>" <?= $key === $role ? 'selected' : '' ?>><?= View::e($label) ?></option><?php endforeach ?>
  </select>
</form>

<div class="card overflow-x-auto">
  <table class="table">
    <thead><tr><th>Ad</th><th>Rol</th><th>Durum</th><th class="hidden sm:table-cell">Eklenme</th><th class="text-right">İşlem</th></tr></thead>
    <tbody>
      <?php foreach ($users['data'] as $u): ?>
        <tr>
          <td>
            <a href="<?= Router::url('admin.users.edit', ['id' => $u['id']]) ?>" class="font-medium hover:underline"><?= View::e($u['name']) ?></a>
            <?php if ((int) $u['id'] === (int) $me['id']): ?><span class="badge badge-gray ml-1">Siz</span><?php endif ?>
            <p class="text-xs text-muted-foreground"><?= View::e($u['email']) ?></p>
          </td>
          <td><?= View::e($roles[$u['role']] ?? $u['role']) ?></td>
          <td><span class="badge <?= $u['status'] === 'active' ? 'badge-green' : 'badge-yellow' ?>"><?= $u['status'] === 'active' ? 'Aktif' : 'Pasif' ?></span></td>
          <td class="hidden text-muted-foreground sm:table-cell"><?= View::date($u['created_at']) ?></td>
          <td>
            <div class="flex justify-end gap-2">
              <a href="<?= Router::url('admin.users.edit', ['id' => $u['id']]) ?>" class="btn btn-secondary btn-sm">Düzenle</a>
              <?php if ((int) $u['id'] !== (int) $me['id']): ?>
                <form method="post" action="<?= Router::url('admin.users.delete', ['id' => $u['id']]) ?>" data-confirm="<?= View::e($u['name']) ?> silinsin mi? Bu işlem geri alınamaz.">
                  <?= View::csrf() ?><button class="btn btn-danger-ghost btn-sm">Sil</button>
                </form>
              <?php endif ?>
            </div>
          </td>
        </tr>
      <?php endforeach ?>
      <?php if (!$users['data']): ?><tr><td colspan="5" class="py-10 text-center text-muted-foreground">Kullanıcı bulunamadı.</td></tr><?php endif ?>
    </tbody>
  </table>
</div>
<div class="mt-4"><?= View::render('partials/pagination', ['page' => $users]) ?></div>
