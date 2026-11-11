<?php
namespace App\Middlewares;

use App\Models\User;
use Core\Middleware;

// Admin giriş ekranı: zaten girmişse panele
class AdminGuestMiddleware extends Middleware
{
    public function handle(): void
    {
        if (User::admin()) Response::redirect('admin');
    }
}
