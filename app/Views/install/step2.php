<?php // Kurulum 2: veritabanı. Değişken: $old
$v = fn($k, $d = '') => View::e((string) ($old[$k] ?? $d));
?>
<h1 class="text-xl font-semibold">Veritabanı</h1>
<p class="mt-1 text-sm text-muted-foreground">Hosting panelinizdeki MySQL / MariaDB bilgileri. Veritabanı yoksa oluşturulmaya çalışılır.</p>
<form method="post" class="mt-5 space-y-4">
  <input type="hidden" name="_token" value="<?= Session::token() ?>"><input type="hidden" name="step" value="2">
  <div class="grid grid-cols-[1fr_6rem] gap-3">
    <label class="block"><span class="label">Sunucu</span><input name="host" value="<?= $v('host', '127.0.0.1') ?>" class="input" required></label>
    <label class="block"><span class="label">Port</span><input name="port" value="<?= $v('port', '3306') ?>" class="input" inputmode="numeric"></label>
  </div>
  <label class="block"><span class="label">Veritabanı adı</span><input name="name" value="<?= $v('name', 'modrix') ?>" class="input" required pattern="[A-Za-z0-9_]+"></label>
  <div class="grid gap-3 sm:grid-cols-2">
    <label class="block"><span class="label">Kullanıcı</span><input name="user" value="<?= $v('user', 'root') ?>" class="input" required autocomplete="off"></label>
    <label class="block"><span class="label">Şifre</span><input type="password" name="pass" class="input" autocomplete="new-password"></label>
  </div>
  <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="reuse" value="1" class="size-4"> Veritabanında önceki bir kurulum varsa onu kullan</label>
  <div class="flex items-center justify-between pt-2"><a href="?step=1" class="text-sm text-muted-foreground hover:underline">← Geri</a><button class="btn">Bağlan ve devam et</button></div>
</form>
