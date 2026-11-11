<?php // Kurulum 3: site ve yönetici, mağazadan modül ve tema. Değişken: $old
use App\Services\Installer;
use App\Services\Store;
$v = fn($k, $d = '') => View::e((string) ($old[$k] ?? $d));
$modules = Store::catalog(false, 'modules');
$themes = Store::catalog(false, 'themes');
$picked = $old ? (array) ($old['modules'] ?? []) : Installer::RECOMMENDED;
?>
<h1 class="text-xl font-semibold">Site ve yönetici</h1>
<p class="mt-1 text-sm text-muted-foreground">Bağlantı tamam. Site adını, yönetici hesabınızı ve başlangıç modüllerini seçin.</p>
<form method="post" class="mt-5 space-y-5" onsubmit="this.querySelector('button').disabled = true; this.querySelector('button').textContent = 'Kuruluyor… (modüller indiriliyor)'">
  <input type="hidden" name="_token" value="<?= Session::token() ?>"><input type="hidden" name="step" value="3">
  <label class="block"><span class="label">Site adı</span><input name="site_name" value="<?= $v('site_name') ?>" class="input" required maxlength="100"></label>
  <div class="grid gap-3 sm:grid-cols-2">
    <label class="block"><span class="label">Adınız</span><input name="admin_name" value="<?= $v('admin_name') ?>" class="input" required autocomplete="name"></label>
    <label class="block"><span class="label">E-posta</span><input type="email" name="admin_email" value="<?= $v('admin_email') ?>" class="input" required autocomplete="email"></label>
    <label class="block"><span class="label">Şifre</span><input type="password" name="admin_password" class="input" required minlength="8" autocomplete="new-password"></label>
    <label class="block"><span class="label">Şifre (tekrar)</span><input type="password" name="admin_password_confirmation" class="input" required autocomplete="new-password"></label>
  </div>

  <fieldset>
    <legend class="label">Tema</legend>
    <div class="mt-2 grid grid-cols-2 gap-2 text-sm sm:grid-cols-3">
      <label class="flex cursor-pointer items-center gap-2 rounded-md border p-2.5 has-[:checked]:border-zinc-900"><input type="radio" name="theme" value="<?= Core\Theme::DEFAULT ?>" class="size-4" <?= ($old['theme'] ?? Core\Theme::DEFAULT) === Core\Theme::DEFAULT ? 'checked' : '' ?>> Başlangıç</label>
      <?php foreach ($themes['items'] as $t): ?>
        <label class="flex cursor-pointer items-center gap-2 rounded-md border p-2.5 has-[:checked]:border-zinc-900"><input type="radio" name="theme" value="<?= View::e($t['slug']) ?>" class="size-4" <?= ($old['theme'] ?? '') === $t['slug'] ? 'checked' : '' ?>> <?= View::e($t['name']) ?></label>
      <?php endforeach ?>
    </div>
  </fieldset>

  <fieldset>
    <legend class="label">Modüller</legend>
    <?php if (!$modules['items']): ?>
      <p class="mt-1 text-xs text-muted-foreground">Mağazaya ulaşılamadı; modülleri kurulumdan sonra Sistem &gt; Mağaza'dan ekleyebilirsiniz.</p>
    <?php else: ?>
      <p class="text-xs text-muted-foreground">Mağazadan indirilip kurulur. İstediğinizi sonra Sistem &gt; Mağaza'dan da ekleyebilirsiniz.</p>
      <div class="mt-2 grid gap-2 text-sm sm:grid-cols-2">
        <?php foreach ($modules['items'] as $m): ?>
          <label class="flex items-start gap-2" title="<?= View::e($m['description']) ?>"><input type="checkbox" name="modules[]" value="<?= View::e($m['slug']) ?>" class="mt-0.5 size-4" <?= in_array($m['slug'], $picked, true) ? 'checked' : '' ?>>
            <span><?= View::e($m['name']) ?> <span class="text-xs text-muted-foreground">· <?= View::e($m['category']) ?></span></span></label>
        <?php endforeach ?>
      </div>
    <?php endif ?>
  </fieldset>
  <div class="flex items-center justify-between pt-2"><a href="?step=1" class="text-sm text-muted-foreground hover:underline">← Baştan</a><button class="btn">Kurulumu tamamla</button></div>
</form>
