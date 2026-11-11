<?php // Yazı bloğu. $s: title, content, align, width, background ?>
<section class="py-14 sm:py-16 <?= $s['background'] === 'muted' ? 'bg-muted' : '' ?>">
  <div class="wrap">
    <div class="<?= $s['width'] === 'narrow' ? 'max-w-3xl' : '' ?> <?= $s['align'] === 'center' ? 'mx-auto text-center' : '' ?>">
      <?php if ($s['title'] !== ''): ?><h2 class="text-2xl font-semibold tracking-tight sm:text-3xl" <?= $edit('title') ?>><?= View::e($s['title']) ?></h2><?php endif ?>
      <?php if ($s['content'] !== ''): ?><div class="prose-content mt-5 <?= $s['width'] === 'wide' ? 'max-w-none' : '' ?>" <?= $edit('content') ?>><?= Html::render($s['content']) ?></div><?php endif ?>
    </div>
  </div>
</section>
