<?php // Başlangıç teması: özellik kartları. $s: title, text, items ?>
<section class="border-t py-16 sm:py-20">
  <div class="wrap">
    <div class="mx-auto max-w-2xl text-center">
      <h2 class="text-2xl font-semibold tracking-tight sm:text-3xl" <?= $edit('title') ?>><?= View::e($s['title']) ?></h2>
      <?php if ($s['text'] !== ''): ?><p class="mt-3 text-muted-foreground" <?= $edit('text') ?>><?= View::e($s['text']) ?></p><?php endif ?>
    </div>
    <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3" <?= $edit('items') ?>>
      <?php foreach ($s['items'] as $item): ?>
        <div class="card p-6">
          <span class="flex size-10 items-center justify-center rounded-md bg-primary/10 text-primary"><?= Icon::svg($item['icon'], 'size-5') ?></span>
          <h3 class="mt-4 font-semibold"><?= View::e($item['title']) ?></h3>
          <?php if ($item['text'] !== ''): ?><p class="mt-1.5 text-sm leading-6 text-muted-foreground"><?= nl2br(View::e($item['text'])) ?></p><?php endif ?>
        </div>
      <?php endforeach ?>
    </div>
  </div>
</section>
