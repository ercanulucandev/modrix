<?php
// Modüllerin 'site.home' içerikleri. Ana sayfa görünümü bunları $context['moduleSections'] olarak verir.
$items = $context['moduleSections'] ?? [];
if (!$items) return;
?>
<section class="py-14 sm:py-16">
  <div class="wrap"><?php foreach ($items as $item): ?><?= $item['html'] ?><?php endforeach ?></div>
</section>
