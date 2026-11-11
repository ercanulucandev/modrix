<?php
namespace App\Controllers;

use App\Models\Redirect;
use Core\Controller;
use Core\Database;
use Core\Hook;
use Core\Request;
use Core\Seo;
use Core\Setting;

// Site haritası (/sitemap.xml), robots.txt, yönlendirmeler ve 404 kaydı
class SeoController extends Controller
{
    // Adresler modüllerden: Hook::on('sitemap.urls', fn($u) => [...$u, ['loc' => 'blog/x', 'lastmod' => '2026-11-10 10:00:00']])
    public function sitemap(): never
    {
        $urls = Hook::filter('sitemap.urls', [['loc' => '/', 'priority' => '1.0']]);
        $seen = [];

        header('Content-Type: application/xml; charset=utf-8');
        header('X-Robots-Tag: noindex');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $u) {
            $loc = Seo::absolute((string) ($u['loc'] ?? ''));
            if (isset($seen[$loc])) continue;
            $seen[$loc] = true;
            echo '  <url><loc>' . htmlspecialchars($loc, ENT_XML1) . '</loc>';
            if (!empty($u['lastmod'])) echo '<lastmod>' . date('c', strtotime($u['lastmod'])) . '</lastmod>';
            if (!empty($u['priority'])) echo '<priority>' . htmlspecialchars((string) $u['priority'], ENT_XML1) . '</priority>';
            echo "</url>\n";
        }
        echo "</urlset>\n";
        exit;
    }

    public function robots(): never
    {
        header('Content-Type: text/plain; charset=utf-8');
        if (Setting::bool('robots_noindex')) {
            echo "# Ayarlar > SEO > \"Arama motorlarından gizle\" açık\nUser-agent: *\nDisallow: /\n";
            exit;
        }
        echo "User-agent: *\nDisallow: /admin\n";
        $extra = trim((string) Setting::get('robots_extra', ''));
        if ($extra !== '') echo $extra . "\n";
        echo "\nSitemap: " . Seo::absolute('sitemap.xml') . "\n";
        exit;
    }

    // Router::fallback: hiçbir sayfa karşılamıyorsa yönlendirme var mı
    public static function followRedirect(string $path): ?string
    {
        try {
            $target = Redirect::resolve($path);
        } catch (\Throwable) {
            return null;   // tablo henüz yok
        }
        if (!$target) return null;
        header('Location: ' . $target['url'], true, $target['code']);
        exit;
    }

    // 404'leri kaydeder (Admin > SEO > Bulunamayanlar). Dosya/panel adresleri ve botların tarama gürültüsü kaydedilmez.
    public static function logNotFound(): void
    {
        $path = Redirect::normalize(Request::path());
        if ($path === '' || mb_strlen($path) > 500) return;
        if (preg_match('#^(admin|api|assets|theme|media)(/|$)#', $path)) return;
        if (preg_match('#\.(php|env|git|asp|aspx|jsp|cgi|sql|bak|zip|ini|log|yml|xml)$|wp-|xmlrpc|\.well-known#i', $path)) return;

        $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        $now = date('Y-m-d H:i:s');
        try {
            Database::query(
                'INSERT INTO not_found_log (path, hits, referer, first_seen, last_seen) VALUES (?, 1, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE hits = hits + 1, last_seen = VALUES(last_seen), referer = COALESCE(VALUES(referer), referer)',
                [$path, $referer !== '' ? mb_substr($referer, 0, 500) : null, $now, $now]
            );
        } catch (\Throwable) {
        }
    }
}
