<?php
namespace Core;

class Request
{
    // GET, POST, PUT, PATCH, DELETE (formlarda <input type="hidden" name="_method" value="DELETE">)
    public static function method(): string
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        return $method === 'POST' ? strtoupper($_POST['_method'] ?? 'POST') : $method;
    }

    // Uygulamanın bulunduğu klasörün URL yolu (otomatik): dev.test/ -> '',  localhost/dev/ -> '/dev'
    public static function base(): string
    {
        static $base = null;
        return $base ??= rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    }

    // Request::url('blog/5') -> /blog/5  (alt klasördeyse /dev/blog/5)
    // Çoklu dilde site adresleri geçerli dilin önekini alır: İngilizce sayfada -> /en/blog/5 (panel, medya, dosyalar almaz).
    // Başka dilin adresi: Request::url('blog', 'de') ya da Lang::url('blog', 'de')
    public static function url(string $path = '', ?string $lang = null): string
    {
        $path = ltrim($path, '/');
        $lang ??= Lang::current();
        if ($lang !== Lang::default() && Lang::localizable($path)) $path = $lang . ($path === '' ? '' : '/' . $path);
        return self::base() . '/' . $path;
    }

    // Uygulama içi yol, dil öneki olmadan: /en/blog -> /blog (geçerli dil: Lang::current())
    public static function path(): string
    {
        $path = self::rawPath();
        $lang = Lang::current();
        if ($lang !== Lang::default() && ($path === "/$lang" || str_starts_with($path, "/$lang/"))) $path = '/' . trim(substr($path, strlen($lang) + 1), '/');
        return $path;
    }

    // Tarayıcıdaki yol (dil öneki dahil)
    public static function rawPath(): string
    {
        $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $base = self::base();
        if ($base !== '' && str_starts_with($path, $base)) $path = substr($path, strlen($base));
        return '/' . trim($path, '/');
    }

    public static function get(string $key, $default = null)   { return $_GET[$key] ?? $default; }
    public static function post(string $key, $default = null)  { return $_POST[$key] ?? self::json()[$key] ?? $default; }
    public static function input(string $key, $default = null) { return self::all()[$key] ?? $default; }
    public static function all(): array                         { return array_merge($_GET, $_POST, self::json()); }
    public static function only(string ...$keys): array        { return array_intersect_key(self::all(), array_flip($keys)); }
    public static function has(string $key): bool              { return array_key_exists($key, self::all()); }

    // API istekleri için JSON gövde
    public static function json(): array
    {
        static $json = null;
        if ($json !== null) return $json;
        if (!str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) return $json = [];
        return $json = json_decode(file_get_contents('php://input') ?: '[]', true) ?: [];
    }

    public static function isAjax(): bool
    {
        return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
            || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
    }

    public static function ip(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}
