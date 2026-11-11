<?php
namespace Core;

use Throwable;

// Çoklu dil. Diller Admin > Sistem > Diller'den eklenir; ilki varsayılandır ve adreste önek almaz.
//
//   /hakkimizda        varsayılan dil (ör. Türkçe)
//   /en/about          İngilizce: Request::path() önekini atar ('/about'), Request::url() geri ekler
//
//   Lang::current()                       'en'
//   Lang::t('Tüm yazılar')                arayüz metni (Admin > Diller > Arayüz metinleri'nde çevrilir)
//   Lang::t(':n yorum', ['n' => 3])
//   Lang::url('blog', 'de')               /de/blog
//   Lang::setAlternates(['en' => 'blog/hello', 'tr' => 'blog/merhaba'])   bu sayfanın diğer dillerdeki adresi (dil seçici, hreflang)
//
// Ayarlar dile göre: bir ayarın 'anahtar__en' değeri varsa İngilizce sayfalarda Setting::get('anahtar') onu döndürür
// (site adı, tema yazıları, ana sayfa bölümleri, menü konumları... hepsi Setting üzerinden okunduğu için kendiliğinden).
// İçerik (sayfa, yazı) satırlarında 'lang' ve 'translation_group' kolonları vardır.
final class Lang
{
    public const SOURCE = 'tr';   // arayüz metinleri kodda bu dilde yazılır

    // Admin'de dil eklerken önerilenler
    public const KNOWN = [
        'tr' => 'Türkçe', 'en' => 'English', 'de' => 'Deutsch', 'fr' => 'Français', 'es' => 'Español', 'it' => 'Italiano',
        'nl' => 'Nederlands', 'ru' => 'Русский', 'ar' => 'العربية', 'fa' => 'فارسی', 'az' => 'Azərbaycan dili', 'ka' => 'ქართული',
        'el' => 'Ελληνικά', 'bg' => 'Български', 'ro' => 'Română', 'pl' => 'Polski', 'uk' => 'Українська', 'zh' => '中文', 'ja' => '日本語', 'ko' => '한국어',
    ];
    public const RTL = ['ar', 'fa', 'he', 'ur'];

    // Önek almayan (dile göre değişmeyen) adresler
    private const SHARED = ['admin', 'api', 'media', 'assets', 'theme', 'sitemap.xml', 'robots.txt', 'storage'];

    private static ?array $languages = null;
    private static ?string $current = null;
    private static array $alternates = [];
    private static array $strings = [];

    // ['tr' => ['code' => 'tr', 'name' => 'Türkçe'], 'en' => [...]]  (ilki varsayılan)
    public static function all(): array
    {
        if (self::$languages === null) {
            $list = json_decode((string) (Setting::raw()['languages'] ?? ''), true);
            $list = array_values(array_filter(is_array($list) ? $list : [], fn($l) => is_array($l) && preg_match('/^[a-z]{2}$/', $l['code'] ?? '')));
            self::$languages = $list ? array_column($list, null, 'code') : [self::SOURCE => ['code' => self::SOURCE, 'name' => self::KNOWN[self::SOURCE]]];
        }
        return self::$languages;
    }

    public static function default(): string
    {
        return (string) array_key_first(self::all());
    }

    public static function enabled(): bool
    {
        return count(self::all()) > 1;
    }

    public static function exists(string $code): bool
    {
        return isset(self::all()[$code]);
    }

    public static function name(string $code): string
    {
        return self::all()[$code]['name'] ?? self::KNOWN[$code] ?? strtoupper($code);
    }

    public static function current(): string
    {
        return self::$current ??= self::detect();
    }

    public static function isDefault(?string $code = null): bool
    {
        return ($code ?? self::current()) === self::default();
    }

    public static function rtl(?string $code = null): bool
    {
        return in_array($code ?? self::current(), self::RTL, true);
    }

    // Panelde düzenlenen içeriğin dili (Sayfalar/Yazılar listesindeki dil sekmesi); sitede geçerli dil
    public static function content(): string
    {
        if (!str_starts_with(Request::rawPath(), '/admin')) return self::current();
        $code = (string) Session::get('content_lang', '');
        return self::exists($code) ? $code : self::default();
    }

    public static function setContent(string $code): void
    {
        if (self::exists($code)) Session::set('content_lang', $code);
    }

    // Adres önek alır mı (panel, medya, dosyalar almaz)
    public static function localizable(string $path): bool
    {
        $first = explode('/', trim($path, '/'), 2)[0];
        return !in_array(strtolower(explode('?', $first)[0]), self::SHARED, true);
    }

    public static function url(string $path, string $code): string
    {
        return Request::url($path, $code);
    }

    // Önekli yol, kök klasör olmadan (site haritası vb.): localPath('about', 'en') -> 'en/about'
    public static function localPath(string $path, string $code): string
    {
        $path = trim($path, '/');
        return $code === self::default() || !self::localizable($path) ? $path : trim("$code/$path", '/');
    }

    // Dil seçici ve hreflang: [kod => tam yol]. Varsayılan: aynı adres diğer dilde
    public static function alternates(): array
    {
        $path = Request::path();
        $out = [];
        foreach (self::all() as $code => $_) {
            $target = array_key_exists($code, self::$alternates) ? self::$alternates[$code] : $path;
            $out[$code] = self::url($target ?? '', $code);
        }
        return $out;
    }

    // Sayfanın diğer dillerdeki karşılıkları: [kod => adres] (karşılığı yoksa null: o dilin ana sayfası)
    public static function setAlternates(array $map): void
    {
        self::$alternates = $map;
    }

    // Arayüz metni çevirisi (kaynak metin Türkçe). :ad yer tutucuları $vars ile doldurulur.
    public static function t(string $text, array $vars = []): string
    {
        $code = self::current();
        if ($code !== self::SOURCE) {
            if (!isset(self::$strings[$code])) {
                try {
                    self::$strings[$code] = Database::table('translations')->where('lang', $code)->pluck('translation', 'source');
                } catch (Throwable) {
                    self::$strings[$code] = [];
                }
            }
            $translated = self::$strings[$code][$text] ?? null;
            if ($translated !== null && $translated !== '') $text = $translated;
        }
        foreach ($vars as $k => $v) $text = str_replace(':' . $k, (string) $v, $text);
        return $text;
    }

    // Testler ve önizleme için
    public static function set(string $code): void
    {
        if (self::exists($code)) self::$current = $code;
    }

    public static function forget(): void
    {
        self::$languages = null;
        self::$current = null;
        self::$strings = [];
    }

    private static function detect(): string
    {
        $first = explode('/', trim(Request::rawPath(), '/'), 2)[0];
        return $first !== '' && $first !== self::default() && self::exists($first) ? $first : self::default();
    }
}
