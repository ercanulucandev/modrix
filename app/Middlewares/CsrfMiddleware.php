<?php
namespace App\Middlewares;

use Core\HttpException;
use Core\Middleware;

// GET dışındaki her istekte formdaki _token (ya da X-CSRF-TOKEN başlığı) oturumdakiyle eşleşmeli.
// Formlara View::csrf() eklemek yeterli.
class CsrfMiddleware extends Middleware
{
    public function handle(): void
    {
        if (in_array(Request::method(), ['GET', 'HEAD', 'OPTIONS'], true)) return;

        $token = (string) (Request::post('_token') ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!hash_equals(Session::token(), $token)) throw new HttpException(419);
    }
}
