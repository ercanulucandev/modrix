<?php // Görsel ve yazı. $s: image, title, text, button_text, button_link, side, background ?>
<section class="py-14 sm:py-20 <?= $s['background'] === 'muted' ? 'bg-muted' : '' ?>">
  <div class="wrap grid items-center gap-10 md:grid-cols-2 lg:gap-16">
    <div class="<?= $s['side'] === 'right' ? 'md:order-2' : '' ?>" <?= $edit('image') ?>>
      <?php if ($s['image']): ?>
        <img src="<?= View::e(Request::url('media/' . $s['image'])) ?>" alt="" class="aspect-[4/3] w-full rounded-xl border object-cover" loading="lazy">
      <?php else: ?>
        <div class="flex aspect-[4/3] w-full items-center justify-center rounded-xl border border-dashed bg-muted text-muted-foreground"><?= Icon::svg('image', 'size-10') ?></div>
      <?php endif ?>
    </div>
    <div>
      <h2 class="text-2xl font-semibold tracking-tight sm:text-3xl" <?= $edit('title') ?>><?= View::e($s['title']) ?></h2>
      <?php if ($s['text'] !== ''): ?>
        <div class="mt-5 space-y-4 leading-7 text-muted-foreground" <?= $edit('text') ?>>
          <?php foreach (preg_split('/\R+/', $s['text']) as $p): ?><p><?= View::e($p) ?></p><?php endforeach ?>
        </div>
      <?php endif ?>
      <?php if ($s['button_text'] !== '' && $s['button_link'] !== ''): ?>
        <a href="<?= View::e(Menu::href($s['button_link'])) ?>" class="btn btn-primary mt-8" <?= $edit('button_text') ?>><?= View::e($s['button_text']) ?></a>
      <?php endif ?>
    </div>
  </div>
</section>
