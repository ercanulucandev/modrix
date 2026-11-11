<?php
namespace Core;

// Uygulamanın akışı:
//   1. bootstrap: autoload, hata yönetimi, config, global takma adlar
//   2. app/Hooks.php, aktif modüllerin hooks.php + module.json menüleri, etkin temanın functions.php -> Hook 'app.boot'
//   3. app/Routes/web.php + aktif modüllerin routes.php   -> 'web' middleware grubu
//      app/Routes/admin.php                               -> /admin öneki, 'web' grubu
//      app/Routes/api.php + aktif modüllerin api.php      -> /api öneki, 'api' grubu
//   4. global middleware + route middleware'leri + controller
class App
{
    // Modrix sürümü. Yeni sürüm: burayı artırın, php modrix release:pack, GitHub'da v1.2.3 etiketiyle sürüm (bkz. docs/updates.md)
    public const VERSION = '1.0.3';

    public static function run(): void
    {
        Session::start();

        // Yeni sunucu: kurulum sihirbazı (/install). Kurulu sitede devreye girmez.
        if (class_exists(\App\Services\Installer::class) && !\App\Services\Installer::installed()) {
            \App\Services\Installer::handle();
            return;
        }

        require ROOT . '/app/Hooks.php';
        Module::boot();
        Theme::boot();
        Hook::fire('app.boot');

        self::loadRoutes();

        $method = Request::method();
        $path   = Request::path();

        Hook::fire('request.before', $method, $path);
        ob_start();
        Middleware::run(Config::get('app.global_middleware', []));
        Router::dispatch($method, $path);
        echo Hook::filter('response.after', ob_get_clean());
    }

    private static function loadRoutes(): void
    {
        $groups = Config::get('app.middleware_groups', []);

        Router::group(['middleware' => $groups['web'] ?? []], function () {
            require ROOT . '/app/Routes/web.php';
            Router::group(['prefix' => '/admin'], fn() => require ROOT . '/app/Routes/admin.php');
            foreach (Module::files('routes.php') as $file) require $file;
        });

        Router::group(['prefix' => '/api', 'middleware' => $groups['api'] ?? []], function () {
            require ROOT . '/app/Routes/api.php';
            foreach (Module::files('api.php') as $file) require $file;
        });
    }
}
