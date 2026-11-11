<?php
namespace App\Middlewares;

use Core\Middleware;

// Giriş yapmış olmalı. Parametre verilirse rol de kontrol edilir: ->middleware('auth:admin')
class AuthMiddleware extends Middleware
{
    public function handle(): void
    {
        if (!Session::has('user_id')) {
            Session::flash('info', 'Devam etmek için giriş yapın.');
            Response::redirect('login');
        }

        if ($this->params && !in_array(Session::get('user_role'), $this->params, true)) {
            Response::abort(403);
        }
    }
}
