<?php
namespace Core;

use RuntimeException;
use ZipArchive;

// Temayı ZIP'ten kurar ya da günceller (Admin > Sistem > Mağaza > Temalar, ya da ZIP yükleme).
//
// ZIP yapısı: tek kök klasör (tema adı) ve içinde theme.json
//   Kurumsal/theme.json, Kurumsal/views/layouts/site.php, Kurumsal/assets/...
//
// Güvenlik: ModuleInstaller ile aynı (zip slip, gizli dosyalar atlanır, izinli türler, boyut sınırı, önce geçici klasör).
// Var olan temanın üzerine sadece daha yeni sürüm yazılır; hata olursa eski sürüm geri gelir. Kurulan tema etkinleşmez.
class ThemeInstaller
{
    private const EXTENSIONS = ['php', 'json', 'css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'ico', 'woff', 'woff2', 'ttf', 'otf', 'txt', 'md'];
    private const MAX_FILES  = 3000;
    private const MAX_BYTES  = 80 * 1024 * 1024;

    // Dağıtım için: dist/{Tema}-{sürüm}.zip
    public static function pack(string $folder): string
    {
        $manifest = Theme::manifest($folder);
        $source = ROOT . "/app/Themes/$folder";
        $dir = ROOT . '/dist';
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        $target = "$dir/$folder-{$manifest['version']}.zip";
        if (is_file($target)) unlink($target);

        $zip = new ZipArchive();
        if ($zip->open($target, ZipArchive::CREATE) !== true) throw new RuntimeException("ZIP oluşturulamadı: $target");
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            $relative = $folder . '/' . str_replace('\\', '/', substr($file->getPathname(), strlen($source) + 1));
            if (preg_match('#(^|/)\.#', $relative) || !in_array(strtolower($file->getExtension()), self::EXTENSIONS, true)) continue;
            $zip->addFile($file->getPathname(), $relative);
        }
        $zip->close();
        return $target;
    }

    // Dönüş: ['folder' => 'Kurumsal', 'action' => 'installed' | 'updated', 'version' => '1.1.0']
    public static function fromZip(string $zipPath): array
    {
        if (!class_exists(ZipArchive::class)) throw new RuntimeException('Sunucuda PHP zip eklentisi kapalı');
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) throw new RuntimeException('ZIP dosyası açılamadı');

        $staging = ROOT . '/storage/tmp/themes/' . bin2hex(random_bytes(8));
        $open = true;
        try {
            [$folder, $files] = self::inspect($zip);
            $manifest = json_decode((string) $zip->getFromName("$folder/theme.json"), true);
            if (!is_array($manifest) || empty($manifest['name']) || !preg_match('/^\d+\.\d+\.\d+$/', (string) ($manifest['version'] ?? ''))) {
                throw new RuntimeException('theme.json geçersiz: "name" ve "version" (1.0.0 biçiminde) zorunlu');
            }
            $action = self::decide($folder, $manifest['version']);
            foreach ($files as $name) {
                $target = "$staging/$name";
                if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0775, true)) throw new RuntimeException('Geçici klasör oluşturulamadı');
                $in = $zip->getStream($name);
                if (!$in) throw new RuntimeException("Okunamadı: $name");
                $out = fopen($target, 'wb');
                stream_copy_to_stream($in, $out);
                fclose($in);
                fclose($out);
            }
            $zip->close();
            $open = false;

            $target = ROOT . "/app/Themes/$folder";
            if ($action === 'installed') {
                if (!@rename("$staging/$folder", $target)) throw new RuntimeException('Tema klasörü taşınamadı (app/Themes yazılabilir mi?)');
            } else {
                $backup = ROOT . "/storage/tmp/themes/backup-$folder-" . date('YmdHis');
                if (!@rename($target, $backup)) throw new RuntimeException('Mevcut tema yedeklenemedi');
                if (!@rename("$staging/$folder", $target)) {
                    @rename($backup, $target);
                    throw new RuntimeException('Yeni sürüm yerine konamadı; eski sürüm korundu');
                }
                Module::removeDir($backup);
            }
            return ['folder' => $folder, 'action' => $action, 'version' => $manifest['version']];
        } finally {
            if ($open) $zip->close();
            if (is_dir($staging)) Module::removeDir($staging);
        }
    }

    private static function inspect(ZipArchive $zip): array
    {
        if ($zip->numFiles === 0) throw new RuntimeException('ZIP boş');
        if ($zip->numFiles > self::MAX_FILES) throw new RuntimeException('ZIP içinde çok fazla dosya var');
        $root = null;
        $files = [];
        $total = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $name = str_replace('\\', '/', (string) $stat['name']);
            if ($name === '' || str_contains($name, "\0") || $name[0] === '/' || preg_match('#^[A-Za-z]:#', $name)) throw new RuntimeException("Geçersiz yol: $name");
            $parts = array_values(array_filter(explode('/', $name), 'strlen'));
            foreach ($parts as $p) if ($p === '..' || $p === '.') throw new RuntimeException("Geçersiz yol: $name");
            foreach ($parts as $p) if ($p[0] === '.') continue 2;   // gizli dosyalar hiç çıkarılmaz
            $root ??= $parts[0];
            if ($parts[0] !== $root) throw new RuntimeException('ZIP içinde tek bir tema klasörü olmalı');
            if (str_ends_with($name, '/')) continue;
            if (!in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), self::EXTENSIONS, true)) throw new RuntimeException("İzin verilmeyen dosya türü: $name");
            $total += (int) $stat['size'];
            if ($total > self::MAX_BYTES) throw new RuntimeException('ZIP açılınca çok büyük');
            $files[] = $name;
        }
        if (!Theme::validName((string) $root)) throw new RuntimeException("Geçersiz tema adı: $root");
        if (!in_array("$root/theme.json", $files, true)) throw new RuntimeException("$root/theme.json bulunamadı");
        return [$root, $files];
    }

    private static function decide(string $folder, string $version): string
    {
        if (!is_dir(ROOT . "/app/Themes/$folder")) return 'installed';
        $current = Theme::exists($folder) ? Theme::manifest($folder)['version'] : '0.0.0';
        if (!version_compare($version, $current, '>')) throw new RuntimeException("$folder $current kurulu; yüklenen sürüm ($version) daha yeni değil.");
        return 'updated';
    }
}
