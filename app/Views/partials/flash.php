<?php
// Session::flash('ok' | 'error' | 'info', 'mesaj') ile gelen mesajlar
$messages = array_filter([
    'ok'    => Session::flash('ok'),
    'error' => Session::flash('error') ?? (Session::flash('errors') ? 'Lütfen işaretli alanları kontrol edin.' : null),
    'info'  => Session::flash('info'),
]);
$icons = [
    'ok'    => '<path d="M21.801 10A10 10 0 1 1 17 3.335"/><path d="m9 11 3 3L22 4"/>',
    'error' => '<circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/>',
    'info'  => '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>',
];
foreach ($messages as $type => $msg): ?>
  <div class="alert alert-<?= $type ?> flex items-start gap-3" role="<?= $type === 'error' ? 'alert' : 'status' ?>">
    <svg class="mt-0.5 size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?= $icons[$type] ?></svg>
    <span><?= View::e($msg) ?></span>
  </div>
<?php endforeach;
