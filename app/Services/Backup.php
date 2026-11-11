<?php
namespace App\Services;

use Core\Database;
use Core\Setting;
use PDO;
use RuntimeException;
use ZipArchive;

// Yedekler: veritabanı (SQL) + yüklenen dosyalar + şifreleme anahtarı tek ZIP'te, storage/backups altında.
//
//   Backup::create()              tam yedek  -> dosya adı
//   Backup::create(media: false)  sadece veritabanı
//   Backup::restore($zipYolu)     geri yükler (sadece komut satırından: php modrix backup:restore)
//
// ZIP içeriği: database.sql, uploads/ (storage/uploads), config/key.local.php, backup.json
final class Backup
{
    private const PATTERN = '/^yedek-\d{4}-\d{2}-\d{2}-\d{6}(-db)?\.zip$/';

    public static function dir(): string
    {
        $dir = ROOT . '/storage/backups';
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        // Apache dışında da (nginx vb.) web'den açılmasın diye storage/ zaten kapalı; yine de
        if (!is_file("$dir/.htaccess")) file_put_contents("$dir/.htaccess", "Require all denied\n");
        return $dir;
    }

    public static function all(): array
    {
        $list = [];
        foreach (glob(self::dir() . '/yedek-*.zip') ?: [] as $file) {
            $name = basename($file);
            if (!preg_match(self::PATTERN, $name)) continue;
            $list[] = ['name' => $name, 'size' => filesize($file), 'time' => filemtime($file), 'media' => !str_ends_with($name, '-db.zip')];
        }
        usort($list, fn($a, $b) => $b['time'] <=> $a['time']);
        return $list;
    }

    // Geçerli bir yedek adıysa tam yolu (indirme/silme için; '../' vb. kabul edilmez)
    public static function path(string $name): ?string
    {
        return preg_match(self::PATTERN, $name) && is_file($file = self::dir() . "/$name") ? $file : null;
    }

    public static function create(bool $media = true): string
    {
        if (!class_exists(ZipArchive::class)) throw new RuntimeException('Sunucuda PHP zip eklentisi kapalı.');
        @set_time_limit(0);
        $dir = self::dir();

        $lock = fopen("$dir/.lock", 'c');
        if (!flock($lock, LOCK_EX | LOCK_NB)) throw new RuntimeException('Şu anda başka bir yedek alınıyor.');

        // Aynı saniyede iki yedek olursa bir sonraki saniyenin adı
        for ($t = time(); is_file("$dir/" . ($name = 'yedek-' . date('Y-m-d-His', $t) . ($media ? '' : '-db') . '.zip')); $t++);
        $sql = tempnam(sys_get_temp_dir(), 'sql');
        try {
            $tables = self::dump($sql);

            $zip = new ZipArchive();
            if ($zip->open("$dir/$name", ZipArchive::CREATE | ZipArchive::EXCL) !== true) throw new RuntimeException('Yedek dosyası oluşturulamadı.');
            $zip->addFile($sql, 'database.sql');
            $files = $media ? self::addUploads($zip) : 0;
            if (is_file($key = ROOT . '/app/Config/key.local.php')) $zip->addFile($key, 'config/key.local.php');
            $zip->addFromString('backup.json', json_encode([
                'created_at' => date('c'),
                'site'       => Setting::get('site_name'),
                'tables'     => $tables,
                'files'      => $files,
                'media'      => $media,
                'php'        => PHP_VERSION,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            if (!$zip->close()) throw new RuntimeException('Yedek dosyası yazılamadı (disk dolu olabilir).');
        } catch (\Throwable $e) {
            @unlink("$dir/$name");
            throw $e;
        } finally {
            @unlink($sql);
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        self::prune();
        return $name;
    }

    public static function delete(string $name): bool
    {
        $file = self::path($name);
        return $file && unlink($file);
    }

    // Ayarlar > Güvenlik > "Saklanacak yedek sayısı"ndan eskileri siler
    public static function prune(): void
    {
        $keep = max(1, (int) Setting::get('backup_keep', 10));
        foreach (array_slice(self::all(), $keep) as $old) @unlink(self::dir() . '/' . $old['name']);
    }

    // Yedeği geri yükler: veritabanını değiştirir, dosyaları üzerine yazar. Önce mevcut halin yedeği alınır (geri dönebilmek için).
    // Dönüş: ['statements' => n, 'files' => n, 'safety' => önceki halin yedeği]
    public static function restore(string $zipFile, bool $safety = true): array
    {
        @set_time_limit(0);
        $zip = new ZipArchive();
        if ($zip->open($zipFile) !== true || $zip->locateName('database.sql') === false) throw new RuntimeException('Geçerli bir Modrix yedeği değil.');
        $before = $safety ? self::create() : null;

        $tmp = sys_get_temp_dir() . '/modrix-restore-' . bin2hex(random_bytes(4));
        mkdir($tmp);
        try {
            // Dosyaları tek tek ve yolu denetleyerek çıkar ('../' ile dışarı yazılamasın)
            $files = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->getNameIndex($i);
                if (str_ends_with($entry, '/') || str_contains($entry, '..') || str_starts_with($entry, '/') || str_contains($entry, '\\')) continue;
                if (str_starts_with($entry, 'uploads/')) {
                    $target = ROOT . '/storage/' . $entry;
                    $files++;
                } elseif ($entry === 'config/key.local.php') {
                    $target = ROOT . '/app/Config/key.local.php';
                } elseif ($entry === 'database.sql') {
                    $target = "$tmp/database.sql";
                } else {
                    continue;
                }
                if (!is_dir(dirname($target))) mkdir(dirname($target), 0775, true);
                copy("zip://$zipFile#$entry", $target);
            }
            $zip->close();
            return ['statements' => self::import("$tmp/database.sql"), 'files' => $files, 'safety' => $before];
        } finally {
            @unlink("$tmp/database.sql");
            @rmdir($tmp);
        }
    }

    // ---- İç

    // Tüm tabloları SQL dosyasına yazar (satırlar akarak okunur; büyük tablolarda bellek şişmez)
    private static function dump(string $file): int
    {
        $pdo = Database::pdo();
        $out = fopen($file, 'w');
        fwrite($out, "-- Modrix yedeği " . date('Y-m-d H:i:s') . "\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");

        $tables = array_column($pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM), 0);
        $buffered = class_exists(\Pdo\Mysql::class) ? \Pdo\Mysql::ATTR_USE_BUFFERED_QUERY : PDO::MYSQL_ATTR_USE_BUFFERED_QUERY;

        foreach ($tables as $table) {
            $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM)[1];
            fwrite($out, "DROP TABLE IF EXISTS `$table`;\n$create;\n");

            $pdo->setAttribute($buffered, false);
            try {
                $stmt = $pdo->query("SELECT * FROM `$table`");
                $batch = [];
                $columns = null;
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $columns ??= '`' . implode('`, `', array_keys($row)) . '`';
                    $batch[] = '(' . implode(', ', array_map(fn($v) => match (true) {
                        $v === null             => 'NULL',
                        is_int($v), is_float($v) => (string) $v,
                        default                  => $pdo->quote((string) $v),
                    }, $row)) . ')';
                    if (count($batch) === 200) {
                        fwrite($out, "INSERT INTO `$table` ($columns) VALUES\n" . implode(",\n", $batch) . ";\n");
                        $batch = [];
                    }
                }
                if ($batch) fwrite($out, "INSERT INTO `$table` ($columns) VALUES\n" . implode(",\n", $batch) . ";\n");
                $stmt->closeCursor();
            } finally {
                $pdo->setAttribute($buffered, true);
            }
            fwrite($out, "\n");
        }
        fwrite($out, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($out);
        return count($tables);
    }

    // SQL dosyasını komut komut çalıştırır (satır sonu ';' ile biten her blok bir komut; değerlerdeki satır sonları kaçışlı)
    private static function import(string $file): int
    {
        $pdo = Database::pdo();
        $in = fopen($file, 'r');
        $statement = '';
        $count = 0;
        while (($line = fgets($in)) !== false) {
            if ($statement === '' && (str_starts_with($line, '--') || trim($line) === '')) continue;
            $statement .= $line;
            if (str_ends_with(rtrim($line), ';')) {
                $pdo->exec($statement);
                $statement = '';
                $count++;
            }
        }
        fclose($in);
        return $count;
    }

    private static function addUploads(ZipArchive $zip): int
    {
        $base = ROOT . '/storage/uploads';
        if (!is_dir($base)) return 0;
        $count = 0;
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($base) + 1));
            if (!$file->isFile() || str_starts_with($relative, 'tmp/')) continue;
            $zip->addFile($file->getPathname(), "uploads/$relative");
            $zip->setCompressionName("uploads/$relative", ZipArchive::CM_STORE); // görseller zaten sıkışık; hızlı
            $count++;
        }
        return $count;
    }
}
