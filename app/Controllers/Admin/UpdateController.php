<?php
namespace App\Controllers\Admin;

use App\Services\Crypt;
use App\Services\Updater;
use Core\Database;
use Core\Setting;
use Core\View;
use Throwable;

// Admin > Sistem > Güncellemeler
class UpdateController extends AdminController
{
    public function index(): View
    {
        return $this->view('admin/updates/index', [
            'title'    => 'Güncellemeler',
            'current'  => Updater::current(),
            'latest'   => Updater::latest((bool) Request::get('refresh')),
            'repo'     => Updater::repository(),
            'hasToken' => (bool) Setting::get('update_token'),
            'history'  => Database::table('activity_log')->where('action', 'system.update')->orderByDesc('id')->limit(10)->get(),
            'steps'    => Session::flash('update_log') ?? [],
        ]);
    }

    public function apply(): void
    {
        $steps = [];
        try {
            $result = Updater::apply(null, false, function (string $m) use (&$steps) { $steps[] = $m; });
            Session::flash('update_log', $steps);
            $this->redirect('admin/updates', "Modrix v{$result['to']} sürümüne güncellendi. Modüller sayfasında modül güncellemeleri varsa onları da yapın.");
        } catch (Throwable $e) {
            Session::flash('update_log', $steps);
            $this->redirect('admin/updates', 'Güncelleme yapılmadı: ' . $e->getMessage(), 'error');
        }
    }

    public function token(): void
    {
        $token = trim((string) Request::post('token', ''));
        if (Request::post('remove')) $token = '';
        Setting::set(['update_token' => $token === '' ? null : Crypt::encrypt($token)]);
        @unlink(ROOT . '/storage/cache/update.json');
        $this->redirect('admin/updates?refresh=1', $token === '' ? 'Erişim anahtarı kaldırıldı.' : 'Erişim anahtarı kaydedildi (şifreli).');
    }
}
