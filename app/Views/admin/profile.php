<?php // Admin > Profilim. Değişken: $user ?>
<div class="mb-4">
  <h1 class="text-2xl font-semibold tracking-tight">Profilim</h1>
  <p class="mt-1 text-sm text-muted-foreground">Adınız, e-postanız ve şifreniz.</p>
</div>
<?= View::render('admin/profile-tabs', ['current' => 'profile']) ?>

<form method="post" action="<?= Router::url('admin.profile') ?>" class="card max-w-2xl space-y-5 p-6">
  <?= View::csrf() ?>
  <div class="grid gap-5 sm:grid-cols-2">
    <?= View::render('partials/field', ['name' => 'name', 'label' => 'Ad soyad', 'value' => $user['name'], 'required' => true]) ?>
    <?= View::render('partials/field', ['name' => 'email', 'label' => 'E-posta', 'type' => 'email', 'value' => $user['email'], 'required' => true]) ?>
  </div>
  <div class="grid gap-5 border-t pt-5 sm:grid-cols-2">
    <?= View::render('partials/field', ['name' => 'password', 'label' => 'Yeni şifre', 'type' => 'password', 'help' => 'Değiştirmeyecekseniz boş bırakın.', 'attrs' => 'autocomplete="new-password" minlength="8"']) ?>
    <?= View::render('partials/field', ['name' => 'password_confirmation', 'label' => 'Yeni şifre (tekrar)', 'type' => 'password', 'attrs' => 'autocomplete="new-password"']) ?>
  </div>
  <div class="border-t pt-5">
    <?= View::render('partials/field', ['name' => 'current_password', 'label' => 'Mevcut şifreniz', 'type' => 'password', 'help' => 'E-posta ya da şifre değiştirirken gerekli.', 'attrs' => 'autocomplete="current-password"']) ?>
  </div>
  <div class="flex justify-end"><button class="btn btn-primary">Kaydet</button></div>
</form>
