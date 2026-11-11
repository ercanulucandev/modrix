<?php
namespace App\Services;

use Core\App;
use Core\Config;
use Core\Log;
use Core\Migrator;
use Core\Setting;
use RuntimeException;
use Throwable;
use ZipArchive;

// Modrix güncellemeleri. Kaynak: GitHub Releases (app/Config/app.php -> update.repository).
//
//   Updater::latest()               en son sürüm bilgisi (12 saat önbellekli)
//   Updater::available()            daha yeni sürüm varsa bilgisi, yoksa null
//   Updater::apply()                en son sürüme güncelle
//   Updater::apply('paket.zip')     yerel paketten güncelle (php modrix update --file=...)
//
// Güvenlik: önce tam yedek alınır; değişen her dosyanın eski hali saklanır; hata olursa dosyalar ve veritabanı geri alınır.
// Asla dokunulmayanlar: storage/, app/Config/*.local.php, .git. Pakette olmayan dosyalar silinmez.
final class Updater
{
    private const TTL = 43200;   // 12 saat
    private const SKIP = ['#^storage/#', '#^\.github/#', '#^app/Config/[^/]+\.local\.php$#', '#^\.git(/|$)#', '#^dist/#', '#^vendor/#'];

    public static function current(): string
    {
        return App::VERSION;
    }

    public static function repository(): string
    {
        return (string) Config::get('app.update.repository', 'ercanulucandev/modrix');
    }

    // ['version', 'name', 'notes', 'published_at', 'url', 'package', 'checksum', 'checked_at'] ya da ['error' => ...]
    public static function latest(bool $refresh = false): array
    {
        $file = ROOT . '/storage/cache/update.json';
        $cached = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        if (!$refresh && is_array($cached) && ($cached['checked_at'] ?? 0) > time() - self::TTL) return $cached;

        try {
            $release = self::github('https://api.github.com/repos/' . self::repository() . '/releases/latest');
            $version = ltrim((string) ($release['tag_name'] ?? ''), 'vV');
            if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) throw new RuntimeException('Sürüm etiketi geçersiz: ' . ($release['tag_name'] ?? '?'));
            $assets = array_column($release['assets'] ?? [], null, 'name');
            $zip = $assets["modrix-$version.zip"] ?? null;
            $info = [
                'version'      => $version,
                'name'         => (string) ($release['name'] ?: "v$version"),
                'notes'        => (string) ($release['body'] ?? ''),
                'published_at' => $release['published_at'] ?? null,
                'url'          => $release['html_url'] ?? null,
                // Paket: sürüm ZIP'i (php modrix release:pack) yoksa GitHub'ın kaynak ZIP'i
                'package'      => $zip['url'] ?? ($release['zipball_url'] ?? null),
                'checksum'     => $assets["modrix-$version.zip.sha256"]['url'] ?? null,
                'checked_at'   => time(),
            ];
        } catch (Throwable $e) {
            $info = ['error' => $e->getMessage(), 'checked_at' => time()] + (is_array($cached) && empty($cached['error']) ? $cached : []);
        }
        if (!is_dir(dirname($file))) mkdir(dirname($file), 0775, true);
        file_put_contents($file, json_encode($info, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $info;
    }

    public static function available(bool $refresh = false): ?array
    {
        $latest = self::latest($refresh);
        return !empty($latest['version']) && version_compare($latest['version'], self::current(), '>') ? $latest : null;
    }

    // Güncellemeyi uygular. $log: adım adım ilerleme (CLI ve panel). Dönüş: ['from', 'to', 'backup', 'changed', 'added', 'migrations']
    public static function apply(?string $packageFile = null, bool $force = false, ?callable $log = null): array
    {
        $log ??= fn(string $m) => null;
        @set_time_limit(0);
        $work = ROOT . '/storage/tmp/update-' . date('YmdHis');
        $lock = fopen(ROOT . '/storage/tmp/update.lock', 'c');
        if (!flock($lock, LOCK_EX | LOCK_NB)) throw new RuntimeException('Şu anda başka bir güncelleme çalışıyor.');
        if (!class_exists(ZipArchive::class)) throw new RuntimeException('Sunucuda PHP zip eklentisi kapalı.');
        mkdir("$work/rollback", 0775, true);

        $maintenance = Setting::get('maintenance_mode');
        $changed = $added = [];
        $from = self::current();
        try {
            // 1) Paket
            if ($packageFile === null) {
                $latest = self::latest(true);
                if (!empty($latest['error'])) throw new RuntimeException('Sürüm bilgisi alınamadı: ' . $latest['error']);
                if (!$force && !self::available()) throw new RuntimeException('Zaten en son sürüm kullanılıyor (' . $from . ').');
                $log("İndiriliyor: v{$latest['version']}");
                $packageFile = "$work/package.zip";
                self::download($latest['package'], $packageFile);
                if ($latest['checksum']) {
                    $expected = strtolower(substr(trim(self::download($latest['checksum'])), 0, 64));
                    if (!hash_equals($expected, hash_file('sha256', $packageFile))) throw new RuntimeException('Paketin doğrulama özeti tutmuyor; indirme bozuk ya da değiştirilmiş.');
                    $log('Paket doğrulandı (SHA-256).');
                }
            }
            $root = self::extract($packageFile, "$work/package");
            $to = self::packageVersion($root);
            if (!$force && version_compare($to, $from, '<=')) throw new RuntimeException("Paketteki sürüm ($to) mevcut sürümden ($from) yeni değil.");
            $log("Paket: v$to (mevcut v$from)");

            // 2) Yedek (zorunlu)
            $log('Tam yedek alınıyor…');
            $backup = Backup::create(true);
            $log("Yedek: storage/backups/$backup");

            // 3) Bakım modu + dosyalar
            Setting::set(['maintenance_mode' => '1']);
            $log('Dosyalar güncelleniyor…');
            foreach (self::files($root) as $rel) {
                $src = "$root/$rel";
                $dest = ROOT . "/$rel";
                if (is_file($dest)) {
                    if (hash_file('sha256', $src) === hash_file('sha256', $dest)) continue;
                    @mkdir(dirname("$work/rollback/$rel"), 0775, true);
                    if (!copy($dest, "$work/rollback/$rel")) throw new RuntimeException("Yedeklenemedi: $rel");
                    $changed[] = $rel;
                } else {
                    $added[] = $rel;
                }
                if (!is_dir(dirname($dest)) && !mkdir(dirname($dest), 0775, true)) throw new RuntimeException("Klasör oluşturulamadı: " . dirname($rel));
                if (!copy($src, "$dest.updating") || !rename("$dest.updating", $dest)) {
                    @unlink("$dest.updating");
                    throw new RuntimeException("Yazılamadı: $rel (dosya izinlerini kontrol edin)");
                }
            }
            $log(count($changed) . ' dosya değişti, ' . count($added) . ' dosya eklendi.');
            if (function_exists('opcache_reset')) @opcache_reset();

            // 4) Veritabanı
            $tablesBefore = self::tables();
            try {
                $migrations = Migrator::migrate(fn($name) => $log("Migration: $name"));
            } catch (Throwable $e) {
                $log('Migration hatası: ' . $e->getMessage() . ' — veritabanı yedekten geri yükleniyor…');
                self::rollbackFiles($work, $changed, $added);
                $changed = $added = [];
                Backup::restore(ROOT . "/storage/backups/$backup", false);
                // Yedekte olmayan, yarım kalan migration'ın açtığı tablolar
                foreach (array_diff(self::tables(), $tablesBefore) as $table) \Core\Database::query("DROP TABLE IF EXISTS `$table`");
                throw new RuntimeException('Veritabanı güncellenemedi, her şey eski haline döndürüldü: ' . $e->getMessage());
            }

            PageCache::flush();
            @unlink(ROOT . '/storage/cache/update.json');
            Activity::log('system.update', "Modrix'i güncelledi: v$from → v$to", 'admin/updates');
            Log::info('Modrix güncellendi', ['from' => $from, 'to' => $to, 'changed' => count($changed), 'added' => count($added)]);
            $log("Tamamlandı: v$to");
            return ['from' => $from, 'to' => $to, 'backup' => $backup, 'changed' => $changed, 'added' => $added, 'migrations' => $migrations];
        } catch (Throwable $e) {
            if ($changed || $added) {
                $log('Hata: ' . $e->getMessage() . ' — dosyalar geri alınıyor…');
                self::rollbackFiles($work, $changed, $added);
            }
            Log::error('Güncelleme başarısız', ['error' => $e->getMessage()]);
            throw $e;
        } finally {
            Setting::set(['maintenance_mode' => $maintenance]);
            self::remove($work);
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    // Sürüm paketi: git'teki dosyalar (yerel ayarlar, storage içeriği hariç) -> dist/modrix-{sürüm}.zip ve .sha256
    public static function pack(): string
    {
        exec('git -C ' . escapeshellarg(ROOT) . ' ls-files -z', $out, $code);
        if ($code !== 0) throw new RuntimeException('git bulunamadı: paket git deposundaki dosyalardan oluşturulur.');
        $list = array_filter(explode("\0", implode("\n", $out)));
        $dir = ROOT . '/dist';
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        $file = "$dir/modrix-" . self::current() . '.zip';
        @unlink($file);
        $zip = new ZipArchive();
        $zip->open($file, ZipArchive::CREATE);
        foreach ($list as $rel) {
            $rel = str_replace('\\', '/', trim($rel));
            // Pakete girmez: yerel ayarlar, .git, .github, dist, vendor. storage/ iskeleti (.gitkeep, .htaccess) girer.
            if ($rel === '' || !is_file(ROOT . "/$rel") || preg_match('#^(app/Config/[^/]+\.local\.php|\.git(hub)?/|dist/|vendor/)#', $rel)) continue;
            if (str_starts_with($rel, 'storage/') && !preg_match('#(^|/)\.(gitkeep|htaccess)$#', $rel)) continue;
            $zip->addFile(ROOT . "/$rel", "modrix/$rel");
        }
        $zip->close();
        file_put_contents("$file.sha256", hash_file('sha256', $file) . '  ' . basename($file) . "\n");
        return $file;
    }

    // GitHub sürüm notları (Markdown) -> basit HTML
    public static function notes(string $markdown): string
    {
        $html = '';
        $list = false;
        foreach (preg_split('/\R/', htmlspecialchars($markdown, ENT_QUOTES, 'UTF-8')) as $line) {
            $line = preg_replace(['/\*\*(.+?)\*\*/', '/`(.+?)`/'], ['<strong>$1</strong>', '<code>$1</code>'], $line);
            if (preg_match('/^\s*[-*]\s+(.*)$/', $line, $m)) {
                if (!$list) { $html .= '<ul class="list-disc space-y-1 pl-5">'; $list = true; }
                $html .= "<li>{$m[1]}</li>";
                continue;
            }
            if ($list) { $html .= '</ul>'; $list = false; }
            if (preg_match('/^#{1,6}\s+(.*)$/', $line, $m)) $html .= '<p class="mt-3 font-semibold">' . $m[1] . '</p>';
            elseif (trim($line) !== '') $html .= '<p>' . $line . '</p>';
        }
        return $html . ($list ? '</ul>' : '');
    }

    // ---- İç

    private static function github(string $url): array
    {
        $body = self::request($url, ['Accept: application/vnd.github+json']);
        $data = json_decode($body, true);
        if (!is_array($data)) throw new RuntimeException('GitHub yanıtı okunamadı.');
        return $data;
    }

    // $target verilirse dosyaya yazar; verilmezse içeriği döndürür
    private static function download(string $url, ?string $target = null): string
    {
        $headers = str_contains($url, 'api.github.com') && str_contains($url, '/assets/') ? ['Accept: application/octet-stream'] : [];
        return self::request($url, $headers, $target);
    }

    private static function request(string $url, array $headers = [], ?string $target = null): string
    {
        if (!str_starts_with($url, 'https://')) throw new RuntimeException('Sadece HTTPS adresinden güncelleme alınır.');
        $token = Crypt::decrypt((string) Setting::get('update_token'));
        $ch = curl_init($url);
        $fh = $target ? fopen($target, 'w') : null;
        // (Tamsayı anahtarlı dizilerde ... anahtarları yeniden numaralar; seçenekler + ile birleştirilir)
        curl_setopt_array($ch, [
            CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5, CURLOPT_TIMEOUT => $target ? 300 : 15, CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_HTTPHEADER => [...$headers, 'User-Agent: Modrix-Updater', ...($token ? ["Authorization: Bearer $token"] : [])],
        ] + ($fh ? [CURLOPT_FILE => $fh] : [CURLOPT_RETURNTRANSFER => true]));
        $result = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        if ($fh) fclose($fh);
        if ($result === false) throw new RuntimeException('Bağlanılamadı: ' . $error);
        if ($status === 404) throw new RuntimeException('Bulunamadı (henüz yayınlanmış sürüm yok ya da depo gizli: Güncellemeler sayfasından erişim anahtarı girin).');
        if ($status === 401 || $status === 403) throw new RuntimeException('GitHub erişimi reddedildi (erişim anahtarı geçersiz ya da istek sınırı doldu).');
        if ($status >= 400) throw new RuntimeException("GitHub HTTP $status");
        return $target ? $target : (string) $result;
    }

    // ZIP'i güvenli açar ('../' ile dışarı yazılamaz); tek üst klasör varsa onu kök sayar
    private static function extract(string $zipFile, string $dir): string
    {
        $zip = new ZipArchive();
        if ($zip->open($zipFile) !== true) throw new RuntimeException('Paket açılamadı (ZIP değil ya da bozuk).');
        mkdir($dir, 0775, true);
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = str_replace('\\', '/', (string) $zip->getNameIndex($i));
            if ($name === '' || str_contains($name, '..') || str_starts_with($name, '/') || preg_match('#^[a-z]:#i', $name)) throw new RuntimeException("Pakette güvensiz yol: $name");
            if (str_ends_with($name, '/')) continue;
            @mkdir(dirname("$dir/$name"), 0775, true);
            if (!copy("zip://$zipFile#$name", "$dir/$name")) throw new RuntimeException("Paketten çıkarılamadı: $name");
        }
        $zip->close();
        $entries = array_values(array_diff(scandir($dir), ['.', '..']));
        return count($entries) === 1 && is_dir("$dir/{$entries[0]}") ? "$dir/{$entries[0]}" : $dir;
    }

    private static function packageVersion(string $root): string
    {
        if (!is_file("$root/core/App.php") || !is_file("$root/modrix")) throw new RuntimeException('Bu bir Modrix paketi değil (core/App.php bulunamadı).');
        if (!preg_match("/const VERSION = '(\d+\.\d+\.\d+)'/", (string) file_get_contents("$root/core/App.php"), $m)) throw new RuntimeException('Paketin sürümü okunamadı.');
        return $m[1];
    }

    private static function files(string $root): array
    {
        $out = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if (!$f->isFile()) continue;
            $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($root) + 1));
            if (!self::skipped($rel)) $out[] = $rel;
        }
        sort($out);
        return $out;
    }

    private static function skipped(string $rel): bool
    {
        foreach (self::SKIP as $pattern) if (preg_match($pattern, $rel)) return true;
        return false;
    }

    private static function tables(): array
    {
        return array_column(\Core\Database::pdo()->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(\PDO::FETCH_NUM), 0);
    }

    private static function rollbackFiles(string $work, array $changed, array $added): void
    {
        foreach ($changed as $rel) @copy("$work/rollback/$rel", ROOT . "/$rel");
        foreach ($added as $rel) @unlink(ROOT . "/$rel");
        if (function_exists('opcache_reset')) @opcache_reset();
    }

    private static function remove(string $dir): void
    {
        if (!is_dir($dir)) return;
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        @rmdir($dir);
    }
}
