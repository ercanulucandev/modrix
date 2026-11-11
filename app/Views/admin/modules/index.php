<?php
$states = [
    'active'        => ['Aktif', 'badge-green'],
    'inactive'      => ['Pasif', 'badge-yellow'],
    'not_installed' => ['Kurulmamış', 'badge-gray'],
];
// İşlem düğmesi; gönderilirken $busy yazısı ve dönen simge gösterilir (modules-page.js)
$busy = ['install' => 'Kuruluyor…', 'update' => 'Güncelleniyor…', 'enable' => 'Aktif ediliyor…',
         'disable' => 'Pasife alınıyor…', 'uninstall' => 'Kaldırılıyor…', 'delete' => 'Siliniyor…'];
$button = function (string $action, string $name, string $label, string $class, ?string $confirm = null) use ($busy) {
    return '<form method="post" action="' . Router::url("admin.modules.$action", ['name' => $name]) . '" data-module-action'
        . ($confirm ? ' data-confirm="' . View::e($confirm) . '"' : '') . '>' . View::csrf()
        . '<button class="btn btn-sm ' . $class . '" data-busy="' . View::e($busy[$action]) . '">' . View::e($label) . '</button></form>';
};
?>
<div class="mb-8">
  <h1 class="text-2xl font-semibold tracking-tight">Modüller</h1>
  <p class="mt-1 text-sm text-muted-foreground">Klasörü <code class="rounded-sm bg-muted px-1 py-0.5 font-mono text-xs text-foreground">app/Modules</code> içine atın ya da ZIP olarak yükleyin. Sadece aktif modüller sitede çalışır.</p>
</div>

<!-- ZIP yükle: sürükle-bırak, ilerleme çubuğu (app/Assets/admin/modules-page.js). JS yoksa normal form. -->
<form method="post" action="<?= Router::url('admin.modules.upload') ?>" enctype="multipart/form-data" class="card mb-4 p-2"
      data-module-upload data-max="<?= (int) $maxZip ?>" <?= $zip ? '' : 'data-disabled' ?>>
  <?= View::csrf() ?>
  <label data-dropzone class="group flex cursor-pointer flex-col items-center gap-3 rounded-md border-2 border-dashed border-border px-6 py-8 text-center transition-colors
                               hover:border-foreground/30 hover:bg-muted/40 data-[drag]:border-primary data-[drag]:bg-primary/5 <?= $zip ? '' : 'pointer-events-none opacity-60' ?>">
    <span class="flex size-11 items-center justify-center rounded-full border bg-background text-muted-foreground transition-transform group-hover:-translate-y-0.5 group-data-[drag]:-translate-y-0.5 group-data-[drag]:text-primary">
      <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/>
      </svg>
    </span>
    <span>
      <span class="block text-sm font-medium">Modül ZIP'ini sürükleyip bırakın <span class="text-muted-foreground">ya da</span> <span class="underline underline-offset-4">seçin</span></span>
      <span class="mt-1 block text-xs text-muted-foreground">Tek modül klasörü ve <code class="font-mono">module.json</code> içermeli · en fazla <?= round($maxZip / 1048576) ?> MB · kurulu modülün yeni sürümü onu günceller</span>
    </span>
    <input type="file" name="module" accept=".zip,application/zip" class="sr-only" required <?= $zip ? '' : 'disabled' ?>>
  </label>
  <?php if (!$zip): ?><p class="field-error px-2 pb-1">Sunucuda PHP zip eklentisi kapalı; ZIP yükleme çalışmaz.</p><?php endif ?>
  <div class="flex justify-end px-2 pb-1 pt-2" data-nojs><button class="btn btn-primary btn-sm">Yükle</button></div>

  <!-- Yükleme durumu (JS doldurur) -->
  <div class="hidden" data-upload-status>
    <div class="flex items-center gap-4 rounded-md border px-4 py-4">
      <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground" data-upload-icon></span>
      <div class="min-w-0 flex-1">
        <div class="flex items-baseline justify-between gap-3">
          <p class="truncate text-sm font-medium" data-upload-name></p>
          <span class="shrink-0 font-mono text-xs tabular-nums text-muted-foreground" data-upload-percent></span>
        </div>
        <p class="mt-0.5 text-xs text-muted-foreground" data-upload-text></p>
        <div class="mt-2.5 h-1.5 overflow-hidden rounded-full bg-muted" data-upload-track>
          <div class="h-full w-0 rounded-full bg-primary transition-[width] duration-200 ease-out" data-upload-bar></div>
        </div>
      </div>
      <button type="button" class="btn btn-ghost btn-sm hidden" data-upload-retry>Başka dosya</button>
    </div>
  </div>
</form>

<style>
  @keyframes module-indeterminate { 0% { transform: translateX(-100%); } 100% { transform: translateX(250%); } }
  [data-upload-track][data-indeterminate] [data-upload-bar] { width: 40% !important; animation: module-indeterminate 1.1s ease-in-out infinite; }
</style>

<div class="alert alert-warning text-xs">Yüklenen modüllerin PHP kodu sunucunuzda çalışır. Sadece güvendiğiniz kaynaktan gelen modülleri yükleyin.</div>

<?php if (!$modules): ?>
  <div class="rounded-lg border border-dashed px-6 py-16 text-center text-sm text-muted-foreground">Henüz modül yok.</div>
<?php else: ?>
  <div class="card divide-y">
    <?php foreach ($modules as $folder => $m): [$stateLabel, $stateClass] = $states[$m['state']]; ?>
      <div class="flex flex-col gap-4 p-5 lg:flex-row lg:items-center lg:justify-between">
        <div class="flex min-w-0 items-start gap-4">
          <?= View::render('admin/modules/icon', ['module' => $m]) ?>
          <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
              <h2 class="font-medium"><?= View::e($m['name']) ?></h2>
              <span class="font-mono text-xs text-muted-foreground">v<?= View::e($m['installed_version'] ?? $m['version']) ?></span>
              <span class="badge <?= $stateClass ?>"><?= $stateLabel ?></span>
              <?php if ($m['update']): ?><span class="badge badge-default">Güncelleme: v<?= View::e($m['version']) ?></span><?php endif ?>
            </div>
            <?php if ($m['description']): ?><p class="mt-1 text-sm text-muted-foreground"><?= View::e($m['description']) ?></p><?php endif ?>
            <p class="mt-1 font-mono text-xs text-muted-foreground">app/Modules/<?= View::e($folder) ?><?= $m['author'] ? ' · ' . View::e($m['author']) : '' ?></p>
            <?php if ($m['error']): ?><p class="field-error">Hata: <?= View::e($m['error']) ?></p><?php endif ?>
          </div>
        </div>

        <div class="flex shrink-0 flex-wrap gap-2">
          <?php if ($m['state'] === 'not_installed'): ?>
            <?php if (!$m['error']): ?><?= $button('install', $folder, 'Kur', 'btn-primary') ?><?php endif ?>
            <?= $button('delete', $folder, 'Dosyaları sil', 'btn-danger-ghost', "$folder klasörü kalıcı olarak silinsin mi?") ?>
          <?php else: ?>
            <?php if ($m['settings']): ?>
              <a href="<?= Router::url('admin.modules.settings', ['name' => $folder]) ?>" class="btn btn-secondary btn-sm">Ayarlar</a>
            <?php endif ?>
            <?php if ($m['update']): ?><?= $button('update', $folder, 'Güncelle', 'btn-primary') ?><?php endif ?>
            <?php if ($m['state'] === 'active'): ?>
              <?= $button('disable', $folder, 'Pasife al', 'btn-secondary') ?>
            <?php elseif (!$m['error']): ?>
              <?= $button('enable', $folder, 'Aktif et', 'btn-primary') ?>
            <?php endif ?>
            <?= $button('uninstall', $folder, 'Kaldır', 'btn-danger-ghost',
                "$folder kaldırılsın mı? Modülün TABLOLARI VE VERİLERİ SİLİNİR. Verileri korumak için 'Pasife al' kullanın.") ?>
          <?php endif ?>
        </div>
      </div>
    <?php endforeach ?>
  </div>
<?php endif ?>

<script type="module" src="<?= View::e(View::asset('admin/modules-page.js')) ?>"></script>
