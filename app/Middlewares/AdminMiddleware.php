<?php
namespace App\Middlewares;

use App\Models\User;
use Core\Middleware;

// Admin paneline girmiş olmalı. Oturum site kullanıcılarından ayrıdır (admin_id).
class AdminMiddleware extends Middleware
{
    public function handle(): void
    {
        if ($user = User::admin()) {
            if (\App\Services\TwoFactor::required() && !\App\Services\TwoFactor::enabled($user) && !str_starts_with(Request::path(), '/admin/profile/security')) {
                if (Request::isAjax()) {
                    echo Response::json(['error' => 'Önce iki adımlı doğrulamayı açın (Profilim > Güvenlik).', 'status' => 403], 403);
                    exit;
                }
                Session::flash('info', 'Bu sitede iki adımlı doğrulama zorunlu. Devam etmek için açın.');
                Response::redirect('admin/profile/security');
            }
            return;
        }

        Session::forget('admin_id'); // silinmiş ya da pasife alınmış olabilir

        // AJAX / API isteğinde yönlendirme yerine JSON 401 (JS oturumun düştüğünü anlar)
        if (Request::isAjax()) {
            echo Response::json(['error' => 'Oturum süresi doldu. Lütfen tekrar giriş yapın.', 'status' => 401], 401);
            exit;
        }

        Session::flash('info', 'Devam etmek için giriş yapın.');
        Response::redirect('admin/login');
    }
}
