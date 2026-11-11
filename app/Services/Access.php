<?php
namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Core\Hook;
use Core\Module;

// Yetkiler. Roller (Admin > Kullanıcılar > Roller) yetki listesi taşır; 'Yönetici' rolünde '*' = her şey.
//
//   Access::can('pages.manage')             giriş yapmış admin bu işi yapabilir mi
//   Router::...->middleware('can:pages.manage')
//   Menü öğesi: ['url' => ..., 'permission' => 'pages.manage']   (yetkisi olmayan görmez)
//
// Yetkiler: çekirdeğin listesi + aktif modüllerin module.json -> "permissions" + 'admin.permissions' hook'u
final class Access
{
    public const CORE = [
        'media.manage'      => 'Medya kütüphanesi (yükleme dışında düzenleme ve silme)',
        'appearance.manage' => 'Görünüm: temalar, özelleştir, menüler',
        'modules.manage'    => 'Modüller ve modül ayarları',
        'settings.manage'   => 'Site ayarları',
        'seo.manage'        => 'SEO: yönlendirmeler, bulunamayan adresler',
        'users.manage'      => 'Kullanıcılar ve roller',
        'activity.view'     => 'İşlem kaydını görme',
        'backups.manage'    => 'Yedekler: alma, indirme, silme',
    ];

    // ['Çekirdek' => [yetki => açıklama], 'Blog' => [...]]
    public static function permissions(): array
    {
        $groups = ['Genel' => self::CORE];
        foreach (Module::active() as $folder) {
            try {
                $m = Module::manifest($folder);
            } catch (\Throwable) {
                continue;
            }
            if ($m['permissions']) $groups[$m['name']] = $m['permissions'];
        }
        return Hook::filter('admin.permissions', $groups);
    }

    public static function all(): array
    {
        return array_merge(...array_values(self::permissions()));
    }

    public static function can(string $permission, ?array $user = null): bool
    {
        $user ??= User::admin();
        if (!$user) return false;
        $perms = Role::map()[$user['role']]['permissions'] ?? [];
        return in_array('*', $perms, true) || in_array($permission, $perms, true);
    }

    // Rol panele girebilir mi (roles tablosunda tanımlı mı)
    public static function isPanelRole(string $role): bool
    {
        return isset(Role::map()[$role]);
    }

    public static function isSuperRole(string $role): bool
    {
        return in_array('*', Role::map()[$role]['permissions'] ?? [], true);
    }
}
