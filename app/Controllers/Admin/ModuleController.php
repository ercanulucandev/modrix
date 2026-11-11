<?php
namespace App\Controllers\Admin;

use App\Services\SettingForm;
use Core\Module;
use Core\Setting;
use Core\ModuleInstaller;
use Core\View;
use Throwable;

// Admin > Modüller: listele, ZIP ile yükle, kur, güncelle, aktif/pasif, kaldır, dosyaları sil, modül ayarları
class ModuleController extends AdminController
{
    public const MAX_ZIP = 20 * 1024 * 1024;

    public function index(): View
    {
        return $this->view('admin/modules/index', [
            'title'   => 'Modüller',
            'modules' => Module::all(),
            'zip'     => class_exists(\ZipArchive::class),
            'maxZip'  => min(self::MAX_ZIP, self::iniBytes('upload_max_filesize'), self::iniBytes('post_max_size')),
        ]);
    }

    // ZIP yükle -> kur ya da güncelle. Sayfa AJAX ile gönderir (ilerleme çubuğu, app/Assets/admin/modules-page.js):
    // JSON döner; JS kapalıysa normal form gibi yönlendirir.
    public function upload(): string
    {
        $file = Upload::save('module', 'tmp', ['types' => ['zip'], 'max' => self::MAX_ZIP]);
        if (!$file) return $this->uploadResult(false, Upload::errors() ? implode(' ', Upload::errors()) : 'ZIP dosyası seçin.');

        try {
            $result = ModuleInstaller::fromZip(Upload::path($file['path']));
            $error = null;
        } catch (Throwable $e) {
            $error = $e->getMessage();
        } finally {
            Upload::delete($file['path']);
        }

        if ($error !== null) {
            Log::warning('Modül yükleme reddedildi', ['file' => $file['name'], 'error' => $error]);
            return $this->uploadResult(false, 'Yüklenemedi: ' . $error);
        }

        return $this->uploadResult(true, $result['action'] === 'updated'
            ? "{$result['folder']} {$result['version']} sürümüne güncellendi."
            : "{$result['folder']} {$result['version']} yüklendi ve kuruldu.");
    }

    private function uploadResult(bool $ok, string $message): string
    {
        if (!Request::isAjax()) $this->back($message, $ok ? 'ok' : 'error');

        // Başarıda sayfa yenilenir, mesaj flash ile listenin üstünde görünür
        if ($ok) Session::flash('ok', $message);
        return $this->json($ok ? ['message' => $message] : ['error' => $message], $ok ? 200 : 422);
    }

    public function install(string $name): void  { $this->run($name, fn() => Module::install($name), "$name kuruldu."); }
    public function update(string $name): void   { $this->run($name, fn() => Module::update($name), "$name güncellendi."); }
    public function enable(string $name): void   { $this->run($name, fn() => Module::enable($name), "$name aktif edildi."); }
    public function disable(string $name): void  { $this->run($name, fn() => Module::disable($name), "$name pasife alındı; verileri duruyor."); }
    public function uninstall(string $name): void { $this->run($name, fn() => Module::uninstall($name), "$name kaldırıldı ve tabloları silindi."); }
    public function delete(string $name): void   { $this->run($name, fn() => Module::deleteFiles($name), "$name dosyaları silindi."); }

    // Modül görseli (sadece admin; kurulmamış/pasif modüller için de). SVG içindeki betikler çalışmasın diye sandbox'lı.
    public function icon(string $name): never
    {
        try {
            $file = Module::iconFile($name);
        } catch (Throwable) {
            $file = null;
        }
        if (!$file) $this->abort(404);

        $etag = '"' . Module::assetVersion($file) . '"';
        header('ETag: ' . $etag);
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; sandbox");
        if (trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
            http_response_code(304);
            exit;
        }

        header('Content-Type: ' . Module::ICONS[pathinfo($file, PATHINFO_EXTENSION)]);
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    }

    // Modülün ayar sayfası (settings.php tanımlıysa). Pasif modülün de ayarı düzenlenebilir.
    public function settings(string $name): View
    {
        $group = $this->settingsOf($name);
        return $this->view('admin/modules/settings', [
            'title'  => $group['label'] . ' ayarları',
            'folder' => $name,
            'module' => Module::all()[$name],
            'group'  => $group,
            'values' => Setting::all(),
        ]);
    }

    public function saveSettings(string $name): void
    {
        SettingForm::save($this->settingsOf($name)['fields']);
        Log::info('Modül ayarları güncellendi', ['module' => $name, 'admin' => $this->admin()['id']]);
        \App\Services\Activity::log('module.settings', "$name modülünün ayarlarını kaydetti", "admin/modules/$name/settings");
        $this->redirect("admin/modules/$name/settings", 'Ayarlar kaydedildi.');
    }

    private function settingsOf(string $name): array
    {
        try {
            $group = Module::settings($name);
        } catch (Throwable) {
            $group = null;
        }
        return $group ?? $this->abort(404);
    }

    private function run(string $name, callable $action, string $message): void
    {
        try {
            $action();
        } catch (Throwable $e) {
            $this->back($e->getMessage(), 'error');
        }
        $this->redirect('admin/modules', $message);
    }

    // php.ini boyutu: "8M" -> 8388608 (sunucunun kabul ettiği en büyük yükleme)
    private static function iniBytes(string $key): int
    {
        $v = trim((string) ini_get($key));
        $n = (int) $v;
        $mul = ['k' => 1024, 'm' => 1024 ** 2, 'g' => 1024 ** 3][strtolower(substr($v, -1))] ?? 1;
        return $n > 0 ? $n * $mul : PHP_INT_MAX;
    }
}
