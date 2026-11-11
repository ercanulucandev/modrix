<?php // Koleksiyon öğesi formu. Değişkenler: $name, $def, $item (yeni ise null)
$base = "admin/collections/$name";
$action = $item ? Request::url("$base/{$item['id']}") : Request::url($base);
?>
<div class="mb-6 flex items-center gap-2">
  <a href="<?= Request::url($base) ?>" class="btn btn-ghost size-9 p-0" aria-label="Listeye dön" title="Listeye dön">
    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
  </a>
  <h1 class="text-2xl font-semibold tracking-tight"><?= $item ? View::e($item['title']) : 'Yeni ' . View::e(mb_strtolower($def['singular'])) ?></h1>
</div>

<div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_18rem]">
  <form method="post" action="<?= $action ?>" class="card space-y-6 p-6" id="collection-form">
    <?= View::csrf() ?>
    <?= View::render('admin/settings/fields', ['fields' => $def['fields'], 'values' => $item ?? []]) ?>
    <?php if ($def['slug']): ?>
      <?= View::render('partials/field', ['name' => '_slug', 'label' => 'Adres', 'value' => $item['slug'] ?? '', 'help' => 'Boş bırakırsanız başlıktan üretilir.' . (!empty($def['url']) && $item ? ' Şu an: /' . $def['url']($item['slug']) : '')]) ?>
    <?php endif ?>
    <label class="flex items-center gap-2 text-sm"><input type="hidden" name="_visible" value="0"><input type="checkbox" name="_visible" value="1" class="size-4" <?= ($item['visible'] ?? true) ? 'checked' : '' ?>> Sitede göster</label>
    <div class="flex flex-wrap justify-end gap-2 border-t pt-5">
      <?php if (!$item): ?><button name="_next" value="new" class="btn btn-secondary">Kaydet ve yenisini ekle</button><?php endif ?>
      <button class="btn btn-primary"><?= $item ? 'Kaydet' : 'Ekle' ?></button>
    </div>
  </form>

  <?php if ($item): ?>
    <aside class="card space-y-3 p-5 text-sm">
      <p><span class="text-muted-foreground">Eklenme:</span> <?= View::date($item['created_at'], true) ?></p>
      <p><span class="text-muted-foreground">Güncelleme:</span> <?= View::date($item['updated_at'], true) ?></p>
      <?php if (!empty($def['url']) && $item['slug']): ?><a href="<?= Request::url($def['url']($item['slug'])) ?>" target="_blank" class="btn btn-secondary btn-sm w-full">Sitede gör</a><?php endif ?>
      <form method="post" action="<?= Request::url("$base/{$item['id']}/delete") ?>" onsubmit="return confirm('Silinsin mi? Geri alınamaz.')"><?= View::csrf() ?>
        <button class="btn btn-danger-ghost btn-sm w-full">Sil</button></form>
    </aside>
  <?php endif ?>
</div>
<script type="module" src="<?= View::e(View::asset('admin/settings-page.js')) ?>"></script>
