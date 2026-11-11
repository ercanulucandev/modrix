<?php
// Görünüm > Özelleştir: tam ekran canlı düzenleyici. Solda açılır-kapanır panel (bölümler kayarak açılır),
// sağda sitenin önizlemesi. Davranış: app/Assets/admin/customizer.js
// Değişkenler: $folder, $theme, $themes, $isActive, $sections, $values, $dirty, $sectionData (bölüm alanları ve türleri)
$devices = [
    'desktop' => ['Masaüstü', '100%', '<rect width="20" height="14" x="2" y="3" rx="2"/><line x1="8" x2="16" y1="21" y2="21"/><line x1="12" x2="12" y1="17" y2="21"/>'],
    'tablet'  => ['Tablet', '768px', '<rect width="16" height="20" x="4" y="2" rx="2"/><line x1="12" x2="12.01" y1="18" y2="18"/>'],
    'mobile'  => ['Mobil', '390px', '<rect width="14" height="20" x="5" y="2" rx="2"/><path d="M12 18h.01"/>'],
];
$svg = fn(string $paths, string $class = 'size-4') => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths . '</svg>';
$chevronRight = '<path d="m9 18 6-6-6-6"/>';
$chevronLeft  = '<path d="m15 18-6-6 6-6"/>';
?>
<div class="flex h-dvh" data-customizer
     data-draft-url="<?= Router::url('admin.appearance.customize.draft', ['name' => $folder]) ?>"
     data-publish-url="<?= Router::url('admin.appearance.customize.publish', ['name' => $folder]) ?>"
     data-customize-url="<?= Request::url('admin/appearance/customize') ?>"
     data-active="<?= $isActive ? '1' : '' ?>" data-dirty="<?= $dirty ? '1' : '' ?>">

  <!-- ============ Panel (offcanvas) ============ -->
  <aside class="relative z-20 flex w-full shrink-0 flex-col border-r bg-sidebar shadow-xl transition-[margin] duration-300 ease-out sm:w-[22rem]" data-panel>
    <!-- Üst çubuk -->
    <div class="flex h-14 shrink-0 items-center gap-2 border-b bg-background px-2.5">
      <a href="<?= Request::url('admin/appearance') ?>" class="btn btn-ghost size-9 shrink-0 p-0" title="Kapat" aria-label="Özelleştirmeyi kapat" data-close>
        <?= $svg('<path d="M18 6 6 18"/><path d="m6 6 12 12"/>') ?>
      </a>
      <div class="min-w-0 flex-1 leading-tight">
        <p class="text-xs text-muted-foreground" data-status><?= $dirty ? 'Yayınlanmamış değişiklikler var' : 'Özelleştiriliyor' ?></p>
        <p class="truncate text-sm font-semibold"><?= View::e($theme['name']) ?></p>
      </div>
      <button type="button" class="btn btn-primary btn-sm" data-publish <?= $dirty || !$isActive ? '' : 'disabled' ?>>
        <?= $isActive ? 'Yayınla' : 'Etkinleştir ve yayınla' ?>
      </button>
    </div>

    <!-- Kayan paneller -->
    <form class="relative min-h-0 flex-1 overflow-hidden" novalidate data-form>
      <!-- Kök: tema + bölümler -->
      <div class="absolute inset-0 overflow-y-auto transition-transform duration-300 ease-out" data-view="root">
        <div class="border-b p-4">
          <p class="text-xs font-medium uppercase tracking-wider text-muted-foreground">Etkin tema</p>
          <div class="mt-2 flex items-center justify-between gap-3">
            <div class="min-w-0">
              <p class="truncate font-semibold"><?= View::e($theme['name']) ?></p>
              <?php if (!$isActive): ?><p class="text-xs text-amber-700">Önizleniyor · henüz yayında değil</p><?php endif ?>
            </div>
            <label class="btn btn-secondary btn-sm relative shrink-0">
              Değiştir
              <select class="absolute inset-0 cursor-pointer opacity-0" aria-label="Tema değiştir" data-theme-select>
                <?php foreach ($themes as $key => $t): ?>
                  <option value="<?= View::e($key) ?>" <?= $key === $folder ? 'selected' : '' ?>><?= View::e($t['name']) ?><?= $t['active'] ? ' (etkin)' : '' ?></option>
                <?php endforeach ?>
              </select>
            </label>
          </div>
        </div>

        <?php if ($sectionData['areas']): ?>
          <nav class="divide-y border-b">
            <?php foreach ($sectionData['areas'] as $area => $a): ?>
              <button type="button" class="flex w-full items-center gap-3 bg-background px-4 py-3.5 text-left text-sm transition-colors hover:bg-accent" data-open="area-<?= View::e($area) ?>">
                <span class="flex size-8 shrink-0 items-center justify-center rounded-md bg-primary text-primary-foreground"><?= $svg('<rect width="18" height="7" x="3" y="3" rx="1"/><rect width="18" height="7" x="3" y="14" rx="1"/>') ?></span>
                <span class="min-w-0 flex-1">
                  <span class="block font-medium"><?= View::e($a['label']) ?></span>
                  <span class="mt-0.5 block text-xs text-muted-foreground">Sürükleyerek sıralayın, yeni bölüm ekleyin</span>
                </span>
                <?= $svg($chevronRight, 'size-4 shrink-0 text-muted-foreground') ?>
              </button>
            <?php endforeach ?>
          </nav>
          <p class="px-4 pb-1 pt-4 text-xs font-medium uppercase tracking-wider text-muted-foreground">Genel ayarlar</p>
        <?php endif ?>

        <nav class="divide-y border-y">
          <?php foreach ($sections as $key => $s): ?>
            <button type="button" class="flex w-full items-center gap-3 bg-background px-4 py-3.5 text-left text-sm transition-colors hover:bg-accent" data-open="<?= View::e($key) ?>">
              <span class="min-w-0 flex-1">
                <span class="block font-medium"><?= View::e($s['label']) ?></span>
                <?php if ($s['description']): ?><span class="mt-0.5 block truncate text-xs text-muted-foreground"><?= View::e($s['description']) ?></span><?php endif ?>
              </span>
              <span class="hidden size-2 shrink-0 rounded-full bg-destructive" data-error-dot="<?= View::e($key) ?>"></span>
              <?= $svg($chevronRight, 'size-4 shrink-0 text-muted-foreground') ?>
            </button>
          <?php endforeach ?>
          <a href="<?= Request::url('admin/appearance/menus') ?>" class="flex items-center gap-3 bg-background px-4 py-3.5 text-sm transition-colors hover:bg-accent" data-leave>
            <span class="min-w-0 flex-1"><span class="block font-medium">Menüler</span><span class="mt-0.5 block text-xs text-muted-foreground">Görünüm &gt; Menüler'de düzenlenir</span></span>
            <?= $svg('<path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>', 'size-4 shrink-0 text-muted-foreground') ?>
          </a>
        </nav>
        <p class="p-4 text-xs leading-5 text-muted-foreground">Değişiklikler sağdaki önizlemede hemen görünür. Ziyaretçiler <strong>Yayınla</strong>'ya basana kadar eski hâlini görür.</p>
      </div>

      <!-- Bölümler -->
      <?php foreach ($sections as $key => $s): ?>
        <div class="invisible absolute inset-0 translate-x-full overflow-y-auto bg-sidebar transition-[transform,visibility] duration-300 ease-out" data-view="<?= View::e($key) ?>">
          <div class="sticky top-0 z-10 flex items-center gap-2 border-b bg-background px-2.5 py-2.5">
            <button type="button" class="btn btn-ghost size-9 shrink-0 p-0" aria-label="Geri" data-back><?= $svg($chevronLeft) ?></button>
            <div class="min-w-0 leading-tight">
              <p class="text-xs text-muted-foreground">Özelleştiriliyor</p>
              <p class="truncate text-sm font-semibold"><?= View::e($s['label']) ?></p>
            </div>
          </div>
          <?php if ($s['description']): ?><p class="px-4 pt-4 text-sm leading-6 text-muted-foreground"><?= View::e($s['description']) ?></p><?php endif ?>
          <div class="space-y-6 p-4">
            <?= View::render('admin/settings/fields', ['fields' => $s['fields'], 'values' => $values]) ?>
          </div>
        </div>
      <?php endforeach ?>
      <!-- Bölüm alanları: liste (customizer-sections.js doldurur) -->
      <?php foreach ($sectionData['areas'] as $area => $a): ?>
        <div class="invisible absolute inset-0 translate-x-full overflow-y-auto bg-sidebar transition-[transform,visibility] duration-300 ease-out" data-view="area-<?= View::e($area) ?>">
          <div class="sticky top-0 z-10 flex items-center gap-2 border-b bg-background px-2.5 py-2.5">
            <button type="button" class="btn btn-ghost size-9 shrink-0 p-0" aria-label="Geri" data-back><?= $svg($chevronLeft) ?></button>
            <div class="min-w-0 leading-tight">
              <p class="text-xs text-muted-foreground">Özelleştiriliyor</p>
              <p class="truncate text-sm font-semibold"><?= View::e($a['label']) ?></p>
            </div>
          </div>
          <input type="hidden" name="<?= View::e($a['key']) ?>" value="" data-section-input="<?= View::e($area) ?>">
          <div class="space-y-2 p-3" data-section-list="<?= View::e($area) ?>"></div>
          <div class="px-3 pb-4">
            <button type="button" class="btn btn-secondary w-full border-dashed" data-section-add="<?= View::e($area) ?>">
              <?= $svg('<path d="M5 12h14"/><path d="M12 5v14"/>') ?> Bölüm ekle
            </button>
            <p class="mt-3 text-xs leading-5 text-muted-foreground">Tutamaktan sürükleyerek sıralayın. Göz simgesi bölümü silmeden gizler.</p>
          </div>
        </div>
      <?php endforeach ?>

      <!-- Bir bölümün ayarları (içerik JS ile kurulur) -->
      <div class="invisible absolute inset-0 translate-x-full overflow-y-auto bg-sidebar transition-[transform,visibility] duration-300 ease-out" data-view="section-edit">
        <div class="sticky top-0 z-10 flex items-center gap-1 border-b bg-background px-2.5 py-2.5">
          <button type="button" class="btn btn-ghost size-9 shrink-0 p-0" aria-label="Geri" data-back><?= $svg($chevronLeft) ?></button>
          <div class="min-w-0 flex-1 leading-tight">
            <p class="text-xs text-muted-foreground" data-section-edit-area>Bölüm</p>
            <p class="truncate text-sm font-semibold" data-section-edit-title></p>
          </div>
          <button type="button" class="btn btn-ghost size-8 p-0" title="Gizle / göster" data-section-edit-toggle></button>
          <button type="button" class="btn btn-ghost size-8 p-0" title="Çoğalt" data-section-edit-duplicate><?= $svg('<rect width="14" height="14" x="8" y="8" rx="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/>') ?></button>
          <button type="button" class="btn btn-ghost size-8 p-0 text-destructive" title="Sil" data-section-edit-delete><?= $svg('<path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>') ?></button>
        </div>
        <div class="space-y-5 p-4" data-section-edit-body></div>
      </div>

      <!-- Bölüm kütüphanesi -->
      <div class="invisible absolute inset-0 translate-x-full overflow-y-auto bg-sidebar transition-[transform,visibility] duration-300 ease-out" data-view="section-library">
        <div class="sticky top-0 z-10 flex items-center gap-2 border-b bg-background px-2.5 py-2.5">
          <button type="button" class="btn btn-ghost size-9 shrink-0 p-0" aria-label="Geri" data-back><?= $svg($chevronLeft) ?></button>
          <div class="min-w-0 leading-tight">
            <p class="text-xs text-muted-foreground">Bölüm ekle</p>
            <p class="truncate text-sm font-semibold">Hazır bölümler</p>
          </div>
        </div>
        <div class="space-y-5 p-3" data-section-library></div>
      </div>
    </form>

    <!-- Alt çubuk -->
    <div class="flex h-12 shrink-0 items-center justify-between gap-2 border-t bg-background px-2">
      <button type="button" class="btn btn-ghost btn-sm" data-collapse title="Paneli gizle">
        <?= $svg('<path d="m11 17-5-5 5-5"/><path d="m18 17-5-5 5-5"/>') ?><span>Gizle</span>
      </button>
      <button type="button" class="btn btn-secondary btn-sm sm:hidden" data-show-preview>Önizleme</button>
      <div class="hidden gap-0.5 sm:flex" role="group" aria-label="Ekran boyutu">
        <?php foreach ($devices as $key => [$label, $width, $icon]): ?>
          <button type="button" class="btn btn-ghost size-8 p-0 data-[on]:bg-accent data-[on]:text-foreground text-muted-foreground" title="<?= $label ?>" aria-label="<?= $label ?>"
                  data-device="<?= $width ?>" <?= $key === 'desktop' ? 'data-on' : '' ?>><?= $svg($icon) ?></button>
        <?php endforeach ?>
      </div>
    </div>
  </aside>

  <!-- Panel gizliyken açma düğmesi -->
  <button type="button" class="btn btn-secondary fixed bottom-3 left-3 z-30 hidden shadow-lg" data-expand>
    <?= $svg('<path d="m6 17 5-5-5-5"/><path d="m13 17 5-5-5-5"/>') ?> Paneli göster
  </button>

  <!-- ============ Önizleme ============ -->
  <main class="relative hidden min-w-0 flex-1 justify-center overflow-hidden bg-muted sm:flex" data-stage>
    <div class="absolute inset-x-0 top-0 z-10 h-0.5 overflow-hidden" aria-hidden="true">
      <div class="h-full w-1/3 bg-primary opacity-0 transition-opacity" data-loading></div>
    </div>
    <div class="relative h-full w-full bg-background shadow-sm transition-[width] duration-300 ease-out" data-frame-wrap>
      <iframe src="<?= Request::url('/') ?>?customize_preview=1" title="Site önizlemesi" class="absolute inset-0 size-full border-0" data-frame></iframe>
    </div>
    <button type="button" class="btn btn-secondary fixed bottom-3 right-3 z-30 shadow-lg sm:hidden" data-hide-preview>Ayarlar</button>
  </main>
</div>

<style>
  @keyframes customizer-loading { 0% { transform: translateX(-100%); } 100% { transform: translateX(300%); } }
  [data-loading].is-loading { opacity: 1; animation: customizer-loading 0.9s ease-in-out infinite; }
</style>
<script type="module" src="<?= View::e(View::asset('admin/settings-page.js')) ?>"></script>
<script type="application/json" id="section-data"><?= json_encode($sectionData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script type="module" src="<?= View::e(View::asset('admin/customizer.js')) ?>"></script>
