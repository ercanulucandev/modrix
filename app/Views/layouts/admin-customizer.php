<?php
// Görünüm > Özelleştir için tam ekran layout: admin'in görünüşü (aynı head), kenar menüsü ve üst çubuk yok.
// İçerik: app/Views/admin/appearance/customize.php
$siteName = Setting::get('site_name', Config::get('app.name'));
?>
<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title><?= isset($title) ? View::e($title) . ' · ' : '' ?>Yönetim · <?= View::e($siteName) ?></title>
  <?php if ($favicon = Setting::url('favicon')): ?><link rel="icon" href="<?= View::e($favicon) ?>"><?php endif ?>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4.1.13"></script>
  <style type="text/tailwindcss">
    @theme {
      --font-sans: "Inter", ui-sans-serif, system-ui, sans-serif;
      --color-background: #ffffff;
      --color-foreground: #09090b;
      --color-card: #ffffff;
      --color-muted: #f4f4f5;
      --color-muted-foreground: #71717a;
      --color-accent: #f4f4f5;
      --color-accent-foreground: #18181b;
      --color-sidebar: #fafafa;
      --color-border: #e4e4e7;
      --color-input: #e4e4e7;
      --color-primary: #18181b;
      --color-primary-foreground: #fafafa;
      --color-destructive: #dc2626;
      --color-ring: #18181b;
      --radius-sm: 2px;
      --radius-md: 4px;
      --radius-lg: 6px;
    }
    @layer base {
      *, ::before, ::after { @apply border-border; }
      body { @apply bg-background font-sans text-foreground antialiased; }
    }
    @layer components {
      .btn { @apply inline-flex h-9 shrink-0 items-center justify-center gap-2 whitespace-nowrap rounded-md px-4 text-sm font-medium transition-colors outline-none focus-visible:ring-[3px] focus-visible:ring-ring/30 disabled:pointer-events-none disabled:opacity-50; }
      .btn-sm { @apply h-8 gap-1.5 px-3 text-xs; }
      .btn-primary { @apply bg-primary text-primary-foreground shadow-xs hover:bg-primary/90; }
      .btn-secondary { @apply border border-input bg-background shadow-xs hover:bg-accent hover:text-accent-foreground; }
      .btn-ghost { @apply hover:bg-accent hover:text-accent-foreground; }
      .btn-danger { @apply bg-destructive text-white shadow-xs hover:bg-destructive/90; }
      .btn-danger-ghost { @apply text-destructive hover:bg-destructive/10; }
      .label { @apply text-sm font-medium leading-none; }
      .input { @apply mt-2 flex h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none transition-[color,box-shadow] placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/20 disabled:opacity-50; }
      textarea.input { @apply h-auto min-h-20 py-2; }
      .input-error { @apply border-destructive focus-visible:ring-destructive/20; }
      .field-error { @apply mt-2 text-sm text-destructive; }
      .field-help { @apply mt-2 text-sm text-muted-foreground; }
      .card { @apply rounded-lg border bg-card shadow-xs; }
      .card-header { @apply flex flex-col gap-1.5 px-6 py-5; }
      .card-title { @apply font-semibold leading-none tracking-tight; }
      .card-description { @apply text-sm text-muted-foreground; }
      .card-footer { @apply flex items-center gap-2 border-t px-6 py-4; }
      .table { @apply w-full text-sm; }
      .table thead tr { @apply border-b; }
      .table th { @apply h-10 px-4 text-left align-middle font-medium whitespace-nowrap text-muted-foreground; }
      .table td { @apply px-4 py-3 align-middle; }
      .table tbody tr { @apply border-b transition-colors last:border-0 hover:bg-muted/50; }
      .badge { @apply inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-xs font-medium whitespace-nowrap; }
      .badge-default { @apply border-transparent bg-primary text-primary-foreground; }
      .badge-gray { @apply border-transparent bg-muted text-muted-foreground; }
      .badge-green { @apply border-emerald-200 bg-emerald-50 text-emerald-700; }
      .badge-yellow { @apply border-amber-200 bg-amber-50 text-amber-700; }
      .alert { @apply mb-6 w-full rounded-lg border px-4 py-3 text-sm; }
      .alert-ok { @apply border-emerald-200 bg-emerald-50 text-emerald-900; }
      .alert-error { @apply border-red-200 bg-red-50 text-red-900; }
      .alert-info { @apply bg-card; }
      .alert-warning { @apply border-amber-200 bg-amber-50 text-amber-900; }
    }
  </style>
  <style><?= Setting::themeCss() ?></style>
  <?= View::render('partials/admin-color-mode') ?>

  <!-- JS ayarları (app/Assets/lib/app.js okur) ve ortak kitin kısa adı: import ... from '@app/lib/modal.js' -->
  <meta name="csrf-token" content="<?= View::e(Session::token()) ?>">
  <meta name="app-base" content="<?= View::e(Request::url('')) ?>">
  <meta name="app-login" content="<?= View::e(Router::url('admin.login')) ?>">
  <script type="importmap"><?= json_encode(['imports' => ['@app/' => Request::url('assets/app/')]], JSON_UNESCAPED_SLASHES) ?></script>
</head>
<body class="h-dvh overflow-hidden">
  <?= $content ?>
  <script>
    document.addEventListener('submit', (e) => {
      const msg = e.target.dataset.confirm;
      if (msg && !confirm(msg)) e.preventDefault();
    });
  </script>
</body>
</html>
