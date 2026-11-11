<?php
namespace Core;

use RuntimeException;
use Throwable;

// Modül yöneticisi.
//
// Her modül app/Modules/{Ad}/ klasöründedir ve bir module.json taşır:
//   {
//     "name": "Blog", "version": "1.0.0", "description": "...", "author": "...",
//     "requires": { "php": "8.1" },
//     "menu": {
//       "site":  [{ "url": "blog", "label": "Blog", "order": 20 }],
//       "admin": [{ "url": "admin/blog", "label": "Blog yazıları", "icon": "document", "order": 20 }]
//     }
//   }
//
// Durumlar: kurulmamış (klasör var, tabloda yok) -> install() -> aktif <-> pasif -> uninstall()
// Sadece AKTİF modüllerin routes.php / api.php dosyaları ve menüleri yüklenir.
//
//   Module::all()                 klasördeki tüm modüller + durumları
//   Module::active()              aktif modül adları
//   Module::isActive('Blog')
//   Module::install('Blog')       migration'ları çalıştırır, aktif eder
//   Module::update('Blog')        yeni migration'ları çalıştırır, sürümü günceller
//   Module::enable / disable('Blog')
//   Module::uninstall('Blog')     .down.sql dosyalarıyla tabloları geri alır, kaydı siler (veri silinir!)
//   Module::deleteFiles('Blog')   kurulmamış modülün klasörünü siler
//   Module::settings('Blog')      modülün ayar alanları (settings.php) ya da null
//   Module::iconFile('Blog')      modül görseli (icon.svg|png|webp|jpg) ya da null
class Module
{
    private const TABLE = 'modules';
    private static ?array $installed = null;
    private static array $settings = [];

    // ================================================================ BULMA

    // ['Blog' => ['name' => 'Blog', 'version' => '1.0.0', ..., 'dir' => ..., 'state' => 'active|inactive|not_installed',
    //             'installed_version' => '1.0.0', 'update' => false, 'error' => null]]
    public static function all(): array
    {
        $installed = self::installed();
        $modules = [];

        foreach (glob(ROOT . '/app/Modules/*', GLOB_ONLYDIR) as $dir) {
            $folder = basename($dir);
            try {
                $m = self::manifest($folder);
                $m['error'] = null;
            } catch (Throwable $e) {
                $m = ['name' => $folder, 'version' => '?', 'description' => '', 'author' => '', 'menu' => [], 'error' => $e->getMessage()];
            }

            $row = $installed[$folder] ?? null;
            $m['folder'] = $folder;
            $m['dir'] = $dir;
            $m['state'] = $row ? $row['status'] : 'not_installed';
            $m['installed_version'] = $row['version'] ?? null;
            $m['update'] = $row && $m['error'] === null && version_compare($m['version'], $row['version'], '>');
            $m['settings'] = $row && $m['error'] === null && is_file("$dir/settings.php");
            $icon = self::iconFile($folder);
            $m['icon'] = $icon ? Request::url("admin/modules/$folder/icon") . '?v=' . self::assetVersion($icon) : null;
            $modules[$folder] = $m;
        }

        ksort($modules);
        return $modules;
    }

    public static function active(): array
    {
        return array_keys(array_filter(self::installed(), fn($r) => $r['status'] === 'active' && is_dir(ROOT . '/app/Modules/' . $r['name'])));
    }

    public static function isActive(string $folder): bool
    {
        return in_array($folder, self::active(), true);
    }

    // Adresteki küçük harfli addan aktif modül klasörünü bulur: 'blog' -> 'Blog' (yoksa null)
    public static function activeFolder(string $slug): ?string
    {
        if (!preg_match('/^[a-z0-9]{1,50}$/', $slug)) return null;
        foreach (self::active() as $folder) {
            if (strtolower($folder) === $slug) return $folder;
        }
        return null;
    }

    // module.json'u okur ve doğrular
    public static function manifest(string $folder): array
    {
        self::assertFolder($folder);
        $file = ROOT . "/app/Modules/$folder/module.json";
        if (!is_file($file)) throw new RuntimeException('module.json bulunamadı');
        return self::validateManifest(json_decode((string) file_get_contents($file), true), $folder);
    }

    public static function validateManifest($m, string $folder): array
    {
        if (!is_array($m)) throw new RuntimeException('module.json geçerli bir JSON değil');
        if (empty($m['name']) || !is_string($m['name'])) throw new RuntimeException('module.json: "name" zorunlu');
        if (empty($m['version']) || !preg_match('/^\d+\.\d+\.\d+$/', (string) $m['version'])) throw new RuntimeException('module.json: "version" 1.0.0 biçiminde olmalı');

        $php = $m['requires']['php'] ?? null;
        if ($php && version_compare(PHP_VERSION, $php, '<')) throw new RuntimeException("PHP $php ya da üstü gerekli (sunucu: " . PHP_VERSION . ')');

        return [
            'name'        => $m['name'],
            'version'     => $m['version'],
            'description' => (string) ($m['description'] ?? ''),
            'author'      => (string) ($m['author'] ?? ''),
            'requires'    => (array) ($m['requires'] ?? []),
            'menu'        => (array) ($m['menu'] ?? []),
            'permissions' => array_filter((array) ($m['permissions'] ?? []), fn($label, $key) => is_string($label) && preg_match('/^[a-z0-9_]+\.[a-z0-9_.]+$/', (string) $key), ARRAY_FILTER_USE_BOTH),
        ];
    }

    // Modülün ayar alanları: app/Modules/{Ad}/settings.php -> ['description' => '...', 'fields' => [...]]
    // Alanlar app/Config/settings.php ile aynı biçimdedir; anahtarlar modül adıyla başlamalıdır (whatsapp_phone).
    // Ayarı yoksa ya da modül kurulu değilse null. Sayfası: Admin > Modüller > {Ad} > Ayarlar
    public static function settings(string $folder): ?array
    {
        if (array_key_exists($folder, self::$settings)) return self::$settings[$folder];

        self::assertFolder($folder);
        $file = ROOT . "/app/Modules/$folder/settings.php";
        if (!isset(self::installed()[$folder]) || !is_file($file)) return self::$settings[$folder] = null;

        $def = require $file;
        $prefix = strtolower($folder) . '_';
        $fields = [];
        foreach ((array) ($def['fields'] ?? []) as $key => $f) {
            // Başka modülün ya da core'un ayarını ezmesin
            if (!str_starts_with((string) $key, $prefix) || empty($f['type']) || empty($f['label'])) {
                Log::warning("Modül ayarı yok sayıldı: $folder.$key (anahtar '$prefix' ile başlamalı; type ve label zorunlu)");
                continue;
            }
            $fields[$key] = $f;
        }

        return self::$settings[$folder] = $fields ? [
            'label'       => self::manifest($folder)['name'],
            'description' => (string) ($def['description'] ?? ''),
            'fields'      => $fields,
        ] : null;
    }

    // Modül görseli: klasör kökünde icon.svg / icon.png / icon.webp / icon.jpg (kare, en az 128 px önerilir).
    // Yoksa admin listesinde baş harfli renkli kutu gösterilir.
    public const ICONS = ['svg' => 'image/svg+xml', 'png' => 'image/png', 'webp' => 'image/webp', 'jpg' => 'image/jpeg'];

    public static function iconFile(string $folder): ?string
    {
        if (!preg_match('/^[A-Z][A-Za-z0-9]{0,49}$/', $folder)) return null;
        foreach (array_keys(self::ICONS) as $ext) {
            $file = ROOT . "/app/Modules/$folder/icon.$ext";
            if (is_file($file)) return $file;
        }
        return null;
    }

    // ================================================================ AÇILIŞ

    // App her istekte çağırır: aktif modüllerin hooks.php dosyalarını yükler, menülerini hook'lara bağlar
    public static function boot(): void
    {
        foreach (self::files('hooks.php') as $file) require $file;

        foreach (self::active() as $folder) {
            try {
                $menu = self::manifest($folder)['menu'];
            } catch (Throwable $e) {
                Log::error("Modül yüklenemedi: $folder", ['error' => $e->getMessage()]);
                continue;
            }
            foreach (['site', 'admin'] as $area) {
                $items = array_values(array_filter((array) ($menu[$area] ?? []), fn($i) => !empty($i['url']) && !empty($i['label'])));
                if ($items) Hook::on("$area.menu", fn(array $current) => [...$current, ...$items]);
            }
        }
    }

    // Modül asset adresi (değişince tarayıcı yenisini alsın diye sürüm etiketiyle):
    //   <script type="module" src="<?= Module::asset('Blog', 'admin/posts-page.js') ? >"></script>
    //   -> /assets/blog/admin/posts-page.js?v=8c92f2099c   (app/Modules/Blog/Assets/admin/posts-page.js)
    public static function asset(string $folder, string $path): string
    {
        self::assertFolder($folder);
        $path = ltrim(str_replace('\\', '/', $path), '/');
        $file = ROOT . "/app/Modules/$folder/Assets/$path";
        return Request::url('assets/' . strtolower($folder) . "/$path") . (is_file($file) ? '?v=' . self::assetVersion($file) : '');
    }

    // Dosya değişince değişen kısa etiket (tarih ya da başka bilgi içermez)
    public static function assetVersion(string $file): string
    {
        return substr(md5($file . '|' . filemtime($file) . '|' . filesize($file)), 0, 10);
    }

    // Aktif modüllerin belirli dosyaları (routes.php, api.php)
    public static function files(string $name): array
    {
        $files = [];
        foreach (self::active() as $folder) {
            $file = ROOT . "/app/Modules/$folder/$name";
            if (is_file($file)) $files[] = $file;
        }
        return $files;
    }

    // ================================================================ YAŞAM DÖNGÜSÜ

    public static function install(string $folder): void
    {
        $m = self::manifest($folder);
        if (isset(self::installed()[$folder])) throw new RuntimeException("$folder zaten kurulu");

        Migrator::migrateModule($folder);
        Database::table(self::TABLE)->insert([
            'name' => $folder, 'version' => $m['version'], 'status' => 'active',
            'installed_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        self::$installed = null;
        Hook::fire('module.installed', $folder);
        Log::info("Modül kuruldu: $folder {$m['version']}");
    }

    public static function update(string $folder): void
    {
        $m = self::manifest($folder);
        $row = self::installed()[$folder] ?? throw new RuntimeException("$folder kurulu değil");

        Migrator::migrateModule($folder);
        Database::table(self::TABLE)->where('name', $folder)->update(['version' => $m['version'], 'updated_at' => date('Y-m-d H:i:s')]);
        self::$installed = null;
        Hook::fire('module.updated', $folder, $row['version'], $m['version']);
        Log::info("Modül güncellendi: $folder {$row['version']} -> {$m['version']}");
    }

    public static function enable(string $folder): void
    {
        self::setStatus($folder, 'active');
    }

    public static function disable(string $folder): void
    {
        self::setStatus($folder, 'inactive');
    }

    // Tabloları .down.sql dosyalarıyla geri alır ve kaydı siler. VERİ SİLİNİR.
    public static function uninstall(string $folder): void
    {
        self::assertFolder($folder);
        if (!isset(self::installed()[$folder])) throw new RuntimeException("$folder kurulu değil");

        Migrator::rollbackModule($folder);
        Database::table(self::TABLE)->where('name', $folder)->delete();
        self::$installed = null;
        Hook::fire('module.uninstalled', $folder);
        Log::warning("Modül kaldırıldı: $folder");
    }

    // Sadece kurulmamış modülün dosyaları silinebilir
    public static function deleteFiles(string $folder): void
    {
        self::assertFolder($folder);
        if (isset(self::installed()[$folder])) throw new RuntimeException('Önce modülü kaldırın');

        $dir = ROOT . "/app/Modules/$folder";
        if (!is_dir($dir)) throw new RuntimeException("$folder bulunamadı");
        self::removeDir($dir);
        Log::warning("Modül dosyaları silindi: $folder");
    }

    // ================================================================ İÇ

    private static function installed(): array
    {
        if (self::$installed !== null) return self::$installed;
        try {
            $rows = Database::table(self::TABLE)->get();
        } catch (Throwable $e) {
            // Tablo henüz yok (ilk kurulumda php modrix migrate çalışmamış): hiçbir modül aktif değil
            return self::$installed = [];
        }
        return self::$installed = array_column($rows, null, 'name');
    }

    private static function setStatus(string $folder, string $status): void
    {
        self::assertFolder($folder);
        if (!isset(self::installed()[$folder])) throw new RuntimeException("$folder kurulu değil");
        if ($status === 'active') self::manifest($folder); // bozuk modül aktif edilmesin

        Database::table(self::TABLE)->where('name', $folder)->update(['status' => $status, 'updated_at' => date('Y-m-d H:i:s')]);
        self::$installed = null;
        Log::info("Modül " . ($status === 'active' ? 'aktif edildi' : 'pasife alındı') . ": $folder");
    }

    public static function assertFolder(string $folder): void
    {
        if (!preg_match('/^[A-Z][A-Za-z0-9]{0,49}$/', $folder)) throw new RuntimeException("Geçersiz modül adı: $folder");
        if (strtolower($folder) === 'app') throw new RuntimeException('"App" modül adı olarak kullanılamaz (core için ayrılmış)');
    }

    // Symlink ya da Windows junction mı? (PHP junction'ı is_link ile tanımaz: gerçek yol, klasörün kendi yolundan farklıysa bağlantıdır)
    public static function isLink(string $path): bool
    {
        if (is_link($path)) return true;
        $real = realpath($path);
        $parent = realpath(dirname($path));
        return $real !== false && $parent !== false && strcasecmp($real, $parent . DIRECTORY_SEPARATOR . basename($path)) !== 0;
    }

    public static function removeDir(string $dir): void
    {
        // Bağlantıysa (symlink / Windows junction) sadece bağlantı kaldırılır; hedefteki dosyalara dokunulmaz
        if (self::isLink($dir)) {
            PHP_OS_FAMILY === 'Windows' ? rmdir($dir) : unlink($dir);
            return;
        }
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        rmdir($dir);
    }
}
