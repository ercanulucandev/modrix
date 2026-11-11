<?php
namespace Core;

use Throwable;

// Site ayarları (Admin > Ayarlar). Alan tanımları: app/Config/settings.php (+ 'admin.settings' hook'u)
// Modül ayarları modülün settings.php dosyasındadır (Admin > Modüller > Ayarlar); okuması aynıdır.
//
//   Setting::get('site_name')              değer; boşsa tanımdaki varsayılan
//   Setting::bool('maintenance_mode')      checkbox
//   Setting::url('logo')                   görsel alanının adresi (yoksa null)
//   Setting::title('Blog')                 "Blog · Site adı" (title_format'a göre)
//   Setting::set(['site_name' => 'X'])     kaydeder
class Setting
{
    private static ?array $values = null;
    private static ?array $groups = null;
    private static array $preview = [];
    private static array $localized = [];

    public static function get(string $key, $default = null)
    {
        $value = self::all()[$key] ?? null;
        if ($value !== null && $value !== '') return $value;
        return $default ?? self::field($key)['default'] ?? null;
    }

    public static function bool(string $key): bool
    {
        return (bool) self::get($key);
    }

    // Görsel alanı: /media/settings/2026/11/abc.png
    public static function url(string $key): ?string
    {
        $path = self::get($key);
        return $path ? Request::url('media/' . $path) : null;
    }

    public static function title(?string $title = null): string
    {
        $site = (string) self::get('site_name', Config::get('app.name'));
        if ($title === null || $title === '' || $title === $site) return $site;
        return strtr((string) self::get('title_format', '{title} · {site}'), ['{title}' => $title, '{site}' => $site]);
    }

    // Geçerli dile göre: 'anahtar__en' doluysa İngilizce sayfalarda 'anahtar' onun değeridir (bkz. Lang)
    public static function all(): array
    {
        $values = self::raw();
        $lang = Lang::current();
        if ($lang !== Lang::default()) $values = self::$localized[$lang] ??= self::localize($values, $lang);
        return self::$preview ? array_merge($values, self::$preview) : $values;
    }

    // Veritabanındaki değerler olduğu gibi (dil uygulanmadan)
    public static function raw(): array
    {
        if (self::$values === null) {
            try {
                self::$values = Database::table('settings')->pluck('value', 'key');
            } catch (Throwable) {
                self::$values = []; // tablo henüz yok (php modrix migrate çalışmamış)
            }
        }
        return self::$values;
    }

    // Ayarın bir dildeki karşılığının anahtarı: translationKey('site_name', 'en') -> 'site_name__en'
    public static function translationKey(string $key, string $lang): string
    {
        return $key . '__' . $lang;
    }

    private static function localize(array $values, string $lang): array
    {
        $suffix = '__' . $lang;
        foreach ($values as $key => $value) {
            if ($value !== null && $value !== '' && str_ends_with((string) $key, $suffix)) $values[substr((string) $key, 0, -strlen($suffix))] = $value;
        }
        return $values;
    }

    // Bu istek boyunca kaydedilmemiş değerleri kullan (Görünüm > Özelleştir önizlemesi; bkz. Theme::customizing)
    public static function preview(array $values): void
    {
        self::$preview = array_map(fn($v) => $v === null ? null : (string) $v, $values);
    }

    public static function set(array $values): void
    {
        $now = date('Y-m-d H:i:s');
        foreach ($values as $key => $value) {
            if (!preg_match('/^[a-z0-9_]{1,100}$/', (string) $key)) continue;
            Database::query(
                'INSERT INTO `settings` (`key`, `value`, `updated_at`) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `updated_at` = VALUES(`updated_at`)',
                [$key, $value === null ? null : (string) $value, $now]
            );
        }
        self::$values = null;
        self::$localized = [];
    }

    // Sekmeler: app/Config/settings.php + modüllerin 'admin.settings' ile eklediği
    public static function groups(): array
    {
        return self::$groups ??= Hook::filter('admin.settings', Config::get('settings', []));
    }

    public static function field(string $key): ?array
    {
        foreach (self::groups() as $group) {
            if (isset($group['fields'][$key])) return $group['fields'][$key];
        }
        // Modül ayarı: whatsapp_phone -> Whatsapp modülünün settings.php'si
        foreach (Module::active() as $folder) {
            if (!str_starts_with($key, strtolower($folder) . '_')) continue;
            $fields = Module::settings($folder)['fields'] ?? [];
            if (isset($fields[$key])) return $fields[$key];
        }
        return null;
    }

    // ---- Görünüm (layout'lar kullanır)

    // <style> içine: ana renk, üstündeki yazı rengi ve köşe yuvarlaklığı
    public static function themeCss(): string
    {
        $color = (string) self::get('primary_color', '#18181b');
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) $color = '#18181b';

        // Renk açıksa koyu, koyuysa açık yazı (algılanan parlaklık)
        [$r, $g, $b] = array_map('hexdec', str_split(substr($color, 1), 2));
        $foreground = (0.299 * $r + 0.587 * $g + 0.114 * $b) > 160 ? '#09090b' : '#fafafa';

        $base = ['none' => 0, 'small' => 4, 'medium' => 6, 'large' => 10][self::get('radius', 'small')] ?? 4;
        $sm = max(0, $base - 2);
        $lg = $base ? $base + 2 : 0;

        $css = "html:root{--color-primary:$color;--color-primary-foreground:$foreground;--color-ring:$color;"
             . "--radius-sm:{$sm}px;--radius-md:{$base}px;--radius-lg:{$lg}px;--radius-xl:{$lg}px}";
        // Koyu modda çok koyu bir ana renk görünmez: açık tona çevrilir (özel, canlı renkler olduğu gibi kalır)
        if ((0.299 * $r + 0.587 * $g + 0.114 * $b) < 60) $css .= 'html.dark:root{--color-primary:#fafafa;--color-primary-foreground:#18181b;--color-ring:#d4d4d8}';
        return $css;
    }
}
