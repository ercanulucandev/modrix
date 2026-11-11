<div class="flex flex-col items-center py-24 text-center">
  <p class="text-sm font-medium text-muted-foreground"><?= (int) $status ?></p>
  <h1 class="mt-2 text-3xl font-semibold tracking-tight"><?= View::e($message) ?></h1>
  <?php if ($errorId): ?>
    <p class="mt-3 text-sm text-muted-foreground">Sorun devam ederse bu kodu iletin: <code class="rounded-sm bg-muted px-1.5 py-0.5 font-mono text-foreground">#<?= View::e($errorId) ?></code></p>
  <?php endif ?>
  <?php if (str_starts_with(Request::path(), '/admin')): ?>
    <a href="<?= Request::url('admin') ?>" class="btn btn-primary mt-8">Genel bakışa dön</a>
  <?php else: ?>
    <a href="<?= Request::url('/') ?>" class="btn btn-primary mt-8">Ana sayfaya dön</a>
  <?php endif ?>
</div>
