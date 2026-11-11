<?php
// Genel bakış. Değişkenler: $admin, $actions, $todos, $stats, $series, $widgets, $activity, $system
use App\Services\Dashboard;
$hour = (int) date('G');
$greeting = $hour < 6 ? 'İyi geceler' : ($hour < 12 ? 'Günaydın' : ($hour < 18 ? 'İyi günler' : 'İyi akşamlar'));
$months = ['', 'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
$days = ['', 'Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi', 'Pazar'];
$icon = fn(string $name) => Icon::svg($name, 'size-4');
?>
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
  <div>
    <p class="text-sm text-muted-foreground"><?= date('j') . ' ' . $months[(int) date('n')] . ' ' . date('Y') . ', ' . $days[(int) date('N')] ?></p>
    <h1 class="mt-1 text-2xl font-semibold tracking-tight"><?= $greeting ?>, <?= View::e(explode(' ', $admin['name'])[0]) ?></h1>
  </div>
  <?php if ($actions): ?>
    <div class="flex flex-wrap gap-2">
      <?php foreach ($actions as $i => $a): ?>
        <a href="<?= Request::url($a['url']) ?>" class="btn <?= $i === 0 ? 'btn-primary' : 'btn-secondary' ?> btn-sm"><?= $icon($a['icon'] ?? 'plus') ?><?= View::e($a['label']) ?></a>
      <?php endforeach ?>
    </div>
  <?php endif ?>
</div>

<?php if ($todos): ?>
  <section class="card mb-6 divide-y">
    <p class="px-5 py-3 text-sm font-semibold">Dikkat isteyenler</p>
    <?php foreach ($todos as $t): $warn = ($t['tone'] ?? 'info') === 'warning'; ?>
      <a href="<?= Request::url($t['url']) ?>" class="flex items-center gap-3 px-5 py-3 text-sm hover:bg-muted/50">
        <span class="size-2 shrink-0 rounded-full <?= $warn ? 'bg-amber-500' : 'bg-sky-500' ?>"></span>
        <span class="flex-1"><?= View::e($t['text']) ?></span>
        <span class="text-muted-foreground">→</span>
      </a>
    <?php endforeach ?>
  </section>
<?php endif ?>

<?php if ($stats): ?>
  <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
    <?php foreach ($stats as $s): $url = $s['url'] ?? null; ?>
      <<?= $url ? 'a href="' . Request::url($url) . '"' : 'div' ?> class="card block p-5 <?= $url ? 'transition-colors hover:bg-muted/40' : '' ?>">
        <p class="text-sm text-muted-foreground"><?= View::e($s['label']) ?></p>
        <p class="mt-1.5 text-2xl font-semibold tracking-tight tabular-nums"><?= is_numeric($s['value']) ? number_format($s['value'], 0, ',', '.') : View::e($s['value']) ?></p>
        <?php if (!empty($s['hint'])): ?><p class="mt-1 text-xs text-muted-foreground"><?= View::e($s['hint']) ?></p><?php endif ?>
      </<?= $url ? 'a' : 'div' ?>>
    <?php endforeach ?>
  </div>
<?php endif ?>

<?php if ($series): ?>
  <section class="card mt-6 p-5" data-chart>
    <div class="flex flex-wrap items-center justify-between gap-3">
      <p class="font-semibold">Son 30 gün</p>
      <div class="flex flex-wrap gap-1" role="tablist">
        <?php foreach ($series as $i => $s): ?>
          <button type="button" role="tab" class="rounded-md px-3 py-1.5 text-sm text-muted-foreground hover:text-foreground aria-selected:bg-accent aria-selected:font-medium aria-selected:text-foreground" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>" data-series="<?= $i ?>"><?= View::e($s['label']) ?></button>
        <?php endforeach ?>
      </div>
    </div>
    <?php foreach ($series as $i => $s): $total = array_sum($s['points']); $format = $s['format'] ?? 'count'; $prev = $s['previous'] ?? null; ?>
      <div class="mt-4" data-panel="<?= $i ?>" <?= $i ? 'hidden' : '' ?>>
        <p class="flex flex-wrap items-baseline gap-2">
          <span class="text-3xl font-semibold tracking-tight tabular-nums"><?= Dashboard::format($total, $format) ?></span>
          <?php if ($prev !== null): $diff = $prev > 0 ? round(($total - $prev) * 100 / $prev) : null; ?>
            <span class="text-sm <?= $diff === null ? 'text-muted-foreground' : ($diff >= 0 ? 'text-emerald-600' : 'text-red-600') ?>">
              <?= $diff === null ? 'önceki 30 gün: ' . Dashboard::format($prev, $format) : ($diff >= 0 ? '▲ %' . $diff : '▼ %' . abs($diff)) . ' önceki 30 güne göre' ?></span>
          <?php endif ?>
        </p>
        <div class="mt-4 text-primary"><?= Dashboard::chart($s['points'], $format) ?></div>
      </div>
    <?php endforeach ?>
  </section>
  <script>
    document.querySelector('[data-chart]').addEventListener('click', (e) => {
      const tab = e.target.closest('[data-series]'); if (!tab) return;
      document.querySelectorAll('[data-series]').forEach((t) => t.setAttribute('aria-selected', t === tab));
      document.querySelectorAll('[data-panel]').forEach((p) => { p.hidden = p.dataset.panel !== tab.dataset.series; });
    });
  </script>
<?php endif ?>

<div class="mt-6 grid items-start gap-6 lg:grid-cols-2">
  <?php foreach ($widgets as $w): ?>
    <div class="<?= ($w['size'] ?? 'half') === 'full' ? 'lg:col-span-2' : '' ?>"><?= $w['html'] ?></div>
  <?php endforeach ?>

  <?php if ($activity): ?>
    <section class="card">
      <div class="flex items-center justify-between border-b px-5 py-4">
        <h2 class="card-title">Son işlemler</h2>
        <a href="<?= Request::url('admin/activity') ?>" class="btn btn-secondary btn-sm">Tümü</a>
      </div>
      <ul class="divide-y text-sm">
        <?php foreach ($activity as $r): ?>
          <li class="flex gap-3 px-5 py-3">
            <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-semibold"><?= View::e(mb_strtoupper(mb_substr((string) ($r['user_name'] ?: '?'), 0, 1))) ?></span>
            <span class="min-w-0 flex-1"><span class="font-medium"><?= View::e((string) ($r['user_name'] ?: 'Sistem')) ?></span> <span class="text-muted-foreground"><?= View::e(mb_strtolower(mb_substr($r['description'], 0, 1)) . mb_substr($r['description'], 1)) ?></span>
              <span class="block text-xs text-muted-foreground"><?= View::date($r['created_at'], true) ?></span></span>
          </li>
        <?php endforeach ?>
      </ul>
    </section>
  <?php endif ?>

  <?php if ($system): ?>
    <section class="card">
      <div class="border-b px-5 py-4"><h2 class="card-title">Sistem</h2></div>
      <dl class="divide-y text-sm">
        <?php foreach ($system as $k => $v): ?><div class="flex justify-between gap-4 px-5 py-2.5"><dt class="text-muted-foreground"><?= View::e($k) ?></dt><dd class="text-right"><?= View::e((string) $v) ?></dd></div><?php endforeach ?>
      </dl>
    </section>
  <?php endif ?>
</div>
