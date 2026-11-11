<div class="mb-6 space-y-1.5">
  <h1 class="text-xl font-semibold tracking-tight">İki adımlı doğrulama</h1>
  <p class="text-sm text-muted-foreground">Doğrulayıcı uygulamanızdaki 6 haneli kodu girin. Telefonunuz yanınızda değilse kurtarma kodlarınızdan birini kullanabilirsiniz.</p>
</div>

<form method="post" action="<?= Router::url('admin.login.verify') ?>" class="space-y-4">
  <?= View::csrf() ?>
  <?= View::render('partials/field', ['name' => 'code', 'label' => 'Doğrulama kodu', 'required' => true, 'placeholder' => '123456',
      'attrs' => 'autofocus autocomplete="one-time-code" inputmode="numeric" maxlength="20" style="font-family:ui-monospace,monospace;letter-spacing:.2em"']) ?>
  <button class="btn btn-primary w-full">Doğrula</button>
</form>
<p class="mt-6 text-center text-sm"><a href="<?= Router::url('admin.login') ?>" class="text-muted-foreground hover:text-foreground">Başka hesapla giriş yap</a></p>
