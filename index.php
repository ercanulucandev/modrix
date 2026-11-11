<?php
// Tek giriş noktası. Tüm istekler .htaccess ile buraya gelir.
require __DIR__ . '/core/bootstrap.php';

Core\App::run();
