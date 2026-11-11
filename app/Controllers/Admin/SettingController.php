<?php
namespace App\Controllers\Admin;

use App\Services\SettingForm;
use Core\Setting;
use Core\View;

// Admin > Ayarlar: sitenin genel ayarları. Sekmeler ve alanlar app/Config/settings.php içinde tanımlı.
// Modüllerin ayarları burada değil, kendi sayfalarındadır (ModuleController::settings).
class SettingController extends AdminController
{
    public function index(): View
    {
        $groups = Setting::groups();
        $tab = (string) Request::get('tab', array_key_first($groups));
        if (!isset($groups[$tab])) $tab = array_key_first($groups);

        return $this->view('admin/settings/index', [
            'title'  => 'Ayarlar',
            'groups' => $groups,
            'tab'    => $tab,
            'values' => Setting::all(),
        ]);
    }

    // Ayarlar > E-posta: kayıtlı ayarlarla deneme e-postası
    public function cacheClear(): void
    {
        $count = \App\Services\PageCache::flush();
        $this->redirect('admin/settings?tab=performance', "Önbellek temizlendi ($count sayfa).");
    }

    public function mailTest(): void
    {
        $data = $this->validate(['to' => 'required|email'], ['to' => 'Alıcı']);
        $site = (string) Setting::get('site_name', 'Site');
        $ok = Mail::send($data['to'], "Deneme e-postası · $site",
            "Merhaba,\n\nBu, $site yönetim panelinden gönderilen bir deneme e-postasıdır. Bu e-postayı aldıysanız ayarlar doğru.\n",
            ['html' => '<p>Merhaba,</p><p>Bu, <strong>' . View::e($site) . '</strong> yönetim panelinden gönderilen bir deneme e-postasıdır. Bu e-postayı aldıysanız ayarlar doğru.</p>']);

        $this->redirect('admin/settings?tab=mail', $ok
            ? (Setting::get('mail_driver') === 'log' ? 'E-posta gönderilmedi, storage/logs/mail.log dosyasına yazıldı (geliştirme modu).' : "Deneme e-postası gönderildi: {$data['to']}")
            : 'Gönderilemedi: ' . Mail::lastError(), $ok ? 'ok' : 'error');
    }

    public function update(string $group): void
    {
        $def = Setting::groups()[$group] ?? $this->abort(404);

        SettingForm::save($def['fields'], $def['translatable'] ?? true);
        Log::info('Ayarlar güncellendi', ['group' => $group, 'admin' => $this->admin()['id']]);
        \App\Services\Activity::log('settings.updated', 'Ayarlar > ' . $def['label'] . ' sekmesini kaydetti', 'admin/settings?tab=' . $group);
        $this->redirect('admin/settings?tab=' . $group, 'Ayarlar kaydedildi.');
    }
}
