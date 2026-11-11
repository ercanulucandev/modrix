<?php
// Başlangıç teması: site layout'u. Renk, yazı tipi, üst/alt kısım: Görünüm > Özelleştir.
// Menüler: Görünüm > Menüler ('header' ve 'footer' konumları, theme.json).
$siteName = Setting::get('site_name', Config::get('app.name'));
$logo     = Setting::url('logo');
$primary  = Theme::color('primary_color', '#18181b');
$radius   = ['none' => 0, 'small' => 4, 'medium' => 6, 'large' => 10][Theme::get('radius')] ?? 4;
$fonts    = [
    'inter'   => ['Inter:wght@400;500;600;700', '"Inter", ui-sans-serif, system-ui, sans-serif'],
    'manrope' => ['Manrope:wght@400;500;600;700', '"Manrope", ui-sans-serif, system-ui, sans-serif'],
    'dmsans'  => ['DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700', '"DM Sans", ui-sans-serif, system-ui, sans-serif'],
    'lora'    => ['Lora:wght@400;500;600;700', '"Lora", ui-serif, Georgia, serif'],
];
[$fontFamily, $fontStack] = $fonts[Theme::get('font')] ?? $fonts['inter'];

$header  = Menu::location('header');
$footer  = Menu::location('footer');
$ctaText = Theme::get('header_cta_text');
$ctaUrl  = (string) Theme::get('header_cta_url');
$social  = Theme::bool('footer_social') ? array_filter([
    'Instagram' => Setting::get('social_instagram'),
    'X'         => Setting::get('social_x'),
    'Facebook'  => Setting::get('social_facebook'),
    'LinkedIn'  => Setting::get('social_linkedin'),
    'YouTube'   => Setting::get('social_youtube'),
]) : [];
$contact = Theme::bool('footer_contact') ? array_filter([
    'email'   => Setting::get('contact_email'),
    'phone'   => Setting::get('contact_phone'),
    'address' => Setting::get('contact_address'),
]) : [];
?>
<!doctype html>
<html lang="<?= Lang::current() ?>" dir="<?= Lang::rtl() ? 'rtl' : 'ltr' ?>">
<head>
<?= Theme::head($title ?? null, $metaDescription ?? null, $metaImage ?? null) ?>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=<?= $fontFamily ?>&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4.1.13"></script>
  <style type="text/tailwindcss">
    @theme {
      --font-sans: <?= $fontStack ?>;
      --color-background: #ffffff;
      --color-foreground: #09090b;
      --color-card: #ffffff;
      --color-muted: #f4f4f5;
      --color-muted-foreground: #71717a;
      --color-accent: #f4f4f5;
      --color-accent-foreground: #18181b;
      --color-border: #e4e4e7;
      --color-input: #e4e4e7;
      --color-primary: <?= $primary ?>;
      --color-primary-foreground: <?= Theme::contrast($primary) ?>;
      --color-destructive: #dc2626;
      --color-ring: <?= $primary ?>;
      --radius-sm: <?= max(0, $radius - 2) ?>px;
      --radius-md: <?= $radius ?>px;
      --radius-lg: <?= $radius ? $radius + 2 : 0 ?>px;
      --radius-xl: <?= $radius ? $radius + 4 : 0 ?>px;
    }
  </style>
<?= View::render('partials/ui-styles') ?>
</head>
<body class="flex min-h-screen flex-col">
  <header <?= Theme::editable('header') ?> class="<?= Theme::bool('sticky_header') ? 'sticky top-0' : 'relative' ?> z-40 border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/80">
    <div class="wrap flex h-14 items-center justify-between gap-6">
      <a <?= Theme::editable('identity', 'logo') ?> href="<?= Request::url('/') ?>" class="flex shrink-0 items-center gap-2 font-semibold tracking-tight">
        <?php if ($logo): ?>
          <img src="<?= View::e($logo) ?>" alt="<?= View::e($siteName) ?>" class="h-7 w-auto">
        <?php else: ?>
          <span class="flex size-7 items-center justify-center rounded-md bg-primary text-xs font-bold text-primary-foreground"><?= View::e(mb_strtoupper(mb_substr($siteName, 0, 1))) ?></span>
          <span><?= View::e($siteName) ?></span>
        <?php endif ?>
      </a>

      <input type="checkbox" id="site-nav" class="peer hidden">
      <label for="site-nav" class="btn btn-ghost btn-sm md:hidden" aria-label="Menü">
        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="18" y2="18"/></svg>
      </label>
      <nav class="absolute inset-x-0 top-14 hidden max-h-[calc(100vh-3.5rem)] flex-col gap-1 overflow-y-auto border-b bg-background p-4 peer-checked:flex
                  md:static md:flex md:max-h-none md:flex-row md:items-center md:gap-6 md:overflow-visible md:border-0 md:bg-transparent md:p-0">
        <?= View::render('partials/nav', ['items' => $header]) ?>
        <?= implode('', Hook::filter('site.header_actions', [])) ?>
        <?php if (Theme::bool('header_search')): ?>
          <a href="<?= Router::url('search') ?>" class="flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground" aria-label="Ara" title="Ara"><svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg><span class="md:sr-only">Ara</span></a>
        <?php endif ?>
        <?php if ($ctaText && Menu::validUrl($ctaUrl)): ?>
          <a <?= Theme::editable('header', 'header_cta_text') ?> href="<?= View::e(Menu::href($ctaUrl)) ?>" class="btn btn-primary btn-sm mt-2 md:mt-0"><?= View::e($ctaText) ?></a>
        <?php endif ?>
      </nav>
    </div>
  </header>

  <?php // Ana sayfa tam genişlik: bölümler kendi kabını (wrap) çizer ?>
  <?php if (trim(Request::path(), '/') === ''): ?>
    <main class="flex-1">
      <div class="wrap empty:hidden"><?= View::render('partials/flash') ?></div>
      <?= $content ?>
    </main>
  <?php else: ?>
    <main class="wrap flex-1 py-10">
      <?= View::render('partials/flash') ?>
      <?= $content ?>
    </main>
  <?php endif ?>

  <footer <?= Theme::editable('footer') ?> class="border-t">
    <div class="wrap grid gap-8 py-10 sm:grid-cols-2 lg:grid-cols-4">
      <div class="lg:col-span-2">
        <p class="font-semibold"><?= View::e($siteName) ?></p>
        <?php if ($about = Theme::get('footer_about') ?: Setting::get('site_description')): ?>
          <p <?= Theme::editable('footer', 'footer_about') ?> class="mt-2 max-w-sm text-sm leading-6 text-muted-foreground"><?= View::e($about) ?></p>
        <?php endif ?>
      </div>

      <?php if ($footer): ?>
        <nav class="space-y-1.5 text-sm">
          <p class="font-medium">Bağlantılar</p>
          <?php foreach ($footer as $item): ?>
            <a href="<?= View::e($item['href']) ?>" <?= $item['new_tab'] ? 'target="_blank" rel="noopener"' : '' ?> class="block text-muted-foreground hover:text-foreground"><?= View::e($item['label']) ?></a>
          <?php endforeach ?>
        </nav>
      <?php endif ?>

      <?php if ($contact || $social): ?>
        <div class="space-y-1.5 text-sm">
          <p class="font-medium">İletişim</p>
          <?php if (!empty($contact['email'])): ?><a href="mailto:<?= View::e($contact['email']) ?>" class="block text-muted-foreground hover:text-foreground"><?= View::e($contact['email']) ?></a><?php endif ?>
          <?php if (!empty($contact['phone'])): ?><a href="tel:<?= View::e(preg_replace('/[^0-9+]/', '', $contact['phone'])) ?>" class="block text-muted-foreground hover:text-foreground"><?= View::e($contact['phone']) ?></a><?php endif ?>
          <?php if (!empty($contact['address'])): ?><p class="text-muted-foreground"><?= nl2br(View::e($contact['address'])) ?></p><?php endif ?>
          <?php if ($social): ?>
            <p class="flex flex-wrap gap-x-3 gap-y-1 pt-2">
              <?php foreach ($social as $name => $url): ?>
                <a href="<?= View::e($url) ?>" target="_blank" rel="noopener" class="text-muted-foreground hover:text-foreground"><?= $name ?></a>
              <?php endforeach ?>
            </p>
          <?php endif ?>
        </div>
      <?php endif ?>
    </div>
    <div class="border-t">
      <p class="wrap py-4 text-xs text-muted-foreground"><?= View::e(Setting::get('footer_text') ?: '© ' . date('Y') . ' ' . $siteName) ?></p>
    </div>
  </footer>

<?= Theme::footer() ?>
</body>
</html>
