<?php
// index.php ve modrix (komut satırı) tarafından yüklenir.
define('ROOT', dirname(__DIR__));

// Çalışma klasörleri (sürüm ZIP'inden ya da git'ten gelen kurulumda boş olabilir): eksikse oluşturulur
foreach (['storage', 'storage/uploads', 'storage/logs', 'storage/tmp', 'storage/cache', 'storage/backups'] as $dir) {
    if (!is_dir(ROOT . "/$dir")) @mkdir(ROOT . "/$dir", 0775, true);
}
if (!is_file(ROOT . '/storage/.htaccess')) @file_put_contents(ROOT . '/storage/.htaccess', "Require all denied\n");

// 1. Autoload: composer install yapıldıysa vendor/autoload.php, yoksa composer.json'daki PSR-4 haritası
if (is_file(ROOT . '/vendor/autoload.php')) {
    require ROOT . '/vendor/autoload.php';
} else {
    $psr4 = json_decode(file_get_contents(ROOT . '/composer.json'), true)['autoload']['psr-4'] ?? [];
    spl_autoload_register(function ($class) use ($psr4) {
        foreach ($psr4 as $prefix => $dir) {
            if (str_starts_with($class, $prefix)) {
                $file = ROOT . '/' . rtrim($dir, '/') . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
                if (is_file($file)) require $file;
                return;
            }
        }
    });
}

// 2. Hata yönetimi (config'teki hatalar da yakalansın diye en başta)
Core\ErrorHandler::register();

// 3. Ayarlar
Core\Config::load(ROOT . '/app/Config');
date_default_timezone_set(Core\Config::get('app.timezone', 'Europe/Istanbul'));

// 4. Global takma adlar (app/Config/app.php -> aliases): her dosyada `use` yazmadan Session::get(), View::e() ...
//    Namespace içindeki dosyada da çalışır: App\Controllers\Session bulunamazsa Core\Session'a bağlanır.
//    Aynı adda gerçek bir sınıf varsa (ör. App\Models\Session) önce o yüklenir; takma ad devreye girmez.
spl_autoload_register(function ($class) {
    $short  = substr(strrchr('\\' . $class, '\\'), 1);
    $target = Core\Config::get('app.aliases', [])[$short] ?? null;
    if ($target && $target !== $class) class_alias($target, $class);
});
