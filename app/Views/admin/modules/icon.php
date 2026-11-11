<?php
// Modül görseli: icon.svg|png|webp|jpg varsa o, yoksa addan türeyen renkli baş harf kutusu.
// Değişkenler: $module (Module::all() öğesi), $size ('size-12' gibi Tailwind sınıfı)
$size ??= 'size-12';
if (!$module['icon']) {
    $words = preg_split('/[^\p{L}\p{N}]+|(?<=\p{Ll})(?=\p{Lu})/u', $module['name'], -1, PREG_SPLIT_NO_EMPTY);
    $initials = mb_strtoupper(mb_substr($words[0] ?? '?', 0, 1) . mb_substr($words[1] ?? '', 0, 1));
    $hue = crc32($module['folder']) % 360;
}
?>
<?php if ($module['icon']): ?>
  <img src="<?= View::e($module['icon']) ?>" alt="" class="<?= $size ?> shrink-0 rounded-lg border bg-background object-cover">
<?php else: ?>
  <span class="<?= $size ?> flex shrink-0 items-center justify-center rounded-lg text-sm font-semibold text-white"
        style="background: linear-gradient(135deg, hsl(<?= $hue ?> 65% 52%), hsl(<?= ($hue + 40) % 360 ?> 70% 40%))" aria-hidden="true"><?= View::e($initials) ?></span>
<?php endif ?>
