<?php
namespace App\Controllers\Admin;

use App\Services\Activity;
use App\Services\Backup;
use Core\View;
use Throwable;

// Admin > Yedekler: veritabanı + dosyalar ZIP olarak; indir, sil. Geri yükleme komut satırından (docs/backups.md).
class BackupController extends AdminController
{
    public function index(): View
    {
        return $this->view('admin/backups/index', [
            'title'   => 'Yedekler',
            'backups' => Backup::all(),
            'keep'    => max(1, (int) Setting::get('backup_keep', 10)),
        ]);
    }

    public function store(): void
    {
        $media = Request::post('type') !== 'db';
        try {
            $name = Backup::create($media);
        } catch (Throwable $e) {
            Log::error('Yedek alınamadı', ['error' => $e->getMessage()]);
            $this->redirect('admin/backups', 'Yedek alınamadı: ' . $e->getMessage(), 'error');
        }
        Activity::log('backup.created', ($media ? 'Tam yedek aldı: ' : 'Veritabanı yedeği aldı: ') . $name, 'admin/backups');
        $this->redirect('admin/backups', 'Yedek alındı: ' . $name);
    }

    public function download(string $name): never
    {
        $file = Backup::path($name) ?? $this->abort(404);
        Activity::log('backup.downloaded', "Yedeği indirdi: $name", 'admin/backups');
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Content-Length: ' . filesize($file));
        header('Cache-Control: private, no-store');
        readfile($file);
        exit;
    }

    public function destroy(string $name): void
    {
        if (!Backup::delete($name)) $this->abort(404);
        Activity::log('backup.deleted', "Yedeği sildi: $name", 'admin/backups');
        $this->redirect('admin/backups', 'Yedek silindi.');
    }
}
