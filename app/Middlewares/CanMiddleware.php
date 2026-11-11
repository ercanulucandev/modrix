<?php
namespace App\Middlewares;

use App\Services\Access;
use Core\Middleware;

// Yetki kontrolü: ->middleware('can:pages.manage'). 'admin' middleware'inden sonra kullanılır.
class CanMiddleware extends Middleware
{
    public function handle(): void
    {
        $permission = $this->params[0] ?? '';
        if ($permission !== '' && Access::can($permission)) return;
        Response::abort(403, 'Bu işlem için yetkiniz yok.');
    }
}
