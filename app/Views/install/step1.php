<?php // Kurulum 1: gereksinimler. Değişken: $requirements ?>
<h1 class="text-xl font-semibold">Gereksinimler</h1>
<p class="mt-1 text-sm text-muted-foreground">Sunucunuzun Modrix'i çalıştırabildiğini denetliyoruz.</p>
<ul class="mt-5 divide-y rounded-md border text-sm">
  <?php $ok = true; foreach ($requirements as $r): $optional = !empty($r[3]); if (!$r[1] && !$optional) $ok = false; ?>
    <li class="flex items-center gap-3 px-3 py-2.5">
      <span class="<?= $r[1] ? 'text-emerald-600' : ($optional ? 'text-amber-600' : 'text-red-600') ?>"><?= $r[1] ? '✓' : ($optional ? '!' : '✗') ?></span>
      <span class="flex-1"><?= View::e($r[0]) ?></span>
      <span class="text-xs text-muted-foreground"><?= View::e($r[2]) ?></span>
    </li>
  <?php endforeach ?>
</ul>
<form method="post" class="mt-6 flex items-center justify-between">
  <input type="hidden" name="_token" value="<?= Session::token() ?>"><input type="hidden" name="step" value="1">
  <a href="?step=1" class="text-sm text-muted-foreground hover:underline">Yeniden denetle</a>
  <button class="btn" <?= $ok ? '' : 'disabled' ?>>Devam</button>
</form>
