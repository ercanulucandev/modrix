<?php
// Theme::head() çıktısı: temaların <head> içine koyduğu ortak kısım (WordPress'teki wp_head gibi).
// Başlık, SEO, paylaşım etiketleri, favicon, Google Analytics, Ayarlar > Özel kod ve modüllerin 'site.head' eklentileri.
// Değişkenler: $title (sayfa başlığı ya da null), $description (sayfaya özel açıklama ya da null), $image (paylaşım görseli adresi ya da null)
$siteName    = Setting::get('site_name', Config::get('app.name'));
$pageTitle   = Setting::title($title);
$description = $description ?: (Setting::get('meta_description') ?? Setting::get('site_description'));
$ogImage     = $image ?: Setting::url('og_image');
$analytics   = Setting::get('google_analytics');
$scheme      = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
$host        = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$current     = $host . Request::url(Request::path());
?>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= View::e($pageTitle) ?></title>
  <?php if ($description): ?><meta name="description" content="<?= View::e($description) ?>"><?php endif ?>
  <?php if ($kw = Setting::get('meta_keywords')): ?><meta name="keywords" content="<?= View::e($kw) ?>"><?php endif ?>
  <?php if (Setting::bool('robots_noindex') || Theme::previewing() || Request::path() === '/search'): ?><meta name="robots" content="noindex, nofollow"><?php endif ?>
  <link rel="canonical" href="<?= View::e($current) ?>">
  <?php if (Lang::enabled()): // diğer dillerdeki karşılıklar (Google doğru dili göstersin) ?>
    <?php foreach (Lang::alternates() as $code => $url): ?><link rel="alternate" hreflang="<?= $code ?>" href="<?= View::e($host . $url) ?>">
    <?php endforeach ?><link rel="alternate" hreflang="x-default" href="<?= View::e($host . Lang::alternates()[Lang::default()]) ?>">
  <?php endif ?>
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="<?= View::e($siteName) ?>">
  <meta property="og:title" content="<?= View::e($pageTitle) ?>">
  <meta property="og:url" content="<?= View::e($current) ?>">
  <?php if ($description): ?><meta property="og:description" content="<?= View::e($description) ?>"><?php endif ?>
  <?php if ($ogImage): ?><meta property="og:image" content="<?= View::e($host . $ogImage) ?>"><meta name="twitter:card" content="summary_large_image"><?php endif ?>
  <?php if ($favicon = Setting::url('favicon')): ?><link rel="icon" href="<?= View::e($favicon) ?>"><?php endif ?>
  <?php if ($analytics && preg_match('/^G-[A-Z0-9]{4,20}$/', $analytics)): ?>
    <?php // Çerez onayı modülü açıksa "analitik" izni verilene kadar çalışmaz (type="text/plain" data-consent) ?>
    <?php $gate = Hook::filter('consent.enabled', false) ? ' type="text/plain" data-consent="analytics"' : ''; ?>
    <script async<?= $gate ?> <?= $gate ? 'data-src' : 'src' ?>="https://www.googletagmanager.com/gtag/js?id=<?= $analytics ?>"></script>
    <script<?= $gate ?>>window.dataLayer = window.dataLayer || []; function gtag(){dataLayer.push(arguments);} gtag('js', new Date()); gtag('config', '<?= $analytics ?>');</script>
  <?php endif ?>
  <?= Seo::jsonLd() ?>
  <?= Setting::get('head_code') ?>
  <?php // Modüllerin <head>'e eklediği HTML: Hook::on('site.head', fn(array $h) => [...$h, '<meta ...>']) ?>
  <?= implode("\n", Hook::filter('site.head', [])) ?>
