<?php
namespace Core;

// Arama motorları için yapılandırılmış veri (schema.org, JSON-LD). Theme::head() <head> içine yazar.
//
//   Seo::add(['@type' => 'Article', 'headline' => $post['title'], ...]);   // controller'da, sayfaya özel
//
// Her sayfada otomatik: WebSite + kuruluş/kişi (Ayarlar > SEO > "Site kimdir"; ad, logo, iletişim, sosyal medya).
final class Seo
{
    private static array $items = [];

    public static function add(array $item): void
    {
        self::$items[] = $item;
    }

    public static function jsonLd(): string
    {
        $base = self::absolute('/');
        $name = (string) Setting::get('site_name', Config::get('app.name'));
        $type = (string) Setting::get('schema_type', 'Organization');

        $owner = array_filter([
            '@type'     => in_array($type, ['Organization', 'LocalBusiness', 'Person'], true) ? $type : 'Organization',
            '@id'       => $base . '#owner',
            'name'      => $name,
            'url'       => $base,
            'logo'      => $type !== 'Person' && ($logo = Setting::url('logo')) ? self::absolute($logo) : null,
            'image'     => $type === 'Person' && ($logo = Setting::url('logo')) ? self::absolute($logo) : null,
            'email'     => Setting::get('contact_email'),
            'telephone' => Setting::get('contact_phone'),
            'address'   => $type === 'LocalBusiness' ? Setting::get('contact_address') : null,
            'sameAs'    => array_values(array_filter([
                Setting::get('social_instagram'), Setting::get('social_x'), Setting::get('social_facebook'),
                Setting::get('social_linkedin'), Setting::get('social_youtube'),
            ])) ?: null,
        ]);

        $graph = [
            ['@type' => 'WebSite', '@id' => $base . '#website', 'name' => $name, 'url' => $base, 'publisher' => ['@id' => $base . '#owner'], 'inLanguage' => 'tr-TR'],
            $owner,
            ...Hook::filter('seo.schema', self::$items),
        ];
        return '<script type="application/ld+json">'
            . json_encode(['@context' => 'https://schema.org', '@graph' => $graph], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP)
            . '</script>';
    }

    // '/blog/x' ya da 'blog/x' -> 'https://site.com/blog/x'
    public static function absolute(string $path): string
    {
        if (preg_match('#^https?://#', $path)) return $path;
        $scheme = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
        $host = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        return $host . (str_starts_with($path, '/') && Request::base() !== '' && str_starts_with($path, Request::base()) ? $path : Request::url($path));
    }
}
