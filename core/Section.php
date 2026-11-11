<?php
namespace Core;

use Throwable;

// Bölümler (sections): sayfayı oluşturan, sürükle-bırak ile sıralanan hazır parçalar (Görünüm > Özelleştir).
//
// Bir bölüm türü bir klasördür:
//   {klasör}/schema.php   ad, açıklama, simge, alanlar (Özelleştir panelindeki form)
//   {klasör}/view.php     HTML. Değişkenler: $s (temizlenmiş alan değerleri), $section, $edit, $context
//
// Türlerin bulunduğu yerler (kimlik):
//   app/Sections/{ad}/                       'core.{ad}'     her temada kullanılabilir (genel bloklar)
//   app/Modules/{Modül}/Sections/{ad}/       '{modül}.{ad}'  modül aktifse
//   app/Themes/{Tema}/sections/{ad}/             '{ad}'          sadece o temada
//
// Bir alanın düzeni (sayfadaki bölümler ve değerleri) temaya özel ayar olarak JSON saklanır:
//   theme_{tema}_sections_{alan}  ->  [{ "id": "a1b2c3", "type": "hero", "hidden": false, "data": {...} }, ...]
// Kayıt yoksa temanın theme.json'daki "sections" düzeni kullanılır.
//
//   Section::render('home', ['moduleSections' => $sections])     tema görünümünde
//   Section::types()      Section::instances('home')      Section::normalize($list)
//
// Alan türleri: text, textarea, richtext, link, image, select, icon, checkbox, color, number, repeater (iç alanlarla liste)
final class Section
{
    private static array $types = [];

    // ================================================================ TÜRLER

    // ['hero' => ['id', 'name', 'description', 'icon', 'source', 'view', 'fields', 'requires'], ...]
    public static function types(?string $theme = null): array
    {
        $theme ??= Theme::active();
        if (isset(self::$types[$theme])) return self::$types[$theme];

        $types = [];
        $add = function (string $dir, string $id, string $source) use (&$types) {
            $schema = is_file("$dir/schema.php") ? require "$dir/schema.php" : null;
            if (!is_array($schema) || !is_file("$dir/view.php")) return;
            if (!empty($schema['requires']) && !Module::isActive((string) $schema['requires'])) return;
            $types[$id] = [
                'id'          => $id,
                'name'        => (string) ($schema['name'] ?? $id),
                'description' => (string) ($schema['description'] ?? ''),
                'icon'        => (string) ($schema['icon'] ?? 'layers'),
                'source'      => $source,
                'view'        => "$dir/view.php",
                'fields'      => self::cleanSchema((array) ($schema['fields'] ?? [])),
            ];
        };

        foreach (glob(ROOT . '/app/Themes/' . $theme . '/sections/*', GLOB_ONLYDIR) as $dir) {
            if (preg_match('/^[a-z][a-z0-9-]{0,40}$/', basename($dir))) $add($dir, basename($dir), 'Tema');
        }
        foreach (Module::active() as $module) {
            foreach (glob(ROOT . "/app/Modules/$module/Sections/*", GLOB_ONLYDIR) as $dir) {
                if (preg_match('/^[a-z][a-z0-9-]{0,40}$/', basename($dir))) $add($dir, strtolower($module) . '.' . basename($dir), Module::manifest($module)['name']);
            }
        }
        foreach (glob(ROOT . '/app/Sections/*', GLOB_ONLYDIR) as $dir) {
            if (preg_match('/^[a-z][a-z0-9-]{0,40}$/', basename($dir))) $add($dir, 'core.' . basename($dir), 'Genel');
        }
        return self::$types[$theme] = $types;
    }

    // Alan tanımlarını düzenler: bilinmeyen tür -> text, repeater'ın iç alanları da
    private static function cleanSchema(array $fields): array
    {
        $out = [];
        foreach ($fields as $name => $f) {
            if (!preg_match('/^[a-z][a-z0-9_]{0,40}$/', (string) $name) || !is_array($f)) continue;
            $f['type'] = in_array($f['type'] ?? '', ['text', 'textarea', 'richtext', 'link', 'image', 'select', 'icon', 'checkbox', 'color', 'number', 'repeater'], true) ? $f['type'] : 'text';
            $f['label'] = (string) ($f['label'] ?? $name);
            if ($f['type'] === 'repeater') $f['fields'] = self::cleanSchema((array) ($f['fields'] ?? []));
            $out[$name] = $f;
        }
        return $out;
    }

    // ================================================================ DÜZEN

    public static function key(string $area, ?string $theme = null): string
    {
        return Theme::settingPrefix($theme) . 'sections_' . $area;
    }

    // Temanın bölüm alanları (theme.json -> sections): ['home']
    public static function areas(?string $theme = null): array
    {
        return array_keys(Theme::manifest($theme ?? Theme::active())['sections']);
    }

    // Alandaki bölümler (temizlenmiş, varsayılanlarla tamamlanmış). Kayıt yoksa theme.json düzeni.
    public static function instances(string $area, ?string $theme = null): array
    {
        $theme ??= Theme::active();
        $raw = Setting::all()[self::key($area, $theme)] ?? null;
        $list = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
        if (!is_array($list)) $list = Theme::manifest($theme)['sections'][$area] ?? [];
        return self::normalize($list, $theme);
    }

    // Formdan/JSON'dan gelen listeyi doğrular: bilinmeyen türler atılır, her alan türüne göre temizlenir
    public static function normalize(array $list, ?string $theme = null): array
    {
        $types = self::types($theme);
        $out = [];
        $ids = [];
        foreach (array_slice($list, 0, 60) as $index => $item) {
            if (!is_array($item) || !isset($types[$item['type'] ?? ''])) continue;
            // Kimliği olmayan (theme.json varsayılanı) bölümlere her istekte aynı kimlik: önizleme ile panel eşleşsin
            $id = preg_match('/^[a-z0-9]{4,16}$/', (string) ($item['id'] ?? '')) ? $item['id'] : substr(md5($item['type'] . '#' . $index), 0, 8);
            if (isset($ids[$id])) $id = self::newId();
            $ids[$id] = true;
            $out[] = [
                'id'     => $id,
                'type'   => $item['type'],
                'hidden' => !empty($item['hidden']),
                'data'   => self::clean($types[$item['type']]['fields'], is_array($item['data'] ?? null) ? $item['data'] : []),
            ];
        }
        return $out;
    }

    public static function newId(): string
    {
        return substr(bin2hex(random_bytes(6)), 0, 8);
    }

    // Değerleri alan türüne göre temizler; olmayanlar varsayılanla doldurulur
    public static function clean(array $fields, array $data): array
    {
        $out = [];
        foreach ($fields as $name => $f) {
            $has = array_key_exists($name, $data);
            $value = $has ? $data[$name] : ($f['default'] ?? null);
            $out[$name] = self::cleanValue($f, $value, $has);
        }
        return $out;
    }

    private static function cleanValue(array $f, $value, bool $fromInput)
    {
        $string = is_scalar($value) ? trim((string) $value) : '';
        switch ($f['type']) {
            case 'checkbox':
                return (bool) $value && $value !== '0';
            case 'number':
                $n = is_numeric($value) ? $value + 0 : ($f['default'] ?? 0);
                if (isset($f['min'])) $n = max($f['min'], $n);
                if (isset($f['max'])) $n = min($f['max'], $n);
                return $n;
            case 'color':
                return preg_match('/^#[0-9a-fA-F]{6}$/', $string) ? $string : (string) ($f['default'] ?? '');
            case 'select':
            case 'icon':
                $options = $f['type'] === 'icon' ? array_keys(Icon::all()) : array_map('strval', array_keys((array) ($f['options'] ?? [])));
                return in_array($string, $options, true) ? $string : (string) ($f['default'] ?? ($options[0] ?? ''));
            case 'link':
                return $string === '' || Menu::validUrl($string) ? $string : '';
            case 'image':
                // Medya kütüphanesi yolu (media/2026/11/x.webp)
                return preg_match('#^[A-Za-z0-9_./-]{1,255}$#', $string) && !str_contains($string, '..') ? $string : '';
            case 'richtext':
                return Html::sanitize(mb_substr($string, 0, (int) ($f['max'] ?? 50000)));
            case 'repeater':
                $items = is_array($value) ? array_values(array_filter($value, 'is_array')) : [];
                $items = array_slice($items, 0, (int) ($f['max'] ?? 24));
                return array_map(fn($item) => self::clean($f['fields'], $item), $items);
            case 'textarea':
                return mb_substr($string, 0, (int) ($f['max'] ?? 5000));
            default:
                return mb_substr($string, 0, (int) ($f['max'] ?? 300));
        }
    }

    // ================================================================ YAZDIRMA

    // Alanın görünür bölümlerini yazdırır. $context her bölüm görünümüne $context olarak gider.
    public static function render(string $area, array $context = []): string
    {
        $types = self::types();
        $customizing = (bool) Theme::customizing();
        $html = '';

        foreach (self::instances($area) as $instance) {
            if ($instance['hidden']) continue;
            $type = $types[$instance['type']];
            $sectionKey = 'section:' . $instance['id'];

            // Özelleştir önizlemesinde tıklanabilir alan işareti: $edit('title'), repeater içinde $edit('items.0.title')
            $edit = fn(string $field) => $customizing
                ? 'data-customize-field="' . htmlspecialchars("$sectionKey:$field", ENT_QUOTES) . '" data-customize-section="' . $sectionKey . '"'
                : '';

            try {
                $out = View::renderFile($type['view'], ['s' => $instance['data'], 'section' => $instance, 'edit' => $edit, 'context' => $context]);
            } catch (Throwable $e) {
                Log::error('Bölüm yazdırılamadı', ['type' => $instance['type'], 'error' => $e->getMessage()]);
                $out = $customizing ? '<div style="padding:16px;border:2px dashed #dc2626;color:#dc2626;font:14px sans-serif">"' . htmlspecialchars($type['name']) . '" bölümünde hata: ' . htmlspecialchars($e->getMessage()) . '</div>' : '';
            }

            $html .= $customizing ? '<div data-customize="' . $sectionKey . '">' . $out . '</div>' : $out;
        }
        return $html;
    }

    // Özelleştir önizlemesindeki "… · düzenle" etiketleri için: ['section:a1b2' => 'Giriş', ...]
    public static function labels(): array
    {
        $types = self::types();
        $labels = [];
        foreach (self::areas() as $area) {
            foreach (self::instances($area) as $i) $labels['section:' . $i['id']] = $types[$i['type']]['name'];
        }
        return $labels;
    }
}
