<?php
// Sayfa numaraları: echo View::render('partials/pagination', ['page' => $posts]);
// $page: DB::paginate() / Model::paginate() / ->paginate() sonucu. Tek sayfa varsa hiçbir şey göstermez.
// Adresteki diğer parametreler (ör. ?q=arama) korunur.
if ($page['pages'] <= 1) return;

$current = $page['page'];
$last    = $page['pages'];
$link    = function (int $n): string {
    $query = array_merge($_GET, ['page' => $n]);
    if ($n === 1) unset($query['page']);
    return Request::url(Request::path()) . ($query ? '?' . http_build_query($query) : '');
};

// Gösterilecek numaralar: 1 … 4 5 [6] 7 8 … 20
$numbers = [];
foreach ([1, $last, ...range(max(1, $current - 2), min($last, $current + 2))] as $n) $numbers[$n] = true;
ksort($numbers);

$item   = 'inline-flex h-9 min-w-9 items-center justify-center gap-1 rounded-md px-2.5 text-sm font-medium transition-colors';
$normal = "$item hover:bg-accent hover:text-accent-foreground";
$active = "$item border border-input bg-background shadow-xs";
$off    = "$item pointer-events-none opacity-50";
$chev   = fn(string $d) => '<svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="' . $d . '"/></svg>';
?>
<nav class="mt-6 flex flex-col items-center justify-between gap-3 sm:flex-row" aria-label="<?= View::e(Lang::t('Sayfalama')) ?>">
  <p class="text-sm text-muted-foreground">
    <?= number_format($page['total'], 0, ',', '.') ?> kayıttan <span class="font-medium text-foreground"><?= $page['from'] ?>–<?= $page['to'] ?></span> <?= Lang::t('arası') ?>
  </p>

  <div class="flex flex-wrap items-center gap-1">
    <?php if ($current > 1): ?>
      <a href="<?= View::e($link($current - 1)) ?>" class="<?= $normal ?>" rel="prev"><?= $chev('m15 18-6-6 6-6') ?><span class="hidden sm:inline"><?= Lang::t('Önceki') ?></span></a>
    <?php else: ?>
      <span class="<?= $off ?>"><?= $chev('m15 18-6-6 6-6') ?><span class="hidden sm:inline"><?= Lang::t('Önceki') ?></span></span>
    <?php endif ?>

    <?php $prev = 0; foreach (array_keys($numbers) as $n): ?>
      <?php if ($n - $prev > 1): ?><span class="flex size-9 items-center justify-center text-muted-foreground">…</span><?php endif ?>
      <?php if ($n === $current): ?>
        <span class="<?= $active ?>" aria-current="page"><?= $n ?></span>
      <?php else: ?>
        <a href="<?= View::e($link($n)) ?>" class="<?= $normal ?>"><?= $n ?></a>
      <?php endif ?>
    <?php $prev = $n; endforeach ?>

    <?php if ($current < $last): ?>
      <a href="<?= View::e($link($current + 1)) ?>" class="<?= $normal ?>" rel="next"><span class="hidden sm:inline"><?= Lang::t('Sonraki') ?></span><?= $chev('m9 18 6-6-6-6') ?></a>
    <?php else: ?>
      <span class="<?= $off ?>"><span class="hidden sm:inline"><?= Lang::t('Sonraki') ?></span><?= $chev('m9 18 6-6-6-6') ?></span>
    <?php endif ?>
  </div>
</nav>
