<?php
namespace App\Services;

use Core\App;
use Core\Config;
use Core\Module;
use Core\ModuleInstaller;
use Core\Setting;
use Core\Theme;
use Core\ThemeInstaller;
use RuntimeException;
use Throwable;

// Mağaza: modüller (modrix-modules deposu) ve temalar (modrix-themes deposu). Admin > Sistem > Mağaza.
//
//   Store::catalog('modules')   Store::catalog('themes')
//   Store::install('Blog')      Store::install('Redmo', 'themes')
//
// Katalog (catalog.json; adres: app/Config/app.php -> store.modules / store.themes, ya da yerel dosya yolu):
//   { "modules": [ { "slug": "Blog", "name": "Blog", "version": "2.1.0", "description": "...", "author": "Modrix",
//       "category": "İçerik", "icon": "icons/Blog.svg", "download": "packages/Blog-2.1.0.zip", "sha256": "…",
//       "requires": { "modrix": "1.0.0", "php": "8.1" }, "price": 0, "buy_url": "" } ] }
//   Temalar: { "themes": [ { ... aynı alanlar, "screenshot": "screenshots/Redmo.svg" } ] }
// Göreli adresler katalog dosyasının yanına göre çözülür. sha256 zorunludur.
// Üretmek: php modrix store:build [--themes] [--out=klasör] [--base=adres] (varsayılan çıktı: ../modrix-modules ya da ../modrix-themes)
final class Store
{
    public const TYPES = ['modules' => 'Modüller', 'themes' => 'Temalar'];
    private const TTL = 21600;   // 6 saat
    private const DEFAULTS = [
        'modules' => 'https://raw.githubusercontent.com/ercanulucandev/modrix-modules/main/catalog.json',
        'themes'  => 'https://raw.githubusercontent.com/ercanulucandev/modrix-themes/main/catalog.json',
    ];

    public static function source(string $type = 'modules'): string
    {
        return (string) (Setting::get("store_catalog_$type") ?: Config::get("app.store.$type", self::DEFAULTS[$type]));
    }

    // ['items' => [slug => ...], 'checked_at', 'source', 'error'?]
    public static function catalog(bool $refresh = false, string $type = 'modules'): array
    {
        $file = ROOT . "/storage/cache/store-$type.json";
        $cached = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        if (!$refresh && is_array($cached) && ($cached['checked_at'] ?? 0) > time() - self::TTL && ($cached['source'] ?? '') === self::source($type)) return $cached;

        try {
            $data = json_decode(self::fetch(self::source($type)), true);
            if (!is_array($data) || !isset($data[$type]) || !is_array($data[$type])) throw new RuntimeException('Katalog biçimi geçersiz.');
            $items = [];
            foreach ($data[$type] as $m) {
                if (!is_array($m) || !preg_match('/^[A-Z][A-Za-z0-9]{0,49}$/', (string) ($m['slug'] ?? '')) || !preg_match('/^\d+\.\d+\.\d+$/', (string) ($m['version'] ?? ''))) continue;
                $items[$m['slug']] = [
                    'slug' => $m['slug'], 'name' => (string) ($m['name'] ?? $m['slug']), 'version' => $m['version'],
                    'description' => (string) ($m['description'] ?? ''), 'author' => (string) ($m['author'] ?? ''), 'category' => (string) ($m['category'] ?? 'Diğer'),
                    'icon' => isset($m['icon']) ? self::resolve((string) $m['icon'], $type) : null,
                    'screenshot' => isset($m['screenshot']) ? self::resolve((string) $m['screenshot'], $type) : null,
                    'download' => self::resolve((string) ($m['download'] ?? ''), $type),
                    'sha256' => strtolower((string) ($m['sha256'] ?? '')), 'requires' => (array) ($m['requires'] ?? []),
                    'price' => (float) ($m['price'] ?? 0), 'buy_url' => (string) ($m['buy_url'] ?? ''), 'homepage' => (string) ($m['homepage'] ?? ''),
                ];
            }
            $info = ['items' => $items, 'checked_at' => time(), 'source' => self::source($type)];
        } catch (Throwable $e) {
            $info = ['items' => $cached['items'] ?? [], 'error' => $e->getMessage(), 'checked_at' => time(), 'source' => self::source($type)];
        }
        if (!is_dir(dirname($file))) mkdir(dirname($file), 0775, true);
        file_put_contents($file, json_encode($info, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $info;
    }

    // Kurulu olana göre durum: [install | update | installed | incompatible, ek bilgi]
    public static function status(array $item, string $type = 'modules'): array
    {
        $have = self::installedVersion($item['slug'], $type);
        $problem = self::incompatible($item);
        if ($have === null) return $problem ? ['incompatible', $problem] : ['install', null];
        if (version_compare($item['version'], $have, '>')) return $problem ? ['incompatible', $problem] : ['update', $have];
        return ['installed', $have];
    }

    // Kur ya da güncelle. Dönüş: ['folder', 'action', 'version']
    public static function install(string $slug, string $type = 'modules'): array
    {
        $item = self::catalog(false, $type)['items'][$slug] ?? throw new RuntimeException('Katalogda bulunamadı.');
        if ($problem = self::incompatible($item)) throw new RuntimeException($problem);
        if ($item['price'] > 0 && !self::license($slug)) throw new RuntimeException('Ücretli: önce lisans anahtarını girin.');
        if (!preg_match('/^[a-f0-9]{64}$/', $item['sha256'])) throw new RuntimeException('Katalogda doğrulama özeti (sha256) yok; güvenlik için kurulmaz.');

        $tmp = ROOT . '/storage/tmp/store-' . bin2hex(random_bytes(6)) . '.zip';
        if (!is_dir(dirname($tmp))) mkdir(dirname($tmp), 0775, true);
        try {
            self::fetch($item['download'], $tmp, $item['price'] > 0 ? self::license($slug) : null);
            if (!hash_equals($item['sha256'], hash_file('sha256', $tmp))) throw new RuntimeException('İndirilen dosyanın özeti katalogdakiyle tutmuyor; kurulmadı.');
            $result = $type === 'themes' ? ThemeInstaller::fromZip($tmp) : ModuleInstaller::fromZip($tmp);
            if ($result['folder'] !== $slug) throw new RuntimeException('Paketteki ad katalogdakiyle aynı değil.');
        } finally {
            @unlink($tmp);
        }
        PageCache::flush();
        $what = $type === 'themes' ? 'temayı' : 'modülü';
        Activity::log('module.store', ($result['action'] === 'updated' ? "Mağazadan $what güncelledi: " : "Mağazadan $what kurdu: ") . "{$item['name']} v{$result['version']}", 'admin/store' . ($type === 'themes' ? '?type=themes' : ''));
        return $result;
    }

    public static function license(string $slug): ?string
    {
        return Crypt::decrypt((string) Setting::get('store_license_' . strtolower($slug))) ?: null;
    }

    public static function setLicense(string $slug, string $key): void
    {
        Setting::set(['store_license_' . strtolower($slug) => $key === '' ? null : Crypt::encrypt($key)]);
    }

    // Yayıncı için: kurulu tüm modülleri (ya da başlangıç teması dışındaki temaları) paketler.
    // Çıktı klasörü (mağaza deposunun yerel kopyası): catalog.json, packages/*.zip, icons/ (screenshots/),
    // modules/ (themes/) altında okunabilir kaynak. Elle eklenen fiyat, kategori, buy_url korunur.
    // Varsayılan çıktı: çekirdeğin yanındaki modrix-modules / modrix-themes klasörü, yoksa dist/modules / dist/themes.
    // app/Modules/X kaynağın kendisine bağlıysa (junction/symlink) kaynak kopyalanmaz; kaynak klasörü hiçbir zaman silinmez.
    public static function build(string $base = '', string $type = 'modules', ?string $out = null): string
    {
        $dir = rtrim(str_replace('\\', '/', $out ?? self::defaultOut($type)), '/');
        if ($base === '') $base = preg_match('#^https://#', self::source($type)) ? preg_replace('#[^/]*$#', '', self::source($type)) : '';
        foreach (['packages', $type === 'themes' ? 'screenshots' : 'icons', $type] as $sub) if (!is_dir("$dir/$sub")) mkdir("$dir/$sub", 0775, true);
        $existing = is_file("$dir/catalog.json") ? (json_decode((string) file_get_contents("$dir/catalog.json"), true)[$type] ?? []) : [];
        $extra = array_column($existing, null, 'slug');
        foreach (glob("$dir/packages/*.zip") ?: [] as $old) unlink($old);

        $items = [];
        $sources = $type === 'themes' ? array_diff_key(Theme::all(), [Theme::DEFAULT => true]) : Module::all();
        foreach ($sources as $folder => $m) {
            if (!empty($m['error'])) continue;
            $zip = $type === 'themes' ? ThemeInstaller::pack($folder) : ModuleInstaller::pack($folder);
            rename($zip, "$dir/packages/" . basename($zip));
            $src = ROOT . '/app/' . ($type === 'themes' ? 'Themes' : 'Modules') . "/$folder";
            if (realpath($src) !== realpath("$dir/$type/$folder")) {   // kaynak zaten oradaysa (bağlantı) dokunma
                if (is_dir("$dir/$type/$folder")) Module::removeDir("$dir/$type/$folder");
                self::copyDir($src, "$dir/$type/$folder");
            }
            $entry = [
                'slug' => $folder, 'name' => $m['name'], 'version' => $m['version'], 'description' => $m['description'], 'author' => $m['author'],
                'category' => $extra[$folder]['category'] ?? 'Diğer', 'requires' => ['modrix' => App::VERSION, 'php' => $m['requires']['php'] ?? '8.1'],
                'download' => $base . 'packages/' . basename($zip), 'sha256' => hash_file('sha256', "$dir/packages/" . basename($zip)),
                'price' => $extra[$folder]['price'] ?? 0, 'buy_url' => $extra[$folder]['buy_url'] ?? '',
            ];
            $image = $type === 'themes' ? Theme::screenshotFile($folder) : Module::iconFile($folder);
            if ($image) {
                $name = ($type === 'themes' ? 'screenshots' : 'icons') . "/$folder." . pathinfo($image, PATHINFO_EXTENSION);
                copy($image, "$dir/$name");
                $entry[$type === 'themes' ? 'screenshot' : 'icon'] = $base . $name;
            }
            $items[] = $entry;
        }
        file_put_contents("$dir/catalog.json", json_encode(['generated_at' => date('c'), $type => $items], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
        return "$dir/catalog.json";
    }

    public static function defaultOut(string $type): string
    {
        $repo = dirname(ROOT) . '/modrix-' . ($type === 'themes' ? 'themes' : 'modules');
        return is_dir($repo) ? $repo : ROOT . '/dist/' . ($type === 'themes' ? 'themes' : 'modules');
    }

    // ---- İç

    private static function installedVersion(string $slug, string $type): ?string
    {
        if ($type === 'themes') return Theme::exists($slug) ? Theme::manifest($slug)['version'] : null;
        $local = Module::all()[$slug] ?? null;
        if (!$local || $local['state'] === 'not_installed') return null;
        return (string) ($local['installed_version'] ?? $local['version']);
    }

    private static function incompatible(array $item): ?string
    {
        $modrix = (string) ($item['requires']['modrix'] ?? '0.0.0');
        $php = (string) ($item['requires']['php'] ?? '0');
        if (version_compare(App::VERSION, $modrix, '<')) return "Modrix v$modrix ya da yenisi gerekir (sizde v" . App::VERSION . '). Önce Güncellemeler\'den güncelleyin.';
        if (version_compare(PHP_VERSION, $php, '<')) return "PHP $php ya da yenisi gerekir (sunucuda " . PHP_VERSION . ').';
        return null;
    }

    // Katalogdaki göreli adresi katalogun konumuna göre çöz
    private static function resolve(string $path, string $type): string
    {
        if ($path === '' || preg_match('#^https://#', $path) || preg_match('#^([a-z]:)?[/\\\\]#i', $path)) return $path;   // tam adres ya da mutlak yol
        $source = self::source($type);
        return preg_match('#^https://#', $source) ? preg_replace('#[^/]*$#', '', $source) . ltrim($path, '/') : dirname(self::localPath($source)) . '/' . ltrim($path, '/');
    }

    private static function localPath(string $path): string
    {
        return preg_match('#^([a-z]:)?[/\\\\]#i', $path) ? $path : ROOT . '/' . $path;
    }

    private static function copyDir(string $from, string $to): void
    {
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($from, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($from) + 1));
            if (preg_match('#(^|/)\.#', $rel)) continue;
            if (!is_dir(dirname("$to/$rel"))) mkdir(dirname("$to/$rel"), 0775, true);
            copy($f->getPathname(), "$to/$rel");
        }
    }

    // HTTPS ya da (geliştirme için) yerel dosya. $target verilirse dosyaya yazar.
    private static function fetch(string $url, ?string $target = null, ?string $license = null): string
    {
        if (!preg_match('#^https://#', $url)) {
            $local = self::localPath($url);
            if (!is_file($local)) throw new RuntimeException('Bulunamadı: ' . $url);
            if ($target) { copy($local, $target); return $target; }
            return (string) file_get_contents($local);
        }
        $ch = curl_init($url);
        $fh = $target ? fopen($target, 'w') : null;
        curl_setopt_array($ch, [
            CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5, CURLOPT_TIMEOUT => $target ? 300 : 15, CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_HTTPHEADER => ['User-Agent: Modrix-Store', ...($license ? ["X-Modrix-License: $license"] : [])],
        ] + ($fh ? [CURLOPT_FILE => $fh] : [CURLOPT_RETURNTRANSFER => true]));
        $result = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        if ($fh) fclose($fh);
        if ($result === false) throw new RuntimeException('Bağlanılamadı: ' . $error);
        if ($status === 404) throw new RuntimeException('Mağaza kataloğu bulunamadı (henüz yayınlanmamış olabilir).');
        if ($status === 401 || $status === 403) throw new RuntimeException('Erişim reddedildi (lisans anahtarı geçersiz olabilir).');
        if ($status >= 400) throw new RuntimeException("HTTP $status");
        return $target ? $target : (string) $result;
    }
}
