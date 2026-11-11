<?php
namespace App\Services;

use Core\Hook;
use Core\Request;
use Core\Session;
use Core\Setting;

// Sayfa önbelleği (Ayarlar > Performans): ziyaretçilere sitenin sayfaları hazır HTML olarak verilir; veritabanı ve
// şablonlar hiç çalışmaz. Sadece giriş yapmamış ziyaretçinin GET isteklerinde, 200 dönen HTML sayfalarda çalışır.
//
//  - Panelden yapılan her kayıt (POST) ve komut satırı işlemleri önbelleği tamamen temizler; ayrıca süre (dakika) dolunca yenilenir
//    (zamanlanmış yazılar en geç bu sürede görünür).
//  - Formlardaki CSRF anahtarı önbellekte yer tutucuyla saklanır, her ziyaretçiye kendi anahtarı yazılır.
//  - Flash mesajı olan (ör. "Mesajınız gönderildi"), admin ya da site kullanıcısı girişli istekler önbelleğe bakmaz.
//  - Önbellekten verilen sayfada Hook 'cache.hit' ($path) çalışır (ör. Blog okunma sayısını artırır).
final class PageCache
{
    private const TOKEN = '__MODRIX_CSRF_TOKEN__';
    private const SKIP = ['/admin', '/api', '/media/', '/assets/', '/theme/', '/sitemap.xml', '/robots.txt', '/blog/feed.xml'];
    private const IGNORED_QUERY = ['fbclid', 'gclid', 'msclkid'];

    private static ?string $key = null;

    public static function enabled(): bool
    {
        return Setting::bool('cache_enabled');
    }

    public static function dir(): string
    {
        return ROOT . '/storage/cache/pages';
    }

    // request.before: önbellekte varsa gönderir ve isteği bitirir
    public static function serve(string $method, string $path): void
    {
        if (!self::enabled() || !($key = self::key($method, $path))) return;
        $file = self::dir() . "/$key.html";
        if (!is_file($file) || time() - filemtime($file) > self::ttl()) return;

        $html = file_get_contents($file);
        if ($html === false) return;
        Hook::fire('cache.hit', $path);
        header('Content-Type: text/html; charset=UTF-8');
        header('X-Cache: HIT');
        echo str_replace(self::TOKEN, Session::token(), $html);
        exit;
    }

    // response.after: uygun sayfayı önbelleğe yazar (çıktı değişmez)
    public static function store(string $html): string
    {
        if (!self::$key || http_response_code() !== 200 || $html === '' || !self::isHtmlResponse()) return $html;
        if (!empty($_SESSION['_flash_next'])) return $html;   // bu istekte flash yazıldıysa (yönlendirme öncesi vb.)

        $dir = self::dir();
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        $tmp = "$dir/" . self::$key . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (file_put_contents($tmp, str_replace(Session::token(), self::TOKEN, $html)) !== false) {
            @rename($tmp, "$dir/" . self::$key . '.html') || @unlink($tmp);
        }
        if (!headers_sent()) header('X-Cache: MISS');
        return $html;
    }

    public static function flush(): int
    {
        $count = 0;
        foreach (glob(self::dir() . '/*.{html,tmp}', GLOB_BRACE) ?: [] as $file) $count += (int) @unlink($file);
        return $count;
    }

    public static function stats(): array
    {
        $files = glob(self::dir() . '/*.html') ?: [];
        return ['count' => count($files), 'size' => array_sum(array_map('filesize', $files))];
    }

    // ---- İç

    private static function ttl(): int
    {
        return max(1, (int) Setting::get('cache_ttl', 60)) * 60;
    }

    // Önbelleğe uygun istekse anahtar (adres + izinli sorgu), değilse null
    private static function key(string $method, string $path): ?string
    {
        if ($method !== 'GET' && $method !== 'HEAD') return null;
        // Modüller kişiye özel sayfalarını ekler: Hook::on('cache.skip', fn(array $p) => [...$p, '/cart'])
        foreach (Hook::filter('cache.skip', self::SKIP) as $prefix) if (str_starts_with($path, $prefix)) return null;
        if (!empty($_SESSION['admin_id']) || !empty($_SESSION['user_id']) || !empty($_SESSION['_flash'])) return null;

        $query = [];
        foreach ($_GET as $name => $value) {
            if (in_array($name, self::IGNORED_QUERY, true) || str_starts_with((string) $name, 'utm_')) continue;
            if ($name !== 'page' || !ctype_digit((string) $value)) return null;   // arama, önizleme vb. önbelleğe girmez
            $query[$name] = $value;
        }
        return self::$key = hash('sha1', Request::url($path) . '?' . http_build_query($query));
    }

    private static function isHtmlResponse(): bool
    {
        foreach (headers_list() as $header) {
            if (stripos($header, 'Content-Type:') === 0 && stripos($header, 'text/html') === false) return false;
            if (stripos($header, 'Location:') === 0) return false;
        }
        return true;
    }
}
