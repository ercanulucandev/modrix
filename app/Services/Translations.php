<?php
namespace App\Services;

use Core\Database;
use Core\Lang;

// İçerik çevirileri: aynı içeriğin farklı dillerdeki satırları 'translation_group' ile bağlanır (grup = ilk satırın id'si).
//   Translations::siblings('pages', $page)   ['tr' => satır, 'en' => satır]  (kendisi dahil)
final class Translations
{
    public static function group(array $row): int
    {
        return (int) ($row['translation_group'] ?: $row['id']);
    }

    public static function siblings(string $table, array $row, array $columns = ['*']): array
    {
        $group = self::group($row);
        $rows = Database::table($table)->select(...$columns)
            ->where(fn($w) => $w->where('translation_group', $group)->orWhere('id', $group))->get();
        $out = [];
        foreach ($rows as $r) if (Lang::exists($r['lang'])) $out[$r['lang']] = $r;
        return $out;
    }

    // Satırı grubun başı yap (ilk çeviri oluşturulurken)
    public static function ensureGroup(string $table, array $row): int
    {
        $group = self::group($row);
        if (!$row['translation_group']) Database::table($table)->where('id', $row['id'])->update(['translation_group' => $group]);
        return $group;
    }
}
