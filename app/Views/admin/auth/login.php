<div class="mb-6 space-y-1.5">
  <h1 class="text-xl font-semibold tracking-tight">Giriş yap</h1>
  <p class="text-sm text-muted-foreground">Yönetim paneline erişmek için bilgilerinizi girin.</p>
</div>

<form method="post" action="<?= Router::url('admin.login') ?>" class="space-y-4">
  <?= View::csrf() ?>
  <?= View::render('partials/field', ['name' => 'email', 'label' => 'E-posta', 'type' => 'email', 'required' => true, 'placeholder' => 'ornek@site.com', 'attrs' => 'autofocus autocomplete="username"']) ?>
  <?= View::render('partials/field', ['name' => 'password', 'label' => 'Şifre', 'type' => 'password', 'required' => true, 'attrs' => 'autocomplete="current-password"']) ?>
  <button class="btn btn-primary w-full">Giriş yap</button>
</form>
<p class="mt-6 text-center text-sm"><a href="<?= Router::url('admin.forgot') ?>" class="text-muted-foreground hover:text-foreground">Şifremi unuttum</a></p>
