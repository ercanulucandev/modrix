<?php // Rol ekle / düzenle. Değişkenler: $role (ya da null), $groups (grup => [yetki => açıklama])
$all = $role && in_array('*', $role['permissions'], true);
$has = $role['permissions'] ?? [];
$action = $role ? Router::url('admin.roles.update', ['id' => $role['id']]) : Router::url('admin.roles.store');
?>
<div class="mb-6 flex items-center gap-2">
  <a href="<?= Router::url('admin.roles.index') ?>" class="btn btn-ghost size-9 p-0" aria-label="Rollere dön"><svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg></a>
  <h1 class="text-2xl font-semibold tracking-tight"><?= $role ? View::e($role['name']) : 'Yeni rol' ?></h1>
</div>

<form method="post" action="<?= $action ?>" class="card max-w-3xl">
  <?= View::csrf() ?>
  <div class="border-b p-6">
    <?= View::render('partials/field', ['name' => 'name', 'label' => 'Rol adı', 'value' => $role['name'] ?? null, 'required' => true, 'placeholder' => 'ör. Mağaza sorumlusu']) ?>
  </div>
  <div class="space-y-6 p-6">
    <?php if ($all): ?>
      <div class="alert alert-info mb-0">Bu rol <strong>her şeye</strong> yetkilidir; yetkileri değiştirilemez.</div>
    <?php else: ?>
      <p class="text-sm text-muted-foreground">Her rol genel bakışı ve kendi profilini görür. Ek olarak yapabilecekleri:</p>
      <?php foreach ($groups as $group => $permissions): ?>
        <fieldset>
          <legend class="mb-2 text-xs font-medium uppercase tracking-wider text-muted-foreground"><?= View::e($group) ?></legend>
          <div class="grid gap-2 sm:grid-cols-2">
            <?php foreach ($permissions as $key => $label): ?>
              <label class="flex cursor-pointer items-start gap-3 rounded-md border p-3 text-sm hover:bg-accent/50">
                <input type="checkbox" name="permissions[]" value="<?= View::e($key) ?>" class="mt-0.5 size-4" <?= in_array($key, $has, true) ? 'checked' : '' ?>>
                <span><?= View::e($label) ?><span class="block font-mono text-xs text-muted-foreground"><?= View::e($key) ?></span></span>
              </label>
            <?php endforeach ?>
          </div>
        </fieldset>
      <?php endforeach ?>
    <?php endif ?>
  </div>
  <div class="card-footer justify-end"><button class="btn btn-primary">Kaydet</button></div>
</form>
