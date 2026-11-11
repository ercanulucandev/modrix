<?php // Kullanıcı ekle / düzenle. Değişkenler: $user (ya da null), $roles
$self = $user && (int) $user['id'] === (int) App\Models\User::admin()['id'];
$action = $user ? Router::url('admin.users.update', ['id' => $user['id']]) : Router::url('admin.users.store');
?>
<div class="mb-6 flex items-center gap-2">
  <a href="<?= Router::url('admin.users.index') ?>" class="btn btn-ghost size-9 p-0" aria-label="Kullanıcılara dön"><svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg></a>
  <h1 class="text-2xl font-semibold tracking-tight"><?= $user ? View::e($user['name']) : 'Yeni kullanıcı' ?></h1>
</div>

<div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_18rem]">
  <form method="post" action="<?= $action ?>" class="card space-y-5 p-6">
    <?= View::csrf() ?>
    <div class="grid gap-5 sm:grid-cols-2">
      <?= View::render('partials/field', ['name' => 'name', 'label' => 'Ad soyad', 'value' => $user['name'] ?? null, 'required' => true]) ?>
      <?= View::render('partials/field', ['name' => 'email', 'label' => 'E-posta', 'type' => 'email', 'value' => $user['email'] ?? null, 'required' => true]) ?>
    </div>
    <div class="grid gap-5 sm:grid-cols-2">
      <?= View::render('partials/field', ['name' => 'role', 'label' => 'Rol', 'type' => 'select', 'options' => $roles, 'value' => $user['role'] ?? 'editor', 'required' => true,
          'help' => $self ? 'Kendi rolünüzü değiştiremezsiniz.' : 'Yetkiler: Kullanıcılar > Roller.', 'attrs' => $self ? 'disabled' : '']) ?>
      <?php if ($user): ?>
        <?= View::render('partials/field', ['name' => 'status', 'label' => 'Durum', 'type' => 'select', 'options' => ['active' => 'Aktif', 'passive' => 'Pasif (giriş yapamaz)'],
            'value' => $user['status'], 'required' => true, 'attrs' => $self ? 'disabled' : '']) ?>
        <?php if ($self): ?><input type="hidden" name="role" value="<?= View::e($user['role']) ?>"><input type="hidden" name="status" value="active"><?php endif ?>
      <?php endif ?>
    </div>
    <?= View::render('partials/field', ['name' => 'password', 'label' => $user ? 'Yeni şifre' : 'Şifre', 'type' => 'password',
        'help' => $user ? 'Değiştirmeyecekseniz boş bırakın.' : 'Boş bırakırsanız kullanıcıya şifresini kendisinin belirleyeceği bir davet e-postası gönderilir.',
        'attrs' => 'autocomplete="new-password" minlength="8"']) ?>
    <div class="flex justify-end"><button class="btn btn-primary"><?= $user ? 'Kaydet' : 'Kullanıcıyı ekle' ?></button></div>
  </form>

  <?php if ($user): ?>
    <aside class="card space-y-3 p-5 text-sm">
      <p><span class="text-muted-foreground">Eklenme:</span> <?= View::date($user['created_at'], true) ?></p>
      <p class="text-muted-foreground">Kullanıcı şifresini unuttuysa ya da daveti kaybettiyse yeni bağlantı gönderin:</p>
      <form method="post" action="<?= Router::url('admin.users.invite', ['id' => $user['id']]) ?>"><?= View::csrf() ?><button class="btn btn-secondary btn-sm w-full">Şifre belirleme bağlantısı gönder</button></form>
      <div class="border-t pt-3">
        <p><span class="text-muted-foreground">İki adımlı doğrulama:</span> <?= App\Services\TwoFactor::enabled($user) ? '<span class="badge badge-green">Açık</span>' : '<span class="badge badge-gray">Kapalı</span>' ?></p>
        <?php if (App\Services\TwoFactor::enabled($user)): ?>
          <p class="mt-2 text-muted-foreground">Telefonunu ve kurtarma kodlarını kaybettiyse sıfırlayın; bir sonraki girişte yalnızca şifre istenir.</p>
          <form method="post" action="<?= Router::url('admin.users.2fa.reset', ['id' => $user['id']]) ?>" class="mt-2" onsubmit="return confirm('Bu kullanıcının iki adımlı doğrulaması sıfırlansın mı?')"><?= View::csrf() ?><button class="btn btn-secondary btn-sm w-full">İki adımlı doğrulamayı sıfırla</button></form>
        <?php endif ?>
      </div>
    </aside>
  <?php endif ?>
</div>
