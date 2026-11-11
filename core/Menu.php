<?php
namespace Core;

use RuntimeException;

// Menüler (Görünüm > Menüler). Bir menü öğelerden oluşur (en fazla 3 seviye) ve temanın
// menü konumlarına (theme.json -> "menus") atanır. Tema layout'unda:
//
//   foreach (Menu::location('header') as $item) {
//       $item['label'], $item['href'], $item['active'], $item['new_tab'], $item['children'] (aynı yapı)
//   }
//
// Konuma menü atanmamışsa temanın İLK konumu modüllerin site menüsünü gösterir (module.json -> menu.site),
// diğerleri boş döner. Böylece yeni kurulan site menüsüz kalmaz.
class Menu
{
    public const MAX_DEPTH = 3;
    public const MAX_ITEMS = 200;

    private static array $cache = [];

    // ================================================================ SİTE

    public static function location(string $location): array
    {
        $theme = Theme::active();
        $key = "$theme.$location." . Lang::current();
        if (isset(self::$cache[$key])) return self::$cache[$key];

        $id = (int) Setting::get(self::locationKey($location, $theme));
        $items = $id ? self::items($id) : null;

        if ($items === null) {
            $first = array_key_first(Theme::manifest($theme)['menus']);
            $items = $location === $first ? self::fallback() : [];
        }
        return self::$cache[$key] = self::decorate($items);
    }

    // Menünün öğeleri ağaç olarak (menü yoksa null)
    public static function items(int $menuId): ?array
    {
        if (!Database::table('menus')->where('id', $menuId)->exists()) return null;
        $rows = Database::table('menu_items')->where('menu_id', $menuId)->orderBy('sort_order')->get();
        return self::tree($rows);
    }

    // Atanmış menü yokken: "Ana sayfa" + modüllerin menu.site öğeleri
    private static function fallback(): array
    {
        $items = Hook::filter('site.menu', [['url' => '/', 'label' => 'Ana sayfa', 'order' => 0]]);
        usort($items, fn($a, $b) => ($a['order'] ?? 50) <=> ($b['order'] ?? 50));
        return array_map(fn($i) => ['label' => $i['label'], 'url' => $i['url'], 'new_tab' => false, 'children' => []], $items);
    }

    // href + active ekler (alt öğesi aktifse üst öğe de aktif sayılır)
    private static function decorate(array $items): array
    {
        $path = trim(Request::path(), '/');
        foreach ($items as &$item) {
            $item['href'] = self::href($item['url']);
            $item['children'] = self::decorate($item['children'] ?? []);
            $item['active'] = self::matches($item['url'], $path) || in_array(true, array_column($item['children'], 'active'), true);
        }
        return $items;
    }

    // 'blog' -> /alt-klasor/blog ; dış adresler, #çapa, mailto:, tel: olduğu gibi
    public static function href(string $url): string
    {
        if (preg_match('#^(https?://|mailto:|tel:|\#)#i', $url)) return $url;
        return Request::url($url);
    }

    private static function matches(string $url, string $path): bool
    {
        if (preg_match('#^(https?://|mailto:|tel:|\#)#i', $url)) return false;
        $url = trim((string) parse_url($url, PHP_URL_PATH), '/');
        return $url === '' ? $path === '' : ($path === $url || str_starts_with($path, "$url/"));
    }

    private static function tree(array $rows, ?int $parent = null): array
    {
        $items = [];
        foreach ($rows as $row) {
            if (($row['parent_id'] === null ? null : (int) $row['parent_id']) !== $parent) continue;
            $items[] = [
                'id'       => (int) $row['id'],
                'label'    => $row['label'],
                'url'      => $row['url'],
                'new_tab'  => (bool) $row['new_tab'],
                'children' => self::tree($rows, (int) $row['id']),
            ];
        }
        return $items;
    }

    // ================================================================ YÖNETİM

    public static function locationKey(string $location, ?string $theme = null): string
    {
        return 'menu_location_' . Theme::slug($theme ?? Theme::saved()) . '_' . $location;
    }

    // Temanın konumları ve atanmış menüler: ['header' => ['label' => 'Üst menü', 'menu_id' => 3], ...]
    public static function locations(?string $theme = null): array
    {
        $theme ??= Theme::saved();
        $result = [];
        foreach (Theme::manifest($theme)['menus'] as $key => $label) {
            $result[$key] = ['label' => $label, 'menu_id' => (int) (Setting::raw()[self::locationKey($key, $theme)] ?? 0) ?: null];
            // Çoklu dil: her konumun diğer dillerdeki menüsü ('header__en'); boşsa varsayılan dildeki menü kullanılır
            foreach (array_slice(array_keys(Lang::all()), 1) as $code) {
                $result["{$key}__$code"] = ['label' => "$label · " . Lang::name($code), 'lang' => $code,
                    'menu_id' => (int) (Setting::raw()[Setting::translationKey(self::locationKey($key, $theme), $code)] ?? 0) ?: null];
            }
        }
        return $result;
    }

    // ['header' => 3, 'footer' => null]
    public static function assign(array $assignments, ?string $theme = null): void
    {
        $theme ??= Theme::saved();
        $locations = Theme::manifest($theme)['menus'];
        $values = [];
        foreach ($assignments as $location => $menuId) {
            [$base, $lang] = array_pad(explode('__', (string) $location, 2), 2, null);
            if (!isset($locations[$base]) || ($lang !== null && (!Lang::exists($lang) || Lang::isDefault($lang)))) continue;
            $menuId = (int) $menuId;
            if ($menuId && !Database::table('menus')->where('id', $menuId)->exists()) throw new RuntimeException('Menü bulunamadı');
            $key = self::locationKey($base, $theme);
            $values[$lang ? Setting::translationKey($key, $lang) : $key] = $menuId ?: null;
        }
        Setting::set($values);
    }

    // Öğeleri baştan yazar. $tree: [['label', 'url', 'new_tab', 'children' => [...]], ...] (doğrulanmış olmalı)
    public static function replaceItems(int $menuId, array $tree): void
    {
        Database::transaction(function () use ($menuId, $tree) {
            Database::table('menu_items')->where('menu_id', $menuId)->delete();
            $insert = function (array $items, ?int $parent) use (&$insert, $menuId) {
                foreach (array_values($items) as $i => $item) {
                    $id = Database::table('menu_items')->insertGetId([
                        'menu_id' => $menuId, 'parent_id' => $parent, 'label' => $item['label'],
                        'url' => $item['url'], 'new_tab' => $item['new_tab'] ? 1 : 0, 'sort_order' => $i,
                    ]);
                    $insert($item['children'] ?? [], (int) $id);
                }
            };
            $insert($tree, null);
            Database::table('menus')->where('id', $menuId)->update(['updated_at' => date('Y-m-d H:i:s')]);
        });
    }

    // Formdan gelen ağacı doğrular ve temizler; hata varsa RuntimeException
    public static function cleanTree($items, int $depth = 1, int &$count = 0): array
    {
        if (!is_array($items)) throw new RuntimeException('Menü öğeleri geçersiz');
        if ($items && $depth > self::MAX_DEPTH) throw new RuntimeException('Menü en fazla ' . self::MAX_DEPTH . ' seviye olabilir');

        $clean = [];
        foreach ($items as $item) {
            if (++$count > self::MAX_ITEMS) throw new RuntimeException('Bir menüde en fazla ' . self::MAX_ITEMS . ' öğe olabilir');

            $label = trim((string) ($item['label'] ?? ''));
            $url = trim((string) ($item['url'] ?? ''));
            if ($label === '' || mb_strlen($label) > 100) throw new RuntimeException('Her öğenin 1-100 karakter bir adı olmalı');
            if (!self::validUrl($url)) throw new RuntimeException("\"$label\" için bağlantı geçersiz");

            $clean[] = [
                'label'    => $label,
                'url'      => $url,
                'new_tab'  => !empty($item['new_tab']),
                'children' => self::cleanTree($item['children'] ?? [], $depth + 1, $count),
            ];
        }
        return $clean;
    }

    // İzin verilen: site içi yol (blog, /hakkimizda?x=1), http(s), #çapa, mailto:, tel:  — javascript: vb. değil
    public static function validUrl(string $url): bool
    {
        if ($url === '' || strlen($url) > 500) return false;
        if (preg_match('#^https?://[^\s<>"]+$#i', $url)) return filter_var($url, FILTER_VALIDATE_URL) !== false;
        if (preg_match('#^(mailto:[^\s<>"]+|tel:[0-9+\-() ]+|\#[A-Za-z0-9_\-]*)$#i', $url)) return true;
        return (bool) preg_match('#^/?[A-Za-z0-9\-._~/%?=&+\#]*$#', $url) && !str_starts_with($url, '//');
    }
}
