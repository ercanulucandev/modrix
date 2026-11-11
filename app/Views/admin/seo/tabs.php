<?php // SEO sekmeleri. Değişken: $current ?>
<div class="mb-4">
  <h1 class="text-2xl font-semibold tracking-tight">SEO</h1>
  <p class="mt-1 text-sm text-muted-foreground">
    Yönlendirmeler ve kırık bağlantılar. Site haritası: <a href="<?= Request::url('sitemap.xml') ?>" target="_blank" class="underline underline-offset-4">/sitemap.xml</a>
    · <a href="<?= Request::url('robots.txt') ?>" target="_blank" class="underline underline-offset-4">/robots.txt</a>
    · başlık ve açıklama ayarları: <a href="<?= Request::url('admin/settings?tab=seo') ?>" class="underline underline-offset-4">Ayarlar &gt; SEO</a>
  </p>
</div>
<nav class="mb-6 flex gap-1 border-b">
  <?php foreach (['redirects' => ['Yönlendirmeler', 'admin/seo'], 'notfound' => ['Bulunamayanlar (404)', 'admin/seo/not-found']] as $key => [$label, $url]): ?>
    <a href="<?= Request::url($url) ?>" class="-mb-px border-b-2 px-3 pb-2.5 text-sm <?= $key === $current ? 'border-primary font-medium' : 'border-transparent text-muted-foreground hover:text-foreground' ?>"><?= $label ?></a>
  <?php endforeach ?>
</nav>
