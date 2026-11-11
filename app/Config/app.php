<?php
return [
    'name'     => 'Modrix',
    'debug'    => true,              // canlıda false: hata detayı gösterilmez, sadece log'a yazılır
    'timezone' => 'Europe/Istanbul',

    // Güncellemeler (Admin > Sistem > Güncellemeler): GitHub deposu (sahip/ad); sürümler bu deponun Releases'inden gelir
    'update' => ['repository' => 'ercanulucandev/modrix'],

    // Mağaza katalogları (Admin > Sistem > Mağaza): modüller ve temalar. HTTPS adresi ya da yerel dosya (geliştirme). Bkz. docs/store.md
    'store' => [
        'modules' => 'https://raw.githubusercontent.com/ercanulucandev/modrix-store/main/catalog.json',
        'themes'  => 'https://raw.githubusercontent.com/ercanulucandev/modrix-themes/main/catalog.json',
    ],

    // Route'larda ->middleware('auth') şeklinde kullanılan adlar
    'middleware' => [
        'auth'  => App\Middlewares\AuthMiddleware::class,
        'guest' => App\Middlewares\GuestMiddleware::class,
        'csrf'  => App\Middlewares\CsrfMiddleware::class,

        // Admin paneli (site kullanıcılarından ayrı oturum)
        'admin'       => App\Middlewares\AdminMiddleware::class,
        'admin.guest' => App\Middlewares\AdminGuestMiddleware::class,
        'can'         => App\Middlewares\CanMiddleware::class,      // ->middleware('can:pages.manage')

        'maintenance' => App\Middlewares\MaintenanceMiddleware::class,
    ],

    // Her istekte çalışanlar
    'global_middleware' => ['maintenance'],

    // app/Routes/web.php ve modül route'ları 'web', app/Routes/api.php 'api' grubundadır
    'middleware_groups' => [
        'web' => ['csrf'],
        'api' => [],
    ],

    // `use` yazmadan kullanılabilen sınıflar: Session::get(), View::e(), DB::all() ...
    'aliases' => [
        'App'        => Core\App::class,
        'Config'     => Core\Config::class,
        'DB'         => Core\Database::class,
        'Hook'       => Core\Hook::class,
        'Icon'       => Core\Icon::class,
        'Html'       => Core\Html::class,
        'Log'        => Core\Log::class,
        'Mail'       => Core\Mail::class,
        'Menu'       => Core\Menu::class,
        'Module'     => Core\Module::class,
        'Request'    => Core\Request::class,
        'Response'   => Core\Response::class,
        'Router'     => Core\Router::class,
        'Seo'        => Core\Seo::class,
        'Lang'       => Core\Lang::class,
        'Section'    => Core\Section::class,
        'Theme'      => Core\Theme::class,
        'Session'    => Core\Session::class,
        'Setting'    => Core\Setting::class,
        'Upload'     => Core\Upload::class,
        'Validator'  => Core\Validator::class,
        'View'       => Core\View::class,
    ],

    'log'    => ['path' => ROOT . '/storage/logs'],
    'upload' => ['path' => ROOT . '/storage/uploads', 'max_size' => 5 * 1024 * 1024],
];
