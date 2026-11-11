<?php // Admin > Güncellemeler. Değişkenler: $current, $latest, $repo, $hasToken, $history, $steps
use App\Services\Updater;
$newer = !empty($latest['version']) && version_compare($latest['version'], $current, '>');
?>
<div class="mb-6">
  <h1 class="text-2xl font-semibold tracking-tight">Güncellemeler</h1>
  <p class="mt-1 text-sm text-muted-foreground">Modrix'in yeni sürümleri <a href="https://github.com/<?= View::e($repo) ?>/releases" target="_blank" rel="noopener" class="underline"><?= View::e($repo) ?></a> üzerinden gelir.</p>
</div>

<div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
  <div class="space-y-6">
    <section class="card p-6">
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
          <p class="text-sm text-muted-foreground">Kullandığınız sürüm</p>
          <p class="text-3xl font-semibold tracking-tight">v<?= View::e($current) ?></p>
        </div>
        <?php if ($newer): ?>
          <span class="badge badge-yellow">Yeni sürüm: v<?= View::e($latest['version']) ?></span>
        <?php elseif (!empty($latest['version'])): ?>
          <span class="badge badge-green">Güncel</span>
        <?php endif ?>
      </div>
      <?php if (!empty($latest['error'])): ?>
        <p class="mt-4 rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">Sürüm bilgisi alınamadı: <?= View::e($latest['error']) ?></p>
      <?php endif ?>
      <p class="mt-4 text-xs text-muted-foreground">Son denetim: <?= !empty($latest['checked_at']) ? date('d.m.Y H:i', $latest['checked_at']) : '—' ?> · <a href="?refresh=1" class="underline">Şimdi denetle</a></p>
    </section>

    <?php if ($newer): ?>
      <section class="card p-6">
        <h2 class="font-semibold"><?= View::e($latest['name']) ?></h2>
        <?php if ($latest['published_at']): ?><p class="text-xs text-muted-foreground"><?= View::date(date('Y-m-d H:i:s', strtotime($latest['published_at'])), true) ?></p><?php endif ?>
        <div class="mt-4 space-y-2 text-sm leading-6"><?= Updater::notes($latest['notes']) ?: '<p class="text-muted-foreground">Sürüm notu yok.</p>' ?></div>
        <form method="post" action="<?= Request::url('admin/updates/apply') ?>" class="mt-6 border-t pt-5" onsubmit="this.querySelector('button').disabled = true; this.querySelector('button').textContent = 'Güncelleniyor… (sayfayı kapatmayın)'; return confirm('Güncelleme başlasın mı? Önce tam yedek alınır; birkaç dakika sürebilir.')">
          <?= View::csrf() ?>
          <button class="btn btn-primary">v<?= View::e($latest['version']) ?> sürümüne güncelle</button>
          <p class="mt-2 text-xs text-muted-foreground">Önce tam yedek alınır, işlem boyunca site bakım modunda olur. Bir hata olursa dosyalar ve veritabanı kendiliğinden eski haline döner.</p>
        </form>
      </section>
    <?php endif ?>

    <?php if ($steps): ?>
      <section class="card p-6">
        <h2 class="font-semibold">Son işlem</h2>
        <ol class="mt-3 space-y-1 font-mono text-xs"><?php foreach ($steps as $s): ?><li><?= View::e($s) ?></li><?php endforeach ?></ol>
      </section>
    <?php endif ?>
  </div>

  <aside class="space-y-6">
    <section class="card space-y-3 p-5 text-sm">
      <p class="font-medium">Neler korunur?</p>
      <ul class="list-disc space-y-1 pl-5 text-muted-foreground">
        <li>Yüklenen dosyalar, yedekler, loglar (storage/)</li>
        <li>Veritabanı bağlantısı ve şifreleme anahtarı (*.local.php)</li>
        <li>Kendi eklediğiniz tema ve modüller</li>
      </ul>
      <p class="text-muted-foreground">Hazır temaların ve modüllerin dosyalarında değişiklik yaptıysanız güncellemede ezilir; değişiklikleri kendi temanıza taşıyın.</p>
    </section>
    <form method="post" action="<?= Request::url('admin/updates/token') ?>" class="card space-y-3 p-5 text-sm">
      <?= View::csrf() ?>
      <p class="font-medium">GitHub erişim anahtarı</p>
      <p class="text-muted-foreground">Depo gizliyse gerekir (GitHub > Settings > Developer settings > Fine-grained token, "Contents: Read").<?= $hasToken ? ' <strong class="text-foreground">Kayıtlı.</strong>' : '' ?></p>
      <input type="password" name="token" class="input mt-0" placeholder="<?= $hasToken ? 'Değiştirmek için yeni anahtar' : 'github_pat_...' ?>" autocomplete="off">
      <div class="flex gap-2"><button class="btn btn-secondary btn-sm">Kaydet</button><?php if ($hasToken): ?><button name="remove" value="1" class="btn btn-ghost btn-sm text-destructive">Kaldır</button><?php endif ?></div>
    </form>
    <?php if ($history): ?>
      <section class="card p-5 text-sm">
        <p class="font-medium">Geçmiş</p>
        <ul class="mt-2 space-y-1 text-muted-foreground"><?php foreach ($history as $h): ?><li><?= View::date($h['created_at'], true) ?> · <?= View::e($h['description']) ?></li><?php endforeach ?></ul>
      </section>
    <?php endif ?>
  </aside>
</div>
