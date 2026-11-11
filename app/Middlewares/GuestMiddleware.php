<?php
namespace App\Middlewares;

use Core\Middleware;

// Giriş yapmamış olmalı (giriş / kayıt sayfaları)
class GuestMiddleware extends Middleware
{
    public function handle(): void
    {
        if (Session::has('user_id')) Response::redirect('/');
    }
}
