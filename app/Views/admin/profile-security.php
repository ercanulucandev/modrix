<?php // Admin > Profilim > Güvenlik. Değişkenler: $user, $enabled, $required, $secret, $uri, $remaining, $codes ?>
<div class="mb-4">
  <h1 class="text-2xl font-semibold tracking-tight">Profilim</h1>
  <p class="mt-1 text-sm text-muted-foreground">Hesabınızın güvenliği.</p>
</div>
<?= View::render('admin/profile-tabs', ['current' => 'security']) ?>

<div class="max-w-2xl space-y-6">
  <?php if ($codes): ?>
    <section class="card border-amber-300 p-6">
      <h2 class="font-semibold">Kurtarma kodlarınız</h2>
      <p class="mt-1 text-sm text-muted-foreground">Telefonunuzu kaybederseniz bu kodlarla giriş yaparsınız; her biri bir kez geçer.
        <strong class="text-foreground">Bir daha gösterilmeyecek</strong>; güvenli bir yere (şifre yöneticisi, çıktı) kaydedin.</p>
      <pre id="recovery-codes" class="mt-4 grid grid-cols-2 gap-2 rounded-md bg-muted p-4 font-mono text-sm"><?php foreach ($codes as $c): ?><span><?= View::e($c) ?></span><?php endforeach ?></pre>
      <div class="mt-3 flex gap-2">
        <button type="button" class="btn btn-secondary btn-sm" onclick="navigator.clipboard.writeText(<?= View::e(json_encode(implode("\n", $codes))) ?>).then(() => this.textContent = 'Kopyalandı')">Kopyala</button>
        <button type="button" class="btn btn-secondary btn-sm" onclick="window.print()">Yazdır</button>
      </div>
    </section>
  <?php endif ?>

  <section class="card p-6">
    <div class="flex items-start justify-between gap-4">
      <div>
        <h2 class="font-semibold">İki adımlı doğrulama</h2>
        <p class="mt-1 text-sm text-muted-foreground">Girişte şifrenize ek olarak telefonunuzdaki doğrulayıcı uygulamanın (Google Authenticator, Microsoft Authenticator, 1Password…) ürettiği kod istenir.</p>
      </div>
      <span class="badge <?= $enabled ? 'badge-green' : 'badge-gray' ?> shrink-0"><?= $enabled ? 'Açık' : 'Kapalı' ?></span>
    </div>

    <?php if (!$enabled): ?>
      <?php if ($required): ?><p class="mt-4 rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">Bu sitede iki adımlı doğrulama zorunlu. Panele devam etmek için açın.</p><?php endif ?>
      <ol class="mt-5 space-y-5 text-sm">
        <li>
          <p class="font-medium">1. Uygulamada QR kodu okutun</p>
          <div class="mt-3 flex flex-wrap items-center gap-5">
            <div id="two-factor-qr" class="keep-light rounded-md border bg-white p-2" data-uri="<?= View::e($uri) ?>" style="width:184px;height:184px"></div>
            <div class="min-w-0 text-muted-foreground">
              <p>Okutamıyorsanız anahtarı elle girin:</p>
              <code class="mt-1 block break-all rounded bg-muted px-2 py-1 font-mono text-foreground"><?= View::e(trim(chunk_split($secret, 4, ' '))) ?></code>
            </div>
          </div>
        </li>
        <li>
          <p class="font-medium">2. Uygulamanın gösterdiği kodu girin</p>
          <form method="post" action="<?= Router::url('admin.security.enable') ?>" class="mt-3 flex flex-wrap items-start gap-2">
            <?= View::csrf() ?>
            <div class="w-44"><?= View::render('partials/field', ['name' => 'code', 'label' => 'Kod', 'required' => true, 'placeholder' => '123456',
              'attrs' => 'autocomplete="one-time-code" inputmode="numeric" maxlength="6" style="font-family:ui-monospace,monospace;letter-spacing:.2em"']) ?></div>
            <button class="btn btn-primary mt-6">Doğrula ve aç</button>
          </form>
        </li>
      </ol>
      <script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"></script>
      <script>
        (() => {
          const box = document.getElementById('two-factor-qr');
          if (!box || typeof qrcode === 'undefined') return;
          const qr = qrcode(0, 'M');
          qr.addData(box.dataset.uri);
          qr.make();
          box.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 2, scalable: true });
        })();
      </script>
    <?php else: ?>
      <p class="mt-4 text-sm text-muted-foreground">Açılma: <?= View::date($user['two_factor_confirmed_at'], true) ?> · Kalan kurtarma kodu: <strong class="text-foreground"><?= $remaining ?></strong> / <?= App\Services\TwoFactor::RECOVERY_COUNT ?></p>
      <form method="post" class="mt-5 space-y-4 border-t pt-5">
        <?= View::csrf() ?>
        <div class="max-w-xs"><?= View::render('partials/field', ['name' => 'password', 'label' => 'Şifreniz', 'type' => 'password', 'required' => true,
          'help' => 'Kurtarma kodlarını yenilemek ya da kapatmak için.', 'attrs' => 'autocomplete="current-password"']) ?></div>
        <div class="flex flex-wrap gap-2">
          <button class="btn btn-secondary" formaction="<?= Router::url('admin.security.recovery') ?>">Yeni kurtarma kodları</button>
          <?php if (!$required): ?>
            <button class="btn btn-danger" formaction="<?= Router::url('admin.security.disable') ?>" onclick="return confirm('İki adımlı doğrulama kapatılsın mı?')">Kapat</button>
          <?php endif ?>
        </div>
      </form>
    <?php endif ?>
  </section>
</div>
