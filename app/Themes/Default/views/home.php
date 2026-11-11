<?php
// Varsayılan tema: ana sayfa. İçerik bölümlerden oluşur: Görünüm > Özelleştir > Ana sayfa bölümleri.
?>
<?= Section::render('home', ['moduleSections' => $sections]) ?>
