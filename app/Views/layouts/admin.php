<?php
// Admin paneli layout'u.
// Menü 'admin.menu' hook'uyla oluşur. Modüller öğelerini module.json -> menu.admin ile ekler.
// Sade menü: öğeler 'group' ile gruplanır (İçerik, Mağaza, Tanıtım, Pazarlama, Sistem); kenar çubuğunda sadece grup görünür,
// grubun sayfalarında üstte grubun sekmeleri çıkar. 'tab' sekmedeki kısa ad (yoksa 'label'). Grupsuz öğe tek başına görünür.
// Sıra: 'order' küçükten büyüğe (verilmezse 50). 'permission' anahtarı olan öğeyi sadece o yetkiye sahip roller görür.
// Modül yeni grup ekleyebilir: Hook::on('admin.menu.groups', fn($g) => [...$g, 'crm' => ['label' => 'Müşteriler', 'icon' => 'users', 'order' => 25]])
$admin = App\Models\User::admin();
$menu  = Hook::filter('admin.menu', [
    ['url' => 'admin', 'label' => 'Genel bakış', 'icon' => 'home', 'order' => 0],
    ['url' => 'admin/media', 'label' => 'Medya', 'icon' => 'image', 'order' => 70, 'permission' => 'media.manage', 'group' => 'content'],
    ['url' => 'admin/appearance', 'label' => 'Görünüm', 'icon' => 'palette', 'order' => 75, 'permission' => 'appearance.manage'],
    ['url' => 'admin/seo', 'label' => 'SEO', 'icon' => 'chart', 'order' => 83, 'permission' => 'seo.manage', 'group' => 'marketing'],
    ['url' => 'admin/settings', 'label' => 'Ayarlar', 'icon' => 'cog', 'order' => 80, 'permission' => 'settings.manage', 'group' => 'system'],
    ['url' => 'admin/modules', 'label' => 'Modüller', 'icon' => 'package', 'order' => 82, 'permission' => 'modules.manage', 'group' => 'system', 'badge' => count(array_filter(Core\Module::all(), fn($m) => $m['update'] || $m['state'] === 'not_installed')) ?: null],
    ['url' => 'admin/store', 'label' => 'Mağaza', 'tab' => 'Mağaza', 'icon' => 'package', 'order' => 83, 'permission' => 'modules.manage', 'group' => 'system'],
    ['url' => 'admin/users', 'label' => 'Kullanıcılar', 'icon' => 'users', 'order' => 85, 'permission' => 'users.manage', 'group' => 'system', 'match' => ['admin/roles', 'admin/activity']],
    ['url' => 'admin/updates', 'label' => 'Güncellemeler', 'icon' => 'cloud', 'order' => 93, 'permission' => 'settings.manage', 'group' => 'system',
     'badge' => App\Services\Updater::available() ? 1 : null],
    ['url' => 'admin/languages', 'label' => 'Diller', 'icon' => 'globe', 'order' => 81, 'permission' => 'settings.manage', 'group' => 'system'],
    ['url' => 'admin/backups', 'label' => 'Yedekler', 'icon' => 'cloud', 'order' => 92, 'permission' => 'backups.manage', 'group' => 'system'],
]);
// Kullanıcılar yetkisi yoksa ama işlem kaydını görebiliyorsa menü öğesi işlem kaydına gider
if (!App\Services\Access::can('users.manage') && App\Services\Access::can('activity.view')) {
    $menu[] = ['url' => 'admin/activity', 'label' => 'İşlem kaydı', 'icon' => 'users', 'order' => 85, 'group' => 'system'];
}
$groups = Hook::filter('admin.menu.groups', [
    'content'   => ['label' => 'İçerik', 'icon' => 'pages', 'order' => 10],
    'shop'      => ['label' => 'Mağaza', 'icon' => 'cart', 'order' => 20],
    'showcase'  => ['label' => 'Tanıtım', 'icon' => 'star', 'order' => 30],
    'marketing' => ['label' => 'Pazarlama', 'icon' => 'megaphone', 'order' => 40],
    'system'    => ['label' => 'Sistem', 'icon' => 'cog', 'order' => 90],
]);
// Yetkisi olmadığı öğeler gösterilmez (module.json -> menu.admin öğelerinde de 'permission' olabilir)
$menu = array_filter($menu, fn($item) => empty($item['permission']) || App\Services\Access::can($item['permission']));
usort($menu, fn($a, $b) => ($a['order'] ?? 50) <=> ($b['order'] ?? 50));

// Aktif öğe: adresle en uzun eşleşen
$path   = trim(Request::path(), '/');
$active = '';
// 'match': öğeye ait başka adresler (ör. Kullanıcılar -> admin/roles)
$bestLength = 0;
foreach ($menu as $item) {
    foreach ([$item['url'], ...($item['match'] ?? [])] as $prefix) {
        if (($path === $prefix || str_starts_with($path, $prefix . '/')) && strlen($prefix) > $bestLength) [$active, $bestLength] = [$item['url'], strlen($prefix)];
    }
}

// Kenar çubuğu: gruplar (alt öğeleri sırayla) + grupsuz öğeler. Grubun bağlantısı ilk alt öğesi, rozeti alt öğelerin toplamı.
$entries = [];
$activeGroup = null;
foreach ($menu as $item) {
    $g = $item['group'] ?? null;
    if ($g === null || !isset($groups[$g])) {
        $entries[$item['url']] = $item + ['children' => [], 'active' => $active === $item['url']];
        continue;
    }
    $entries["group:$g"] ??= ['url' => $item['url'], 'label' => $groups[$g]['label'], 'icon' => $groups[$g]['icon'], 'order' => $groups[$g]['order'], 'badge' => 0, 'children' => [], 'active' => false];
    $entries["group:$g"]['children'][] = $item;
    $entries["group:$g"]['badge'] += (int) ($item['badge'] ?? 0);
    if ($active === $item['url']) { $entries["group:$g"]['active'] = true; $activeGroup = $entries["group:$g"]; }
}
uasort($entries, fn($a, $b) => ($a['order'] ?? 50) <=> ($b['order'] ?? 50));
if ($activeGroup) $activeGroup = $entries[array_search(true, array_map(fn($e) => $e['active'] && $e['children'], $entries), true)];

// Lucide ikonları (module.json'da 'icon' ile seçilir)
$icons = [
    'home'     => '<rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/>',
    'document' => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>',
    'users'    => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
    'cog'      => '<path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/>',
    'package'  => '<path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/>',
    'palette'  => '<circle cx="13.5" cy="6.5" r=".5" fill="currentColor"/><circle cx="17.5" cy="10.5" r=".5" fill="currentColor"/><circle cx="8.5" cy="7.5" r=".5" fill="currentColor"/><circle cx="6.5" cy="12.5" r=".5" fill="currentColor"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.502 5.555-5.554C21.965 6.012 17.461 2 12 2z"/>',
    'pages'    => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M9 13h6"/><path d="M9 17h3"/>',
    'image'    => '<rect width="18" height="18" x="3" y="3" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.09-3.09a2 2 0 0 0-2.82 0L6 21"/>',
    'chart'    => '<path d="M3 3v16a2 2 0 0 0 2 2h16"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/>',
    'mail'     => '<rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>',
    'globe' => '<circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/>',
    'calendar' => '<rect width="18" height="18" x="3" y="4" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/>',
    'briefcase' => '<path d="M16 20V4a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/><rect width="20" height="14" x="2" y="6" rx="2"/>',
    'chat'     => '<path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/>',
];
$icon = fn(string $name, string $class = 'size-4') => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . ($icons[$name] ?? $icons['document']) . '</svg>';
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
<body>
  <input type="checkbox" id="nav" class="peer hidden">

  <aside class="fixed inset-y-0 left-0 z-40 flex w-60 -translate-x-full flex-col border-r bg-sidebar transition-transform peer-checked:translate-x-0 lg:translate-x-0">
    <div class="flex h-14 items-center gap-2 border-b px-4">
      <span class="flex size-7 items-center justify-center rounded-md bg-primary text-xs font-bold text-primary-foreground"><?= View::e(mb_strtoupper(mb_substr($siteName, 0, 1))) ?></span>
      <div class="min-w-0 leading-tight">
        <p class="truncate text-sm font-semibold"><?= View::e($siteName) ?></p>
        <p class="text-xs text-muted-foreground">Yönetim paneli</p>
      </div>
    </div>

    <nav class="flex-1 overflow-y-auto p-2">
      <?php foreach ($entries as $item): $on = $item['active']; $system = str_starts_with((string) array_search($item, $entries, true), 'group:system'); ?>
        <?php if ($system): ?><div class="my-2 border-t"></div><?php endif ?>
        <a href="<?= Request::url($item['url']) ?>"
           class="flex h-9 items-center gap-2.5 rounded-md px-2.5 text-sm transition-colors <?= $on ? 'bg-accent font-medium text-accent-foreground' : 'text-foreground/80 hover:bg-accent hover:text-accent-foreground' ?>">
          <?= $icon($item['icon'] ?? 'document', 'size-4 shrink-0 ' . ($on ? '' : 'text-muted-foreground')) ?>
          <span class="flex-1 truncate"><?= View::e($item['label']) ?></span>
          <?php if (!empty($item['badge'])): ?><span class="rounded-md bg-primary px-1.5 text-xs font-medium text-primary-foreground"><?= (int) $item['badge'] ?></span><?php endif ?>
        </a>
      <?php endforeach ?>
    </nav>

    <div class="border-t p-2">
      <div class="flex items-center gap-2 rounded-md p-2">
        <span class="flex size-8 shrink-0 items-center justify-center rounded-md bg-muted text-xs font-semibold"><?= View::e(mb_strtoupper(mb_substr($admin['name'] ?? '?', 0, 1))) ?></span>
        <a href="<?= Router::url('admin.profile') ?>" class="min-w-0 flex-1 leading-tight hover:underline" title="Profilim">
          <p class="truncate text-sm font-medium"><?= View::e($admin['name'] ?? '') ?></p>
          <p class="truncate text-xs text-muted-foreground"><?= View::e(App\Models\Role::map()[$admin['role'] ?? '']['name'] ?? '') ?> · <?= View::e($admin['email'] ?? '') ?></p>
        </a>
        <form method="post" action="<?= Router::url('admin.logout') ?>">
          <?= View::csrf() ?>
          <button class="btn btn-ghost size-8 px-0" title="Çıkış yap" aria-label="Çıkış yap">
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg>
          </button>
        </form>
      </div>
    </div>
  </aside>

  <label for="nav" class="fixed inset-0 z-30 hidden bg-black/40 peer-checked:block lg:peer-checked:hidden"></label>

  <div class="lg:pl-60">
    <header class="sticky top-0 z-20 flex h-14 items-center gap-3 border-b bg-background/95 px-4 backdrop-blur sm:px-6">
      <label for="nav" class="btn btn-ghost size-8 cursor-pointer px-0 lg:hidden" aria-label="Menü">
        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="18" y2="18"/></svg>
      </label>
      <span class="truncate text-sm font-medium"><?= View::e($title ?? '') ?></span>
      <div class="ml-auto"><?= View::render('partials/admin-color-mode-switch') ?></div>
      <a href="<?= Request::url('/') ?>" target="_blank" class="btn btn-secondary btn-sm">
        <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/></svg>
        Siteyi gör
      </a>
    </header>

    <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
      <?php if (Setting::bool('maintenance_mode')): ?>
        <div class="alert alert-warning">Bakım modu açık: ziyaretçiler bakım sayfasını görüyor. <a href="<?= Request::url('admin/settings?tab=maintenance') ?>" class="font-medium underline underline-offset-4">Kapat</a></div>
      <?php endif ?>
      <?php if ($activeGroup && count($activeGroup['children']) > 1): ?>
        <?php // Grubun sekmeleri (kenar çubuğunda sadece grup adı var) ?>
        <nav class="-mt-2 mb-6 flex flex-wrap gap-x-1 border-b" aria-label="<?= View::e($activeGroup['label']) ?>">
          <?php foreach ($activeGroup['children'] as $tab): $on = $active === $tab['url']; ?>
            <a href="<?= Request::url($tab['url']) ?>" class="-mb-px flex shrink-0 items-center gap-1.5 border-b-2 px-3 py-2 text-sm font-medium <?= $on ? 'border-primary text-foreground' : 'border-transparent text-muted-foreground hover:text-foreground' ?>">
              <?= View::e($tab['tab'] ?? $tab['label']) ?>
              <?php if (!empty($tab['badge'])): ?><span class="rounded bg-primary px-1.5 text-xs text-primary-foreground"><?= (int) $tab['badge'] ?></span><?php endif ?>
            </a>
          <?php endforeach ?>
        </nav>
      <?php endif ?>
      <?= View::render('partials/flash') ?>
      <?= $content ?>
    </main>
  </div>

  <script>
    document.addEventListener('submit', (e) => {
      const msg = e.target.dataset.confirm;
      if (msg && !confirm(msg)) e.preventDefault();
    });
  </script>
</body>
</html>
