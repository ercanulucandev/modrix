<?php
namespace Core;

use RuntimeException;

// Yönlendirme.
//
//   Router::get('/', [HomeController::class, 'index']);
//   Router::get('/blog/{slug}', [PostController::class, 'show'])->name('blog.show');
//   Router::post('/blog', [PostController::class, 'store'])->middleware('auth');
//   Router::get('/ping', fn() => ['ok' => true]);                 // dizi dönerse JSON
//
//   Router::group(['prefix' => '/admin', 'middleware' => ['auth']], function () {
//       Router::get('/', [AdminController::class, 'index']);      // /admin
//   });
//
//   Router::url('blog.show', ['slug' => 'merhaba'])               // /blog/merhaba
//
//   Router::fallback(fn(string $path) => ...)                     // hiçbir route eşleşmezse (GET); null dönerse 404
//       Ör. Sayfalar modülü /hakkimizda gibi adresleri böyle yakalar; tanımlı route'ları asla ezmez.
//
// Middleware adları app/Config/app.php içindeki 'middleware' listesinden gelir.
class Router
{
    /** @var Route[] */
    private static array $routes = [];
    private static array $groupStack = [];
    private static $notFound = null;
    private static array $fallbacks = [];

    public static function get(string $path, $action): Route    { return self::add(['GET'], $path, $action); }
    public static function post(string $path, $action): Route   { return self::add(['POST'], $path, $action); }
    public static function put(string $path, $action): Route    { return self::add(['PUT'], $path, $action); }
    public static function patch(string $path, $action): Route  { return self::add(['PATCH'], $path, $action); }
    public static function delete(string $path, $action): Route { return self::add(['DELETE'], $path, $action); }
    public static function any(string $path, $action): Route    { return self::add(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], $path, $action); }

    public static function add(array $methods, string $path, $action): Route
    {
        $prefix = '';
        $middleware = [];
        foreach (self::$groupStack as $g) {
            $prefix .= '/' . trim($g['prefix'] ?? '', '/');
            $middleware = [...$middleware, ...(array) ($g['middleware'] ?? [])];
        }

        // Öneksiz gruplar (sadece middleware) fazladan "/" bırakmasın: /admin//login -> /admin/login
        $full  = '/' . trim(preg_replace('#/+#', '/', $prefix . '/' . $path), '/');
        $route = new Route($methods, $full, $action);
        $route->middleware(...$middleware);
        return self::$routes[] = $route;
    }

    public static function group(array $options, callable $routes): void
    {
        self::$groupStack[] = $options;
        $routes();
        array_pop(self::$groupStack);
    }

    // Eşleşen route yoksa sırayla denenir (sadece GET/HEAD). Dönen değer çıktı olur; null -> sıradaki, en sonda 404.
    public static function fallback(callable $handler): void
    {
        self::$fallbacks[] = $handler;
    }

    // Route'ların ilk sabit parçaları: ['admin', 'api', 'blog', 'media', ...] (ör. sayfa adresi bunlarla çakışmasın)
    public static function reservedSegments(): array
    {
        $segments = [];
        foreach (self::$routes as $route) {
            $first = explode('/', trim($route->path, '/'))[0];
            if ($first !== '' && !str_contains($first, '{')) $segments[$first] = true;
        }
        return array_keys($segments);
    }

    public static function notFound(callable $handler): void
    {
        self::$notFound = $handler;
    }

    // İsimli route adresi: Router::url('blog.show', ['slug' => 'x'])
    public static function url(string $name, array $params = []): string
    {
        foreach (self::$routes as $route) {
            if ($route->name === $name) {
                $path = preg_replace_callback('#\{(\w+)\*\}#', fn($m) => implode('/', array_map('rawurlencode', explode('/', (string) ($params[$m[1]] ?? '')))), $route->path);
                $path = preg_replace_callback('#\{(\w+)\}#', fn($m) => rawurlencode((string) ($params[$m[1]] ?? '')), $path);
                return Request::url($path);
            }
        }
        throw new RuntimeException("Route bulunamadı: $name");
    }

    public static function dispatch(string $method, string $path): void
    {
        $allowed = false;
        foreach (self::$routes as $route) {
            $params = $route->match($path);
            if ($params === null) continue;
            if (!in_array($method, $route->methods, true)) {
                $allowed = true;
                continue;
            }

            Middleware::run($route->middleware);
            self::output(self::call($route->action, $params));
            return;
        }

        if (!$allowed && in_array($method, ['GET', 'HEAD'], true)) {
            foreach (self::$fallbacks as $fallback) {
                $result = $fallback($path);
                if ($result === null) continue;
                self::output($result);
                return;
            }
        }

        throw new HttpException($allowed ? 405 : 404);
    }

    // ErrorHandler 404'te çağırır. Router::notFound(...) tanımlı değilse null.
    public static function renderNotFound(): ?string
    {
        return self::$notFound ? (string) (self::$notFound)() : null;
    }

    private static function call($action, array $params)
    {
        // [Controller::class, 'metot']
        if (is_array($action) && is_string($action[0])) {
            [$class, $method] = $action;
            if (!class_exists($class)) throw new RuntimeException("Controller bulunamadı: $class");
            $action = [new $class, $method];
        }
        return call_user_func_array($action, array_values($params));
    }

    private static function output($result): void
    {
        if (is_array($result)) $result = Response::json($result);
        echo (string) $result;
    }
}
