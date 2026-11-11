<?php // Admin > İşlem kaydı. Değişkenler: $rows (sayfalı), $users, $groups, $filter
$colors = ['auth' => 'badge-gray', 'user' => 'badge-yellow', 'settings' => 'badge-yellow', 'module' => 'badge-yellow', 'backup' => 'badge-yellow'];
?>
<div class="mb-4">
  <h1 class="text-2xl font-semibold tracking-tight">Kullanıcılar</h1>
  <p class="mt-1 text-sm text-muted-foreground">Panelde kim, ne zaman, ne yaptı. Kayıtlar <?= (int) Setting::get('activity_days', 180) ?> gün saklanır.</p>
</div>
<?= View::render('admin/users/tabs', ['current' => 'activity']) ?>

<form class="mb-4 flex flex-wrap gap-2">
  <select name="user" class="input mt-0 w-auto" onchange="this.form.submit()">
    <option value="">Herkes</option>
    <?php foreach ($users as $id => $name): ?><option value="<?= (int) $id ?>" <?= $filter['user'] === (int) $id ? 'selected' : '' ?>><?= View::e($name) ?></option><?php endforeach ?>
  </select>
  <select name="group" class="input mt-0 w-auto" onchange="this.form.submit()">
    <option value="">Tüm işlemler</option>
    <?php foreach ($groups as $key => $label): ?><option value="<?= $key ?>" <?= $filter['group'] === $key ? 'selected' : '' ?>><?= View::e($label) ?></option><?php endforeach ?>
  </select>
  <input type="search" name="q" value="<?= View::e($filter['q']) ?>" placeholder="Açıklama ya da IP'de ara…" class="input mt-0 max-w-xs">
</form>

<div class="card overflow-x-auto">
  <table class="table">
    <thead><tr><th>Zaman</th><th>Kişi</th><th>İşlem</th><th class="hidden md:table-cell">IP</th></tr></thead>
    <tbody>
      <?php foreach ($rows['data'] as $r): $group = explode('.', $r['action'])[0]; ?>
        <tr>
          <td class="whitespace-nowrap text-muted-foreground"><?= View::date($r['created_at'], true) ?></td>
          <td class="whitespace-nowrap"><?= $r['user_name'] ? View::e($r['user_name']) : '<span class="text-muted-foreground">—</span>' ?></td>
          <td>
            <span class="badge <?= $colors[$group] ?? 'badge-gray' ?> mr-1.5"><?= View::e($groups[$group] ?? $group) ?></span>
            <?php if ($r['subject_url']): ?>
              <a href="<?= View::e(Request::url($r['subject_url'])) ?>" class="hover:underline"><?= View::e($r['description']) ?></a>
            <?php else: ?><?= View::e($r['description']) ?><?php endif ?>
          </td>
          <td class="hidden font-mono text-xs text-muted-foreground md:table-cell"><?= View::e((string) $r['ip']) ?></td>
        </tr>
      <?php endforeach ?>
      <?php if (!$rows['data']): ?><tr><td colspan="4" class="py-10 text-center text-muted-foreground">Kayıt yok.</td></tr><?php endif ?>
    </tbody>
  </table>
</div>
<div class="mt-4"><?= View::render('partials/pagination', ['page' => $rows]) ?></div>
