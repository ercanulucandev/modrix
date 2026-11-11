<?php
namespace Core;

use RuntimeException;
use Throwable;
use ZipArchive;

// Modülü ZIP'ten kurar ya da günceller.
//
// ZIP yapısı: tek bir kök klasör (modül adı) ve içinde module.json
//   Blog/module.json
//   Blog/routes.php
//   Blog/Controllers/...
//
// Güvenlik kontrolleri:
//   - ../ ve mutlak yollar (zip slip) reddedilir; gizli dosyalar (.htaccess vb.) atlanır, hiç yazılmaz
//   - sadece izinli dosya türleri (php, sql, json, css, js, resim, font, txt, md)
//   - dosya sayısı ve toplam boyut sınırı (zip bombası)
//   - module.json geçerli olmalı, klasör adı kurallara uymalı
//   - var olan modülün üzerine sadece daha yeni sürümle (güncelleme) yazılır
//   - önce geçici klasöre açılır; güncellemede hata olursa eski sürüm geri yüklenir
//
// NOT: Yüklenen PHP kodu sunucuda çalışır. Sadece güvendiğin (kendi imzaladığın) modülleri yükle.
class ModuleInstaller
{
    private const EXTENSIONS = ['php', 'sql', 'json', 'css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'ico', 'woff', 'woff2', 'ttf', 'txt', 'md'];
    private const MAX_FILES  = 2000;
    private const MAX_BYTES  = 50 * 1024 * 1024;

    // Modülü satış/dağıtım için ZIP'ler: dist/Blog-1.0.0.zip
    // Yükleyicinin kabul etmeyeceği dosyalar (gizli dosyalar, izinsiz türler) baştan dışarıda bırakılır.
    public static function pack(string $folder): string
    {
        $manifest = Module::manifest($folder);
        $source   = ROOT . "/app/Modules/$folder";
        $distDir  = ROOT . '/dist';
        if (!is_dir($distDir)) mkdir($distDir, 0775, true);

        $target = "$distDir/$folder-{$manifest['version']}.zip";
        if (is_file($target)) unlink($target);

        $zip = new ZipArchive();
        if ($zip->open($target, ZipArchive::CREATE) !== true) throw new RuntimeException("ZIP oluşturulamadı: $target");

        $skipped = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            $relative = $folder . '/' . str_replace('\\', '/', substr($file->getPathname(), strlen($source) + 1));
            $hidden   = (bool) preg_match('#(^|/)\.#', $relative);
            $allowed  = in_array(strtolower($file->getExtension()), self::EXTENSIONS, true);
            if ($hidden || !$allowed) {
                if (!$hidden) $skipped[] = $relative;
                continue;
            }
            $zip->addFile($file->getPathname(), $relative);
        }
        $zip->close();

        if ($skipped) Log::warning("module:pack dışarıda bıraktı ($folder)", ['files' => $skipped]);
        return $target;
    }

    // Dönüş: ['folder' => 'Blog', 'action' => 'installed' | 'updated', 'version' => '1.1.0']
    public static function fromZip(string $zipPath): array
    {
        if (!class_exists(ZipArchive::class)) throw new RuntimeException('Sunucuda PHP zip eklentisi kapalı');

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) throw new RuntimeException('ZIP dosyası açılamadı');

        $staging = ROOT . '/storage/tmp/modules/' . bin2hex(random_bytes(8));
        $open = true;
        try {
            [$folder, $files] = self::inspect($zip);
            $manifest = Module::validateManifest(json_decode((string) $zip->getFromName("$folder/module.json"), true), $folder);
            $action = self::decide($folder, $manifest['version']);

            self::extract($zip, $files, $staging);
            $zip->close();
            $open = false;

            match ($action) {
                'updated'     => self::swapUpdate($folder, "$staging/$folder"),
                'reinstalled' => self::replaceInstall($folder, "$staging/$folder"),
                default       => self::moveInstall($folder, "$staging/$folder"),
            };

            return ['folder' => $folder, 'action' => $action === 'reinstalled' ? 'installed' : $action, 'version' => $manifest['version']];
        } finally {
            if ($open) $zip->close();
            if (is_dir($staging)) Module::removeDir($staging);
        }
    }

    // Tüm girdileri kontrol eder; [kök klasör, [zip içindeki dosya adları]] döner
    private static function inspect(ZipArchive $zip): array
    {
        if ($zip->numFiles === 0) throw new RuntimeException('ZIP boş');
        if ($zip->numFiles > self::MAX_FILES) throw new RuntimeException('ZIP içinde çok fazla dosya var');

        $root  = null;
        $files = [];
        $total = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $name = str_replace('\\', '/', (string) $stat['name']);

            if ($name === '' || str_contains($name, "\0") || $name[0] === '/' || preg_match('#^[A-Za-z]:#', $name)) {
                throw new RuntimeException("Geçersiz yol: $name");
            }

            $parts = array_values(array_filter(explode('/', $name), 'strlen'));
            foreach ($parts as $p) {
                if ($p === '..' || $p === '.') throw new RuntimeException("Geçersiz yol: $name");
            }
            // Gizli dosya/klasörler (.htaccess, .user.ini, .git, .gitkeep...) hiçbir zaman çıkarılmaz
            foreach ($parts as $p) if ($p[0] === '.') continue 2;

            $root ??= $parts[0];
            if ($parts[0] !== $root) throw new RuntimeException('ZIP içinde tek bir modül klasörü olmalı');

            if (str_ends_with($name, '/')) continue; // klasör girdisi

            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, self::EXTENSIONS, true)) throw new RuntimeException("İzin verilmeyen dosya türü: $name");

            $total += (int) $stat['size'];
            if ($total > self::MAX_BYTES) throw new RuntimeException('ZIP açılınca çok büyük (en fazla 50 MB)');

            $files[] = $name;
        }

        Module::assertFolder((string) $root);
        if (!in_array("$root/module.json", $files, true)) throw new RuntimeException("$root/module.json bulunamadı");

        return [$root, $files];
    }

    // Yeni kurulum mu, güncelleme mi? Uygun değilse hata.
    private static function decide(string $folder, string $version): string
    {
        $existing = Module::all()[$folder] ?? null;
        if (!$existing) return 'installed';

        // Klasörü duran ama kurulu olmayan modül (ör. kaldırılmış): paketteki sürümle yeniden kurulur
        if ($existing['state'] === 'not_installed') return 'reinstalled';
        if (!version_compare($version, (string) $existing['installed_version'], '>')) {
            throw new RuntimeException("$folder {$existing['installed_version']} kurulu; yüklenen sürüm ($version) daha yeni değil.");
        }
        return 'updated';
    }

    private static function extract(ZipArchive $zip, array $files, string $staging): void
    {
        foreach ($files as $name) {
            $target = $staging . '/' . $name;
            if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0775, true)) throw new RuntimeException('Geçici klasör oluşturulamadı');

            $in = $zip->getStream($name);
            if (!$in) throw new RuntimeException("Okunamadı: $name");
            $out = fopen($target, 'wb');
            stream_copy_to_stream($in, $out);
            fclose($in);
            fclose($out);
        }
    }

    private static function moveInstall(string $folder, string $source): void
    {
        $target = ROOT . "/app/Modules/$folder";
        if (!@rename($source, $target)) throw new RuntimeException('Modül klasörü taşınamadı (app/Modules yazılabilir mi?)');

        try {
            Module::install($folder);
        } catch (Throwable $e) {
            Module::removeDir($target); // yarım kurulum bırakma
            throw $e;
        }
    }

    // Kurulu olmayan modülün eski klasörü kenara alınır, paketteki kurulur; hata olursa eski klasör geri gelir
    private static function replaceInstall(string $folder, string $source): void
    {
        $target = ROOT . "/app/Modules/$folder";
        $backup = ROOT . '/storage/tmp/modules/old-' . $folder . '-' . date('YmdHis');
        if (!@rename($target, $backup)) throw new RuntimeException('Eski modül klasörü taşınamadı (app/Modules yazılabilir mi?)');
        try {
            self::moveInstall($folder, $source);
        } catch (Throwable $e) {
            if (is_dir($target)) Module::removeDir($target);
            @rename($backup, $target);
            throw $e;
        }
        Module::removeDir($backup);
    }

    // Eski klasörü yedekle, yenisini koy, migration'ları çalıştır; hata olursa eskisini geri getir
    private static function swapUpdate(string $folder, string $source): void
    {
        $target = ROOT . "/app/Modules/$folder";
        $backup = ROOT . '/storage/tmp/modules/backup-' . $folder . '-' . date('YmdHis');

        if (!@rename($target, $backup)) throw new RuntimeException('Mevcut modül yedeklenemedi');
        if (!@rename($source, $target)) {
            @rename($backup, $target);
            throw new RuntimeException('Yeni sürüm yerine konamadı; eski sürüm korundu');
        }

        try {
            Module::update($folder);
        } catch (Throwable $e) {
            Module::removeDir($target);
            @rename($backup, $target);
            throw $e;
        }
        Module::removeDir($backup);
    }
}
