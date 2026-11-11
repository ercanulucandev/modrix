<?php
// Ayar formu: Admin > Ayarlar sekmeleri ve modül ayar sayfaları ortak kullanır.
// Değişkenler: $group (['label', 'description', 'fields', 'translatable']), $values (kayıtlı değerler), $action (form adresi)
// Çoklu dilde yazı alanlarının altında diğer diller için kutu çıkar (grupta 'translatable' => false değilse)
?>
<form method="post" action="<?= View::e($action) ?>" enctype="multipart/form-data" class="card min-w-0 flex-1">
  <?= View::csrf() ?>
  <div class="card-header border-b">
    <h2 class="card-title"><?= View::e($group['label']) ?></h2>
    <?php if (!empty($group['description'])): ?><p class="card-description"><?= View::e($group['description']) ?></p><?php endif ?>
  </div>

  <div class="space-y-6 p-6">
    <?= View::render('admin/settings/fields', ['fields' => $group['fields'], 'values' => $values, 'translate' => ($group['translatable'] ?? true) && Lang::enabled()]) ?>
  </div>

  <div class="card-footer justify-end">
    <button class="btn btn-primary">Kaydet</button>
  </div>
</form>

<script type="module" src="<?= View::e(View::asset('admin/settings-page.js')) ?>"></script>
