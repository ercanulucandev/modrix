<?php // Sitede dil seçici (temaların üst kısmında, site.header_actions). Sadece birden çok dil varsa görünür.
$alternates = Lang::alternates();
$current = Lang::current();
?>
<details class="relative text-sm" data-language-switcher>
  <summary class="flex cursor-pointer list-none items-center gap-1 font-medium uppercase opacity-80 hover:opacity-100 [&::-webkit-details-marker]:hidden" aria-label="Dil: <?= View::e(Lang::name($current)) ?>">
    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/></svg>
    <?= View::e($current) ?>
  </summary>
  <div class="absolute right-0 z-50 mt-2 min-w-40 rounded-lg border bg-white p-1 text-slate-800 shadow-lg">
    <?php foreach ($alternates as $code => $url): ?>
      <a href="<?= View::e($url) ?>" hreflang="<?= $code ?>" lang="<?= $code ?>" class="block rounded-md px-3 py-2 normal-case hover:bg-slate-100 <?= $code === $current ? 'font-semibold' : '' ?>"><?= View::e(Lang::name($code)) ?></a>
    <?php endforeach ?>
  </div>
</details>
