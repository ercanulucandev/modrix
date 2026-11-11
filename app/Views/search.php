<?php
// Site içi arama sonuçları. Tema ezebilir: app/Themes/{Tema}/views/search.php
// Değişkenler: $q, $results [['title', 'url', 'excerpt', 'type', 'date']]
$mark = function (string $text) use ($q): string {
    $safe = View::e($text);
    return $q === '' ? $safe : preg_replace('/(' . preg_quote(View::e($q), '/') . ')/iu', '<mark class="rounded bg-yellow-200/70 px-0.5 text-inherit">$1</mark>', $safe);
};
?>
<div class="mx-auto max-w-3xl">
    <h1 class="text-3xl font-semibold tracking-tight"><?= Lang::t('Arama') ?></h1>
    <form action="<?= Router::url('search') ?>" class="mt-6 flex gap-2" role="search">
      <input type="search" name="q" value="<?= View::e($q) ?>" placeholder="<?= View::e(Lang::t('Sitede ara…')) ?>" class="input h-11 flex-1 text-base" autofocus minlength="2" maxlength="100" aria-label="<?= View::e(Lang::t('Aranacak kelime')) ?>">
      <button class="btn btn-primary h-11 px-5"><?= Lang::t('Ara') ?></button>
    </form>

    <?php if ($q !== '' && mb_strlen($q) < 2): ?>
      <p class="mt-8 text-muted-foreground"><?= Lang::t('En az 2 harf yazın.') ?></p>
    <?php elseif ($q !== ''): ?>
      <p class="mt-8 text-sm text-muted-foreground"><?= count($results) ?> sonuç</p>
      <?php if (!$results): ?>
        <div class="mt-4 rounded-lg border border-dashed px-6 py-14 text-center text-muted-foreground">"<?= View::e($q) ?>" ile ilgili bir şey bulunamadı. Başka bir kelime deneyin.</div>
      <?php endif ?>
      <div class="mt-2 divide-y">
        <?php foreach ($results as $r): ?>
          <article class="py-5">
            <p class="text-xs text-muted-foreground"><?= View::e($r['type'] ?? '') ?><?= !empty($r['date']) ? ' · ' . View::date($r['date']) : '' ?></p>
            <h2 class="mt-1 text-lg font-semibold"><a href="<?= View::e($r['url']) ?>" class="hover:underline hover:underline-offset-4"><?= $mark($r['title']) ?></a></h2>
            <?php if (!empty($r['excerpt'])): ?><p class="mt-1.5 leading-7 text-muted-foreground"><?= $mark($r['excerpt']) ?></p><?php endif ?>
          </article>
        <?php endforeach ?>
      </div>
    <?php endif ?>
  </div>
