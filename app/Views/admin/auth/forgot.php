<div class="mb-6 space-y-1.5">
  <h1 class="text-xl font-semibold tracking-tight">Şifremi unuttum</h1>
  <p class="text-sm text-muted-foreground">Hesabınızın e-posta adresini yazın; şifre sıfırlama bağlantısı gönderelim.</p>
</div>

<form method="post" action="<?= Router::url('admin.forgot') ?>" class="space-y-4">
  <?= View::csrf() ?>
  <?= View::render('partials/field', ['name' => 'email', 'label' => 'E-posta', 'type' => 'email', 'required' => true, 'placeholder' => 'ornek@site.com', 'attrs' => 'autofocus autocomplete="username"']) ?>
  <button class="btn btn-primary w-full">Bağlantı gönder</button>
</form>
<p class="mt-6 text-center text-sm"><a href="<?= Router::url('admin.login') ?>" class="text-muted-foreground hover:text-foreground">← Girişe dön</a></p>
