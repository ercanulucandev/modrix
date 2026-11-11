<?php
namespace App\Controllers\Admin;

use App\Models\User;
use App\Services\Access;
use App\Services\Backup;
use App\Services\PageCache;
use App\Services\TwoFactor;
use Core\Database;
use Core\Hook;
use Core\Module;
use Core\Setting;
use Core\View;

// Genel bakış. Modüller hook'larla katkıda bulunur (hepsinde 'permission' ve 'order' olabilir):
//   admin.dashboard.actions  hızlı işlemler     ['label' => 'Yeni yazı', 'url' => 'admin/blog/create', 'icon' => 'document']
//   admin.dashboard.todos    dikkat isteyenler  ['text' => '3 sipariş bekliyor', 'url' => 'admin/shop/orders', 'tone' => 'warning'|'info']
//   admin.dashboard.stats    sayı kutuları      ['label' => 'Yazı', 'value' => 12, 'url' => 'admin/blog', 'hint' => '3 taslak']
//   admin.dashboard.series   30 günlük grafik   bkz. App\Services\Dashboard
//   admin.dashboard.widgets  kartlar            ['html' => View::render(...), 'size' => 'half'|'full']
class DashboardController extends AdminController
{
    public function index(): View
    {
        $admin = $this->admin();
        $actions = Hook::filter('admin.dashboard.actions', [
            ['label' => 'Medya yükle', 'url' => 'admin/media', 'icon' => 'image', 'order' => 70, 'permission' => 'media.manage'],
            ['label' => 'Görünümü düzenle', 'url' => 'admin/appearance/customize', 'icon' => 'palette', 'order' => 80, 'permission' => 'appearance.manage'],
        ]);
        $stats = Hook::filter('admin.dashboard.stats', [
            ['label' => 'Kullanıcı', 'value' => User::count(), 'url' => 'admin/users', 'order' => 90, 'permission' => 'users.manage'],
        ]);

        return $this->view('admin/dashboard', [
            'title'    => 'Genel bakış',
            'admin'    => $admin,
            'actions'  => self::sorted($actions),
            'todos'    => self::sorted(Hook::filter('admin.dashboard.todos', $this->coreTodos($admin))),
            'stats'    => self::sorted($stats),
            'series'   => self::sorted(Hook::filter('admin.dashboard.series', [])),
            'widgets'  => self::sorted(Hook::filter('admin.dashboard.widgets', [])),
            'activity' => Access::can('activity.view') ? Database::table('activity_log')->orderByDesc('id')->limit(8)->get() : [],
            'system'   => Access::can('settings.manage') ? $this->system() : null,
        ]);
    }

    // Sistemin dikkat isteyen durumları
    private function coreTodos(array $admin): array
    {
        $todos = [];
        if (Setting::bool('maintenance_mode')) $todos[] = ['text' => 'Bakım modu açık: ziyaretçiler siteyi göremiyor.', 'url' => 'admin/settings?tab=maintenance', 'tone' => 'warning', 'permission' => 'settings.manage'];
        if (Setting::bool('robots_noindex')) $todos[] = ['text' => 'Site arama motorlarından gizli (Google listelemiyor).', 'url' => 'admin/settings?tab=seo', 'tone' => 'warning', 'permission' => 'settings.manage'];
        if (in_array(Setting::get('mail_driver'), ['log', null, ''], true)) $todos[] = ['text' => 'E-posta gönderimi ayarlanmamış (form ve sipariş bildirimleri gitmez).', 'url' => 'admin/settings?tab=mail', 'tone' => 'warning', 'permission' => 'settings.manage'];
        $updates = array_filter(Module::all(), fn($m) => $m['update']);
        if ($updates) $todos[] = ['text' => count($updates) . ' modül için güncelleme var: ' . implode(', ', array_map(fn($m) => $m['name'], $updates)), 'url' => 'admin/modules', 'tone' => 'info', 'permission' => 'modules.manage'];
        $last = Backup::all()[0]['time'] ?? null;
        if (!$last || $last < strtotime('-7 days')) $todos[] = ['text' => $last ? 'Son yedek ' . (int) ((time() - $last) / 86400) . ' gün önce alındı.' : 'Henüz hiç yedek alınmadı.', 'url' => 'admin/backups', 'tone' => 'info', 'permission' => 'backups.manage'];
        if (!TwoFactor::enabled($admin)) $todos[] = ['text' => 'Hesabınızda iki adımlı doğrulama kapalı.', 'url' => 'admin/profile/security', 'tone' => 'info', 'order' => 95];
        return $todos;
    }

    private function system(): array
    {
        $backup = Backup::all()[0] ?? null;
        return [
            'PHP'           => PHP_VERSION,
            'Aktif modül'   => count(Module::active()) . ' / ' . count(Module::all()),
            'Tema'          => \Core\Theme::manifest(\Core\Theme::saved())['name'] ?? \Core\Theme::saved(),
            'Son yedek'     => $backup ? date('d.m.Y H:i', $backup['time']) : 'yok',
            'Sayfa önbelleği' => PageCache::enabled() ? 'açık (' . PageCache::stats()['count'] . ' sayfa)' : 'kapalı',
            'E-posta'       => ['smtp' => 'SMTP', 'mail' => 'PHP mail()', 'log' => 'gönderilmiyor (log)'][(string) Setting::get('mail_driver')] ?? 'ayarlanmamış',
        ];
    }

    // Sıralı ve yetkiye göre süzülmüş ('permission' anahtarı olan öğeler)
    private static function sorted(array $items): array
    {
        $items = array_values(array_filter($items, fn($i) => empty($i['permission']) || Access::can($i['permission'])));
        usort($items, fn($a, $b) => ($a['order'] ?? 50) <=> ($b['order'] ?? 50));
        return $items;
    }
}
