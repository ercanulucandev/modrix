<?php
namespace App\Services;

use App\Models\User;
use Core\Database;

// İçerik geçmişi. Modül kendi türünü kaydeder ve her kayıtta sürüm ekler (bkz. Blog/Pages hooks.php):
//
//   Revision::register('post', 'Yazı', 'blog.manage', ['title', 'body', 'excerpt', 'meta_description']);
//   Hook::on('post.updated', fn(array $p) => Revision::save('post', $p));
//
// Düzenleyicide "Geçmiş" (app/Assets/lib/revisions.js) sürümleri listeler; seçilen sürüm düzenleyiciye yüklenir, kaydedince geçerli olur.
final class Revision
{
    public const KEEP = 30;   // içerik başına en fazla

    private static array $types = [];

    public static function register(string $type, string $label, string $permission, array $fields): void
    {
        self::$types[$type] = ['label' => $label, 'permission' => $permission, 'fields' => $fields];
    }

    public static function type(string $type): ?array
    {
        return self::$types[$type] ?? null;
    }

    // Kaydedilmiş kaydın (veritabanı satırı) sürümünü ekler; son sürümle aynıysa eklemez
    public static function save(string $type, array $row): void
    {
        $def = self::$types[$type] ?? null;
        if (!$def || empty($row['id'])) return;
        $data = array_intersect_key($row, array_flip($def['fields']));
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        $hash = sha1($json);

        $last = Database::table('revisions')->where('type', $type)->where('item_id', $row['id'])->orderByDesc('id')->first();
        if ($last && $last['hash'] === $hash) return;

        $user = User::admin();
        Database::table('revisions')->insert([
            'type' => $type, 'item_id' => $row['id'], 'user_id' => $user['id'] ?? null, 'user_name' => $user['name'] ?? null,
            'title' => mb_substr((string) ($row['title'] ?? ''), 0, 255), 'data' => $json, 'hash' => $hash, 'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Eskileri sil
        $keepFrom = Database::table('revisions')->where('type', $type)->where('item_id', $row['id'])->orderByDesc('id')->offset(self::KEEP - 1)->limit(1)->value('id');
        if ($keepFrom) Database::table('revisions')->where('type', $type)->where('item_id', $row['id'])->where('id', '<', $keepFrom)->delete();
    }

    public static function list(string $type, int $itemId): array
    {
        return Database::table('revisions')->select('id', 'user_name', 'title', 'created_at', 'data')
            ->where('type', $type)->where('item_id', $itemId)->orderByDesc('id')->get();
    }

    public static function find(int $id): ?array
    {
        $row = Database::table('revisions')->where('id', $id)->first();
        if ($row) $row['data'] = json_decode($row['data'], true) ?: [];
        return $row;
    }

    public static function forget(string $type, int $itemId): void
    {
        Database::table('revisions')->where('type', $type)->where('item_id', $itemId)->delete();
    }
}
