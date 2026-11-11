<?php
namespace App\Services;

use App\Models\Media;
use Core\Database;
use Core\Hook;
use Core\Html;
use Core\Request;
use Core\Validator;

// Koleksiyonlar: modülün tanımladığı basit içerik listeleri (WordPress'teki özel yazı türleri gibi). Admin listesi, formu,
// sürükle-bırak sıralama ve gizle/göster hazır gelir; modül sadece alanları ve sitedeki görünümü yazar.
//
//   Collection::register('faq', [
//       'label' => 'Sıkça sorulanlar', 'singular' => 'Soru', 'icon' => 'help', 'permission' => 'faq.manage', 'order' => 40,
//       'title' => 'question',              // listede başlık olan alan
//       'subtitle' => 'category',           // isteğe bağlı: başlığın altındaki alan
//       'image' => null,                    // isteğe bağlı: listede küçük resim olan alan
//       'slug' => false,                    // true: başlıktan adres üretilir (kendi sayfası olanlar)
//       'group' => 'showcase', 'tab' => 'SSS',  // admin menüsünde grup ve sekmedeki kısa ad
//       'fields' => ['question' => ['label' => 'Soru', 'type' => 'text', 'rules' => 'required|max:255'], ...],
//   ]);
//
//   Collection::items('faq')                       görünür öğeler, sırayla: [['id', 'title', 'slug', ...alanlar], ...]
//   Collection::items('faq', fn($i) => $i['category'] === 'Ödeme')
//   Collection::findBySlug('portfolio', 'web-sitesi')
//
// Alan türleri: text, textarea, email, url, tel, number, select (options), checkbox, color, image, gallery (birden çok görsel)
// Admin: /admin/collections/{ad}. Olaylar: collection.saved ($ad, $öğe), collection.deleted ($ad, $öğe)
final class Collection
{
    private static array $defs = [];

    public static function register(string $name, array $def): void
    {
        self::$defs[$name] = $def + ['singular' => 'Öğe', 'icon' => 'layers', 'order' => 50, 'subtitle' => null, 'image' => null, 'slug' => false];
        $d = self::$defs[$name];
        Hook::on('admin.menu', fn(array $menu) => [...$menu,
            ['url' => "admin/collections/$name", 'label' => $d['label'], 'icon' => $d['icon'], 'order' => $d['order'], 'permission' => $d['permission'],
             'group' => $d['group'] ?? null, 'tab' => $d['tab'] ?? null],
        ]);
    }

    public static function def(string $name): ?array
    {
        return self::$defs[$name] ?? null;
    }

    public static function items(string $name, ?callable $filter = null, bool $all = false): array
    {
        $rows = Database::table('collection_items')->where('collection', $name)
            ->when(!$all, fn($q) => $q->where('visible', 1))
            ->orderBy('sort_order')->orderBy('id')->get();
        $items = array_map(self::decode(...), $rows);
        return $filter ? array_values(array_filter($items, $filter)) : $items;
    }

    public static function find(string $name, int $id): ?array
    {
        $row = Database::table('collection_items')->where('collection', $name)->where('id', $id)->first();
        return $row ? self::decode($row) : null;
    }

    public static function findBySlug(string $name, string $slug): ?array
    {
        $row = Database::table('collection_items')->where('collection', $name)->where('slug', $slug)->where('visible', 1)->first();
        return $row ? self::decode($row) : null;
    }

    // Formdan gelen değerleri doğrular, temizler ve kaydeder. Dönüş: öğe
    public static function save(string $name, ?array $item = null): array
    {
        $def = self::$defs[$name];
        $data = self::validate($def['fields'], $item);
        $title = trim((string) ($data[$def['title']] ?? '')) ?: $def['singular'];
        $values = [
            'title'      => mb_substr($title, 0, 255),
            'data'       => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR),
            'visible'    => ($v = Request::post('_visible')) === null ? (int) ($item['visible'] ?? 1) : (int) (bool) $v,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($def['slug']) {
            $wanted = trim((string) Request::post('_slug', '')) ?: $title;
            $values['slug'] = self::uniqueSlug($name, $wanted, $item['id'] ?? null);
        }

        if ($item) {
            Database::table('collection_items')->where('id', $item['id'])->update($values);
            $id = $item['id'];
            if ($def['slug'] && ($item['slug'] ?? null) && $item['slug'] !== $values['slug'] && !empty($def['url'])) {
                Hook::fire('url.moved', $def['url']($item['slug']), $def['url']($values['slug']));
            }
        } else {
            $max = (int) Database::table('collection_items')->where('collection', $name)->max('sort_order');
            $id = Database::table('collection_items')->insertGetId($values + ['collection' => $name, 'sort_order' => $max + 1, 'created_at' => date('Y-m-d H:i:s')]);
        }
        $saved = self::find($name, (int) $id);
        Hook::fire('collection.saved', $name, $saved);
        return $saved;
    }

    public static function delete(string $name, array $item): void
    {
        Database::table('collection_items')->where('collection', $name)->where('id', $item['id'])->delete();
        Hook::fire('collection.deleted', $name, $item);
    }

    public static function sort(string $name, array $ids): void
    {
        Database::transaction(function () use ($name, $ids) {
            foreach (array_values($ids) as $i => $id) {
                Database::table('collection_items')->where('collection', $name)->where('id', (int) $id)->update(['sort_order' => $i + 1]);
            }
        });
    }

    public static function setVisible(string $name, int $id, bool $visible): void
    {
        Database::table('collection_items')->where('collection', $name)->where('id', $id)->update(['visible' => (int) $visible]);
    }

    // Görsel alanının adresi (yoksa null)
    public static function imageUrl(?string $path): ?string
    {
        return $path ? Request::url('media/' . $path) : null;
    }

    // ---- İç

    private static function decode(array $row): array
    {
        $data = json_decode($row['data'], true) ?: [];
        return $data + ['id' => (int) $row['id'], 'title' => $row['title'], 'slug' => $row['slug'], 'visible' => (bool) $row['visible'],
                        'sort_order' => (int) $row['sort_order'], 'created_at' => $row['created_at'], 'updated_at' => $row['updated_at']];
    }

    private static function validate(array $fields, ?array $item): array
    {
        $rules = $labels = [];
        foreach ($fields as $key => $f) {
            if (in_array($f['type'], ['checkbox', 'image', 'gallery'], true)) continue;
            $rules[$key] = $f['rules'] ?? 'nullable|max:' . ($f['type'] === 'textarea' ? 5000 : 255);
            if ($f['type'] === 'select' && !str_contains($rules[$key], 'in:')) $rules[$key] .= '|in:' . implode(',', array_keys($f['options'] ?? []));
            $labels[$key] = $f['label'];
        }
        $data = $rules ? Validator::validate($rules, $labels) : [];

        $errors = [];
        foreach ($fields as $key => $f) {
            if ($f['type'] === 'checkbox') $data[$key] = (bool) Request::post($key);
            if ($f['type'] === 'image') {
                $path = trim((string) Request::post($key, ''));
                if ($path !== '' && !self::isImage($path)) $errors[$key] = 'Seçilen görsel medya kütüphanesinde bulunamadı.';
                $data[$key] = $path;
                if ($path === '' && str_contains($f['rules'] ?? '', 'required')) $errors[$key] = $f['label'] . ' seçin.';
            }
            if ($f['type'] === 'gallery') {
                $paths = json_decode((string) Request::post($key, '[]'), true);
                $paths = array_values(array_unique(array_filter(is_array($paths) ? $paths : [], fn($p) => is_string($p) && self::isImage($p))));
                $data[$key] = array_slice($paths, 0, (int) ($f['max'] ?? 60));
            }
            if (in_array($f['type'], ['text', 'textarea', 'email', 'url', 'tel'], true)) $data[$key] = trim((string) ($data[$key] ?? ''));
            if ($f['type'] === 'number' && isset($data[$key]) && $data[$key] !== '') $data[$key] = $data[$key] + 0;
        }
        if ($errors) Validator::fail($errors);
        return $data;
    }

    private static function isImage(string $path): bool
    {
        static $known = [];
        return $known[$path] ??= (bool) (($m = Media::findByPath($path)) && $m['type'] === 'image');
    }

    private static function uniqueSlug(string $name, string $text, ?int $exceptId): string
    {
        $base = substr(trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(strtr($text, [
            'ç' => 'c', 'Ç' => 'c', 'ğ' => 'g', 'Ğ' => 'g', 'ı' => 'i', 'İ' => 'i', 'ö' => 'o', 'Ö' => 'o', 'ş' => 's', 'Ş' => 's', 'ü' => 'u', 'Ü' => 'u',
        ]))), '-'), 0, 150) ?: 'ogeler';
        $slug = $base;
        for ($i = 2; ($row = Database::table('collection_items')->where('collection', $name)->where('slug', $slug)->first()) && (int) $row['id'] !== $exceptId; $i++) $slug = "$base-$i";
        return $slug;
    }
}
