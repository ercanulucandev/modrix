<?php $siteName = Setting::get('site_name', Config::get('app.name')); ?>
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
      body { @apply bg-muted font-sans text-foreground antialiased; }
    }
    @layer components {
      .btn { @apply inline-flex h-9 items-center justify-center gap-2 whitespace-nowrap rounded-md px-4 text-sm font-medium transition-colors outline-none focus-visible:ring-[3px] focus-visible:ring-ring/30; }
      .btn-primary { @apply bg-primary text-primary-foreground shadow-xs hover:bg-primary/90; }
      .label { @apply text-sm font-medium leading-none; }
      .input { @apply mt-2 flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs outline-none transition-[color,box-shadow] placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/20; }
      .input-error { @apply border-destructive focus-visible:ring-destructive/20; }
      .field-error { @apply mt-2 text-sm text-destructive; }
      .field-help { @apply mt-2 text-sm text-muted-foreground; }
      .card { @apply rounded-lg border bg-card shadow-xs; }
      .alert { @apply mb-6 w-full rounded-lg border px-4 py-3 text-sm; }
      .alert-ok { @apply border-emerald-200 bg-emerald-50 text-emerald-900; }
      .alert-error { @apply border-red-200 bg-red-50 text-red-900; }
      .alert-info { @apply bg-card; }
    }
  </style>
  <style><?= Setting::themeCss() ?></style>
  <?= View::render('partials/admin-color-mode') ?>
</head>
<body>
  <div class="flex min-h-screen flex-col items-center justify-center px-4 py-12">
    <div class="w-full max-w-sm">
      <div class="mb-6 flex items-center justify-center gap-2 font-semibold">
        <span class="flex size-7 items-center justify-center rounded-md bg-primary text-xs font-bold text-primary-foreground"><?= View::e(mb_strtoupper(mb_substr($siteName, 0, 1))) ?></span>
        <?= View::e($siteName) ?>
      </div>
      <div class="card p-6">
        <?= View::render('partials/flash') ?>
        <?= $content ?>
      </div>
      <p class="mt-6 text-center text-sm text-muted-foreground"><a href="<?= Request::url('/') ?>" class="underline-offset-4 hover:text-foreground hover:underline">← Siteye dön</a></p>
    </div>
  </div>
</body>
</html>
