<?php
namespace App\Services;

use App\Models\User;
use Core\Database;
use Core\Request;
use Core\Setting;
use Throwable;

// İşlem kaydı: yönetim panelinde kim, ne zaman, ne yaptı (Admin > Kullanıcılar > İşlem kaydı).
//
//   Activity::log('post.updated', '"Merhaba" yazısını güncelledi', 'admin/blog/5/edit');
//
// Eylem adları 'konu.fiil' biçimindedir; listede gruplanır (ACTIONS). Kayıtlar Ayarlar > Güvenlik'teki süreden eskiyse silinir.
final class Activity
{
    public const GROUPS = [
        'auth'       => 'Giriş ve hesap',
        'post'       => 'Yazılar',
        'page'       => 'Sayfalar',
        'media'      => 'Medya',
        'settings'   => 'Ayarlar',
        'appearance' => 'Görünüm',
        'module'     => 'Modüller',
        'user'       => 'Kullanıcılar',
        'seo'        => 'SEO',
        'form'       => 'Formlar',
        'backup'     => 'Yedekler',
        'system'     => 'Sistem',
        'collection' => 'Koleksiyonlar',
        'newsletter' => 'Bülten',
    ];

    public static function log(string $action, string $description, ?string $subjectUrl = null, ?array $user = null): void
    {
        try {
            $user ??= User::admin();
            Database::table('activity_log')->insert([
                'user_id'     => $user['id'] ?? null,
                'user_name'   => $user['name'] ?? null,
                'action'      => substr($action, 0, 40),
                'description' => mb_substr($description, 0, 500),
                'subject_url' => $subjectUrl ? substr($subjectUrl, 0, 255) : null,
                'ip'          => Request::ip(),
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
            // Arada bir eski kayıtları temizle (her istekte değil)
            if (random_int(1, 50) === 1) self::prune();
        } catch (Throwable) {
            // İşlem kaydı asıl işi asla bozmamalı (tablo yoksa vb.)
        }
    }

    public static function prune(): void
    {
        $days = max(7, (int) Setting::get('activity_days', 180));
        Database::table('activity_log')->where('created_at', '<', date('Y-m-d H:i:s', strtotime("-$days days")))->delete();
    }
}
