<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Modrix kurulumu</title>
  <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4.1.13"></script>
  <style type="text/tailwindcss">
    @theme { --color-muted: #f4f4f5; --color-muted-foreground: #71717a; --color-border: #e4e4e7; --color-primary: #18181b; }
    .btn { @apply inline-flex h-10 items-center justify-center rounded-md bg-primary px-5 text-sm font-medium text-white hover:bg-primary/90; }
    .input { @apply mt-1.5 h-10 w-full rounded-md border border-border px-3 text-sm outline-none focus:border-zinc-900 focus:ring-2 focus:ring-zinc-900/10; }
    .label { @apply text-sm font-medium; }
  </style>
</head>
<body class="min-h-screen bg-muted font-sans text-zinc-900 antialiased">
  <main class="mx-auto max-w-xl px-4 py-12">
    <div class="mb-6 flex items-center gap-3">
      <span class="flex size-9 items-center justify-center rounded-lg bg-zinc-900 text-sm font-bold text-white">M</span>
      <div><p class="font-semibold">Modrix kurulumu</p><p class="text-xs text-muted-foreground">v<?= Core\App::VERSION ?></p></div>
    </div>
    <ol class="mb-6 flex gap-2 text-xs">
      <?php foreach ([1 => 'Gereksinimler', 2 => 'Veritabanı', 3 => 'Site ve yönetici'] as $n => $label): ?>
        <li class="flex-1 rounded-md border px-3 py-2 <?= $step === $n ? 'border-zinc-900 bg-white font-medium' : ($step > $n ? 'bg-white text-emerald-700' : 'text-muted-foreground') ?>"><?= $step > $n ? '✓ ' : "$n. " ?><?= $label ?></li>
      <?php endforeach ?>
    </ol>
    <?php if (!empty($error)): ?><div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900"><?= View::e($error) ?></div><?php endif ?>
    <div class="rounded-xl border bg-white p-6 shadow-sm"><?= $content ?></div>
  </main>
</body>
</html>
