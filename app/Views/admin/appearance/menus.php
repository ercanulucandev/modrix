<?php
// Görünüm > Menüler: iskelet. İçerik ve kayıt API ile (app/Assets/admin/menus-page.js, MenuApiController).
// Değişken: $theme (etkin temanın manifesti)
?>
<?= View::render('admin/appearance/tabs', ['current' => 'menus']) ?>

<div class="grid items-start gap-6 lg:grid-cols-[20rem_minmax(0,1fr)]" data-menus-page>
  <!-- Sol: menü seçimi, bağlantı ekleme, konumlar -->
  <div class="space-y-4">
    <div class="card p-4">
      <label class="label" for="menu-select">Düzenlenen menü</label>
      <div class="mt-2 flex gap-2">
        <select id="menu-select" class="input mt-0" data-ref="select"></select>
        <button type="button" class="btn btn-secondary shrink-0" data-ref="create" title="Yeni menü">Yeni</button>
      </div>
    </div>

    <div class="card" data-ref="addPanel">
      <div class="flex border-b text-sm" role="tablist">
        <button type="button" class="flex-1 border-b-2 border-primary px-3 py-2.5 font-medium" data-tab="links">Hazır bağlantılar</button>
        <button type="button" class="flex-1 border-b-2 border-transparent px-3 py-2.5 text-muted-foreground" data-tab="custom">Özel bağlantı</button>
      </div>
      <div class="p-4" data-pane="links">
        <div class="max-h-72 space-y-4 overflow-y-auto overflow-x-hidden pr-1" data-ref="links"></div>
        <button type="button" class="btn btn-secondary btn-sm mt-4 w-full" data-ref="addLinks">Seçilenleri menüye ekle</button>
      </div>
      <form class="hidden space-y-3 p-4" data-pane="custom" data-ref="customForm" novalidate>
        <div>
          <label class="label" for="custom-label">Görünen ad</label>
          <input id="custom-label" class="input" maxlength="100" placeholder="Hakkımızda" data-ref="customLabel">
        </div>
        <div>
          <label class="label" for="custom-url">Bağlantı</label>
          <input id="custom-url" class="input font-mono text-xs" maxlength="500" placeholder="hakkimizda, https://..., #iletisim, tel:..." data-ref="customUrl">
          <p class="field-help text-xs">Site içi için sadece yol (ör. <code>blog</code>), dış site için tam adres.</p>
        </div>
        <button class="btn btn-secondary btn-sm w-full">Menüye ekle</button>
      </form>
    </div>

    <div class="card">
      <div class="card-header border-b py-4">
        <h2 class="card-title text-sm">Menü konumları</h2>
        <p class="card-description text-xs"><?= View::e($theme['name']) ?> temasının menüleri gösterdiği yerler.</p>
      </div>
      <div class="space-y-4 p-4" data-ref="locations"></div>
      <div class="card-footer justify-end px-4 py-3"><button type="button" class="btn btn-secondary btn-sm" data-ref="saveLocations">Konumları kaydet</button></div>
    </div>
  </div>

  <!-- Sağ: menü yapısı -->
  <div class="card min-w-0" data-ref="editor">
    <div class="flex flex-wrap items-end gap-3 border-b p-4">
      <div class="min-w-48 flex-1">
        <label class="label" for="menu-name">Menü adı</label>
        <input id="menu-name" class="input" maxlength="100" data-ref="name">
      </div>
      <button type="button" class="btn btn-danger-ghost" data-ref="delete">Sil</button>
      <button type="button" class="btn btn-primary" data-ref="save">Menüyü kaydet</button>
    </div>
    <div class="p-4">
      <p class="mb-4 text-sm text-muted-foreground">Sürükleyerek sıralayın; bir öğeyi diğerinin altına, biraz sağa bırakınca alt menü olur (en fazla 3 seviye).</p>
      <ul class="space-y-2" data-ref="tree"></ul>
      <div class="hidden rounded-lg border border-dashed px-6 py-12 text-center text-sm text-muted-foreground" data-ref="empty">Menü boş. Soldan bağlantı ekleyin.</div>
    </div>
  </div>

  <div class="hidden rounded-lg border border-dashed px-6 py-16 text-center lg:col-start-2 lg:row-start-1" data-ref="noMenu">
    <p class="font-medium">Henüz menü yok</p>
    <p class="mt-1 text-sm text-muted-foreground">İlk menüyü oluşturun; temanın boş bir konumuna kendiliğinden atanır.</p>
    <button type="button" class="btn btn-primary mt-5" data-ref="createFirst">Menü oluştur</button>
  </div>
</div>

<script type="module" src="<?= View::e(View::asset('admin/menus-page.js')) ?>"></script>
