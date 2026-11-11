<?php
namespace App\Services;

use App\Models\Media;
use Core\Request;

// Duyarlı görseller: telefona küçük, geniş ekrana büyük kopya (srcset). Kopyalar yüklemede üretilir (MediaService::SIZES).
//
//   İçerik: Hook 'content.html' ile yazı/sayfa içeriğindeki medya görsellerine srcset, sizes, width/height ve lazy eklenir.
//   Şablon: <img src="..." + ResponsiveImages::attrs($url, '(max-width: 640px) 100vw, 33vw') + alt="">  (srcset, sizes, width, height döner)
final class ResponsiveImages
{
    private static array $cache = [];

    public static function apply(string $html): string
    {
        if (!str_contains($html, '<img')) return $html;
        preg_match_all('/<img\b[^>]*>/i', $html, $tags);
        self::load(array_filter(array_map(fn($tag) => self::path(self::attr($tag, 'src') ?? ''), $tags[0])));

        return preg_replace_callback('/<img\b[^>]*>/i', function ($m) {
            $tag = $m[0];
            $add = '';
            $media = self::$cache[self::path(self::attr($tag, 'src') ?? '') ?? ''] ?? null;
            if ($media && !self::attr($tag, 'srcset') && ($srcset = self::srcset($media))) {
                $add .= ' srcset="' . htmlspecialchars($srcset, ENT_QUOTES) . '" sizes="' . self::sizesFor((string) self::attr($tag, 'class')) . '"';
            }
            if ($media && $media['width'] && !self::attr($tag, 'width')) $add .= " width=\"{$media['width']}\" height=\"{$media['height']}\"";
            if (!self::attr($tag, 'loading')) $add .= ' loading="lazy"';
            if (!self::attr($tag, 'decoding')) $add .= ' decoding="async"';
            return $add === '' ? $tag : preg_replace('/\s*\/?>$/', $add . '>', $tag);
        }, $html);
    }

    // Şablonlar için: ' srcset="..." sizes="..." width=".." height=".."' (medya görseli değilse boş)
    public static function attrs(?string $url, string $sizes = '100vw'): string
    {
        $path = self::path((string) $url);
        if (!$path) return '';
        self::load([$path]);
        $media = self::$cache[$path] ?? null;
        if (!$media) return '';
        $out = ($srcset = self::srcset($media)) ? ' srcset="' . htmlspecialchars($srcset, ENT_QUOTES) . '" sizes="' . htmlspecialchars($sizes, ENT_QUOTES) . '"' : '';
        return $out . ($media['width'] ? " width=\"{$media['width']}\" height=\"{$media['height']}\"" : '');
    }

    public static function srcset(array $media): ?string
    {
        $sizes = json_decode((string) ($media['sizes'] ?? ''), true) ?: [];
        if (!$sizes) return null;
        ksort($sizes);
        $list = [];
        foreach ($sizes as $width => $path) $list[] = Media::url($path) . " {$width}w";
        if ($media['width']) $list[] = Media::url($media['path']) . " {$media['width']}w";
        return implode(', ', $list);
    }

    // ---- İç

    // Editörde seçilen boyuta göre tarayıcıya "görsel ekranda ne kadar yer kaplar" ipucu
    private static function sizesFor(string $class): string
    {
        return match (true) {
            str_contains($class, 'img-sm') => '(max-width: 639px) 75vw, 260px',
            str_contains($class, 'img-md') => '(max-width: 639px) 75vw, 400px',
            str_contains($class, 'img-lg') => '(max-width: 639px) 100vw, 600px',
            default                        => '(max-width: 860px) 100vw, 800px',
        };
    }

    private static function load(array $paths): void
    {
        $paths = array_values(array_diff(array_unique($paths), array_keys(self::$cache)));
        if (!$paths) return;
        foreach ($paths as $p) self::$cache[$p] = null;
        foreach (Media::query()->whereIn('path', $paths)->where('type', 'image')->get() as $m) self::$cache[$m['path']] = $m;
    }

    // https://site/media/media/2026/11/x.webp -> media/2026/11/x.webp (sitenin medya adresi değilse null)
    private static function path(string $url): ?string
    {
        $url = html_entity_decode($url, ENT_QUOTES);
        $base = Request::url('media/');
        if (str_starts_with($url, $base)) $rest = substr($url, strlen($base));
        elseif (preg_match('#^/(?:[^/]+/)*?media/(media/.+)$#', (string) parse_url($url, PHP_URL_PATH), $m)) $rest = $m[1];
        else return null;
        $rest = preg_replace('/[?#].*$/', '', $rest);
        return str_starts_with($rest, 'media/') ? $rest : null;
    }

    private static function attr(string $tag, string $name): ?string
    {
        return preg_match('/\s' . $name . '\s*=\s*("([^"]*)"|\'([^\']*)\')/i', $tag, $m) ? ($m[2] !== '' ? $m[2] : ($m[3] ?? '')) : null;
    }
}
