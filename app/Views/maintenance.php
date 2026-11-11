<?php
// Bakım modu sayfası (MaintenanceMiddleware). Layout'suz ve CDN'siz: bakımdayken bile sade ve hızlı açılır.
$siteName = Setting::get('site_name', Config::get('app.name'));
?>
<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title><?= View::e($title) ?> · <?= View::e($siteName) ?></title>
  <?php if ($favicon = Setting::url('favicon')): ?><link rel="icon" href="<?= View::e($favicon) ?>"><?php endif ?>
  <style>
    *{box-sizing:border-box}
    body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;
      font:15px/1.6 Inter,ui-sans-serif,system-ui,sans-serif;background:#fafafa;color:#09090b}
    .box{max-width:420px;width:100%;border:1px solid #e4e4e7;border-radius:6px;background:#fff;padding:32px;text-align:center;box-shadow:0 1px 2px rgb(0 0 0/.05)}
    .badge{display:inline-block;border:1px solid #e4e4e7;border-radius:4px;padding:2px 8px;font-size:12px;font-weight:500;color:#71717a}
    h1{margin:16px 0 8px;font-size:22px;font-weight:600;letter-spacing:-.01em}
    p{margin:0;color:#71717a}
  </style>
</head>
<body>
  <div class="box">
    <span class="badge"><?= View::e($siteName) ?></span>
    <h1><?= View::e($title) ?></h1>
    <p><?= nl2br(View::e($message)) ?></p>
  </div>
</body>
</html>
