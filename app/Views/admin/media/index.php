<?php // Admin > Medya: sayfa iskeleti. İçerik app/Assets/admin/media-page.js tarafından API'den doldurulur. ?>
<div class="mb-6 flex flex-col gap-1">
  <h1 class="text-2xl font-semibold tracking-tight">Medya</h1>
  <p class="text-sm text-muted-foreground">Görselleri ve belgeleri yükleyin; içerikte, ayarlarda ve modüllerde kullanın. Dosyaları sayfaya sürükleyip bırakabilirsiniz. Birden çok dosya için kutucuktaki onay kutusunu, Ctrl ya da Shift ile tıklamayı kullanın.</p>
</div>

<div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
  <div class="card p-4 sm:p-5" id="media-library"></div>
  <aside class="card h-fit lg:sticky lg:top-20" id="media-details">
    <div class="flex flex-col items-center gap-2 px-6 py-16 text-center text-sm text-muted-foreground">
      Ayrıntıları görmek için bir dosya seçin.
    </div>
  </aside>
</div>

<script type="module" src="<?= View::e(View::asset('admin/media-page.js')) ?>"></script>
