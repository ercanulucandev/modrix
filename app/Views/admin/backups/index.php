<?php // Admin > Yedekler. Değişkenler: $backups, $keep
$size = fn(int $b) => $b >= 1048576 ? number_format($b / 1048576, 1, ',', '.') . ' MB' : max(1, round($b / 1024)) . ' KB';
?>
<div class="mb-6 flex flex-wrap items-start justify-between gap-4">
  <div>
    <h1 class="text-2xl font-semibold tracking-tight">Yedekler</h1>
    <p class="mt-1 text-sm text-muted-foreground">Veritabanı ve yüklenen dosyalar tek ZIP'te. Son <?= $keep ?> yedek saklanır (Ayarlar &gt; Güvenlik).</p>
  </div>
  <form method="post" action="<?= Router::url('admin.backups.store') ?>" class="flex gap-2" onsubmit="this.querySelectorAll('button').forEach(b => b.disabled = true); this.querySelector('[data-busy]').hidden = false">
    <?= View::csrf() ?>
    <span data-busy hidden class="self-center text-sm text-muted-foreground">Yedek alınıyor…</span>
    <button name="type" value="db" class="btn btn-secondary">Sadece veritabanı</button>
    <button name="type" value="full" class="btn btn-primary">Yedek al</button>
  </form>
</div>

<div class="card overflow-x-auto">
  <table class="table">
    <thead><tr><th>Yedek</th><th>İçerik</th><th>Boyut</th><th>Tarih</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($backups as $b): ?>
        <tr>
          <td class="font-mono text-xs"><?= View::e($b['name']) ?></td>
          <td><span class="badge <?= $b['media'] ? 'badge-green' : 'badge-gray' ?>"><?= $b['media'] ? 'Veritabanı + dosyalar' : 'Veritabanı' ?></span></td>
          <td class="whitespace-nowrap"><?= $size($b['size']) ?></td>
          <td class="whitespace-nowrap text-muted-foreground"><?= View::date(date('Y-m-d H:i:s', $b['time']), true) ?></td>
          <td class="whitespace-nowrap text-right">
            <a href="<?= Router::url('admin.backups.download', ['name' => $b['name']]) ?>" class="btn btn-secondary btn-sm">İndir</a>
            <form method="post" action="<?= Router::url('admin.backups.delete', ['name' => $b['name']]) ?>" class="inline" onsubmit="return confirm('Bu yedek silinsin mi?')">
              <?= View::csrf() ?><button class="btn btn-ghost btn-sm text-destructive">Sil</button>
            </form>
          </td>
        </tr>
      <?php endforeach ?>
      <?php if (!$backups): ?><tr><td colspan="5" class="py-10 text-center text-muted-foreground">Henüz yedek yok.</td></tr><?php endif ?>
    </tbody>
  </table>
</div>

<div class="mt-6 grid gap-4 text-sm text-muted-foreground md:grid-cols-2">
  <div class="card p-5">
    <p class="font-medium text-foreground">Otomatik yedek</p>
    <p class="mt-1">Sunucunuzda zamanlanmış görev (cron) ekleyin; her gece 03:00'te tam yedek alır:</p>
    <code class="mt-2 block break-all rounded bg-muted px-2 py-1 font-mono text-xs text-foreground">0 3 * * * php <?= View::e(str_replace('\\', '/', ROOT)) ?>/modrix backup</code>
  </div>
  <div class="card p-5">
    <p class="font-medium text-foreground">Geri yükleme</p>
    <p class="mt-1">Mevcut veritabanının ve dosyaların üzerine yazar. Komut satırından:</p>
    <code class="mt-2 block break-all rounded bg-muted px-2 py-1 font-mono text-xs text-foreground">php modrix backup:restore storage/backups/yedek-….zip</code>
    <p class="mt-2">Yedekleri bilgisayarınıza da indirin; sunucu çökerse sunucudaki yedekler de gider.</p>
  </div>
</div>
