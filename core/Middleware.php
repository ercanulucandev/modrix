<?php
namespace Core;

use RuntimeException;

// Middleware: controller'dan önce çalışır. İsteği durdurmak için yönlendirir ya da hata fırlatır.
//
//   namespace App\Middlewares;
//   use Core\Middleware;
//
//   class AuthMiddleware extends Middleware
//   {
//       public function handle(): void
//       {
//           if (!Session::has('user_id')) Response::redirect('login');
//       }
//   }
//
// Adlar app/Config/app.php -> 'middleware' içinde tanımlanır; 'global_middleware' her istekte çalışır.
// Parametreli kullanım: ->middleware('role:admin,editor')  ->  handle() içinde $this->params === ['admin', 'editor']
abstract class Middleware
{
    protected array $params = [];

    abstract public function handle(): void;

    // Route'un middleware'lerini sırayla çalıştırır
    public static function run(array $names): void
    {
        $aliases = Config::get('app.middleware', []);

        foreach ($names as $name) {
            [$alias, $args] = array_pad(explode(':', $name, 2), 2, '');
            $class = $aliases[$alias] ?? (class_exists($alias) ? $alias : null);
            if (!$class) throw new RuntimeException("Middleware tanımlı değil: $alias (app/Config/app.php)");

            $middleware = new $class;
            $middleware->params = $args !== '' ? explode(',', $args) : [];
            $middleware->handle();
        }
    }
}
