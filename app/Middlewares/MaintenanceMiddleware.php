<?php
namespace App\Middlewares;

use App\Models\User;
use Core\Middleware;

// Bakım modu (Admin > Ayarlar > Bakım modu). Her istekte çalışır (app/Config/app.php -> global_middleware).
// Admin paneli, admin girişi yapmış kullanıcı ve ayarlardaki görseller etkilenmez.
class MaintenanceMiddleware extends Middleware
{
    public function handle(): void
    {
        if (!Setting::bool('maintenance_mode')) return;

        $path = Request::path();
        if ($path === '/admin' || str_starts_with($path, '/admin/') || str_starts_with($path, '/media/') || User::admin()) return;

        http_response_code(503);
        header('Retry-After: 3600');
        if (str_starts_with($path, '/api/')) {
            echo Response::json(['error' => Setting::get('maintenance_message'), 'status' => 503], 503);
        } else {
            echo View::render('maintenance', ['title' => 'Bakımdayız', 'message' => Setting::get('maintenance_message')]);
        }
        exit;
    }
}
