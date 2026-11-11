<?php // Çağrı bandı. $s: title, text, button_text, button_link, style
$box = ['primary' => 'bg-primary text-primary-foreground', 'muted' => 'bg-muted', 'outline' => 'border bg-card'][$s['style']] ?? 'bg-primary text-primary-foreground';
?>
<section class="py-12 sm:py-16">
  <div class="wrap">
    <div class="flex flex-col items-start justify-between gap-6 rounded-xl px-6 py-10 sm:px-10 md:flex-row md:items-center <?= $box ?>">
      <div class="max-w-2xl">
        <h2 class="text-2xl font-semibold tracking-tight sm:text-3xl" <?= $edit('title') ?>><?= View::e($s['title']) ?></h2>
        <?php if ($s['text'] !== ''): ?><p class="mt-2 leading-7 opacity-80" <?= $edit('text') ?>><?= nl2br(View::e($s['text'])) ?></p><?php endif ?>
      </div>
      <?php if ($s['button_text'] !== '' && $s['button_link'] !== ''): ?>
        <a href="<?= View::e(Menu::href($s['button_link'])) ?>" <?= $edit('button_text') ?>
           class="btn btn-lg shrink-0 <?= $s['style'] === 'primary' ? 'bg-primary-foreground text-primary hover:opacity-90' : 'btn-primary' ?>"><?= View::e($s['button_text']) ?></a>
      <?php endif ?>
    </div>
  </div>
</section>
