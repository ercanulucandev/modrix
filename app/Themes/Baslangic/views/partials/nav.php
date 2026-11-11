<?php
// Üst menü (alt menülü). Masaüstünde üzerine gelince açılır, mobilde iç içe liste.
// Değişkenler: $items (Menu::location()), $depth (iç içe çağrıda)
$depth ??= 0;
$link = fn(array $item) => $item['active'] ? 'font-medium text-foreground' : 'text-muted-foreground hover:text-foreground';
?>
<?php foreach ($items as $item): $target = $item['new_tab'] ? ' target="_blank" rel="noopener"' : ''; ?>
  <?php if (!$item['children']): ?>
    <a href="<?= View::e($item['href']) ?>"<?= $target ?>
       class="<?= $depth ? 'block rounded-md px-3 py-2 md:whitespace-nowrap md:hover:bg-accent' : 'rounded-md px-2 py-1.5 md:px-0' ?> text-sm transition-colors <?= $link($item) ?>"><?= View::e($item['label']) ?></a>
  <?php else: ?>
    <div class="group/nav relative">
      <a href="<?= View::e($item['href']) ?>"<?= $target ?>
         class="flex items-center gap-1 <?= $depth ? 'rounded-md px-3 py-2 md:whitespace-nowrap md:hover:bg-accent' : 'rounded-md px-2 py-1.5 md:px-0' ?> text-sm transition-colors <?= $link($item) ?>">
        <?= View::e($item['label']) ?>
        <svg class="size-3.5 opacity-60 <?= $depth ? 'md:-rotate-90' : '' ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
      </a>
      <?php // Alt menü: masaüstünde açılır kutu (üstte boşluk bırakmadan, fare kaçmasın diye pt) ?>
      <div class="ml-3 border-l pl-2 md:invisible md:absolute md:z-50 md:ml-0 md:border-l-0 md:opacity-0 md:transition
                  md:group-hover/nav:visible md:group-hover/nav:opacity-100 md:group-focus-within/nav:visible md:group-focus-within/nav:opacity-100
                  <?= $depth ? 'md:left-full md:top-0 md:pl-1' : 'md:left-1/2 md:top-full md:-translate-x-1/2 md:pl-0 md:pt-3' ?>">
        <div class="md:min-w-48 md:rounded-lg md:border md:bg-background md:p-1 md:shadow-lg">
          <?= View::render('partials/nav', ['items' => $item['children'], 'depth' => $depth + 1]) ?>
        </div>
      </div>
    </div>
  <?php endif ?>
<?php endforeach ?>
