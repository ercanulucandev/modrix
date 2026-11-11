<?php // Koleksiyon listesi. Değişkenler: $name, $def, $items
$base = "admin/collections/$name";
?>
<div class="mb-6 flex flex-wrap items-start justify-between gap-4">
  <div>
    <h1 class="text-2xl font-semibold tracking-tight"><?= View::e($def['label']) ?></h1>
    <?php if (!empty($def['description'])): ?><p class="mt-1 text-sm text-muted-foreground"><?= View::e($def['description']) ?></p><?php endif ?>
  </div>
  <a href="<?= Request::url("$base/create") ?>" class="btn btn-primary"><?= Icon::svg('plus', 'size-4') ?: '+' ?> <?= View::e($def['singular']) ?> ekle</a>
</div>

<?php if (!$items): ?>
  <div class="card px-6 py-16 text-center">
    <p class="font-medium">Henüz <?= View::e(mb_strtolower($def['singular'])) ?> yok</p>
    <p class="mt-1 text-sm text-muted-foreground">İlkini ekleyin; sitede kendiliğinden görünür.</p>
  </div>
<?php else: ?>
  <p class="mb-2 text-xs text-muted-foreground">Sırayı değiştirmek için satırları tutup sürükleyin.</p>
  <div class="card divide-y" data-collection-list data-sort-url="<?= View::e(Request::url("$base/sort")) ?>">
    <?php foreach ($items as $item): $img = $def['image'] ? App\Services\Collection::imageUrl(is_array($item[$def['image']] ?? null) ? ($item[$def['image']][0] ?? null) : ($item[$def['image']] ?? null)) : null; ?>
      <div class="flex items-center gap-3 px-4 py-3" data-id="<?= $item['id'] ?>">
        <span class="cursor-grab select-none text-muted-foreground" data-handle title="Sürükle">⋮⋮</span>
        <?php if ($def['image']): ?>
          <div class="size-10 shrink-0 overflow-hidden rounded-md border bg-muted"><?php if ($img): ?><img src="<?= View::e($img) ?>" alt="" class="size-full object-cover"><?php endif ?></div>
        <?php endif ?>
        <a href="<?= Request::url("$base/{$item['id']}/edit") ?>" class="min-w-0 flex-1">
          <span class="block truncate font-medium <?= $item['visible'] ? '' : 'text-muted-foreground line-through' ?>"><?= View::e($item['title']) ?></span>
          <?php if ($def['subtitle'] && !empty($item[$def['subtitle']])): ?><span class="block truncate text-xs text-muted-foreground"><?= View::e((string) $item[$def['subtitle']]) ?></span><?php endif ?>
        </a>
        <?php if (!$item['visible']): ?><span class="badge badge-gray">Gizli</span><?php endif ?>
        <form method="post" action="<?= Request::url("$base/{$item['id']}/toggle") ?>"><?= View::csrf() ?>
          <button class="btn btn-ghost btn-sm"><?= $item['visible'] ? 'Gizle' : 'Göster' ?></button></form>
        <a href="<?= Request::url("$base/{$item['id']}/edit") ?>" class="btn btn-secondary btn-sm">Düzenle</a>
      </div>
    <?php endforeach ?>
  </div>
<?php endif ?>

<script type="module" src="<?= View::e(View::asset('admin/collections-page.js')) ?>"></script>
