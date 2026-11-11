<div class="mb-6 space-y-1.5">
  <h1 class="text-xl font-semibold tracking-tight">Yeni şifre</h1>
  <p class="text-sm text-muted-foreground"><?= View::e($email) ?> için yeni şifrenizi belirleyin.</p>
</div>

<form method="post" action="<?= Router::url('admin.reset') ?>" class="space-y-4">
  <?= View::csrf() ?>
  <input type="hidden" name="token" value="<?= View::e($token) ?>">
  <input type="hidden" name="email" value="<?= View::e($email) ?>">
  <?= View::render('partials/field', ['name' => 'password', 'label' => 'Yeni şifre', 'type' => 'password', 'required' => true, 'help' => 'En az 8 karakter.', 'attrs' => 'autofocus autocomplete="new-password" minlength="8"']) ?>
  <?= View::render('partials/field', ['name' => 'password_confirmation', 'label' => 'Yeni şifre (tekrar)', 'type' => 'password', 'required' => true, 'attrs' => 'autocomplete="new-password"']) ?>
  <button class="btn btn-primary w-full">Şifreyi değiştir</button>
</form>
