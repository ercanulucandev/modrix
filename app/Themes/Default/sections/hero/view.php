<?php
// Varsayılan tema: giriş
$text = $s['text'];
$tagline = $text === '' ? Setting::get('site_tagline') : null;
$intro = $text === '' ? Setting::get('site_description') : null;
?>
<section class="border-b">
  <div class="wrap grid items-center gap-10 pb-12 pt-14 <?= $s['image'] ? 'lg:grid-cols-2' : '' ?>">
    <div>
      <h1 class="text-4xl font-semibold tracking-tight sm:text-5xl" <?= $edit('title') ?>><?= View::e($s['title'] ?: Setting::get('site_name', Config::get('app.name'))) ?></h1>
      <?php if ($text !== ''): ?>
        <p class="mt-4 max-w-2xl text-lg leading-8 text-muted-foreground" <?= $edit('text') ?>><?= nl2br(View::e($text)) ?></p>
      <?php else: ?>
        <?php if ($tagline): ?><p class="mt-4 max-w-2xl text-lg text-muted-foreground" <?= $edit('text') ?>><?= View::e($tagline) ?></p><?php endif ?>
        <?php if ($intro): ?><p class="mt-3 max-w-2xl leading-7 text-foreground/80"><?= nl2br(View::e($intro)) ?></p><?php endif ?>
      <?php endif ?>
      <?php if ($s['button_text'] !== '' && $s['button_link'] !== ''): ?>
        <a href="<?= View::e(Menu::href($s['button_link'])) ?>" class="btn btn-primary btn-lg mt-8" <?= $edit('button_text') ?>><?= View::e($s['button_text']) ?></a>
      <?php endif ?>
    </div>
    <?php if ($s['image']): ?>
      <img src="<?= View::e(Request::url('media/' . $s['image'])) ?>" alt="" class="aspect-[4/3] w-full rounded-xl border object-cover" <?= $edit('image') ?>>
    <?php endif ?>
  </div>
</section>
