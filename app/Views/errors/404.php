<div class="flex flex-col items-center py-24 text-center">
  <p class="text-sm font-medium text-muted-foreground">404</p>
  <h1 class="mt-2 text-3xl font-semibold tracking-tight"><?= Lang::t('Sayfa bulunamadı') ?></h1>
  <p class="mt-3 max-w-md text-muted-foreground"><?= Lang::t('Aradığınız sayfa taşınmış ya da hiç var olmamış olabilir.') ?></p>
  <a href="<?= Request::url('/') ?>" class="btn btn-primary mt-8"><?= Lang::t('Ana sayfaya dön') ?></a>
</div>
