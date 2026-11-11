<?php
namespace App\Models;

use Core\Model;

class Role extends Model
{
    protected static string $table = 'roles';
    protected static array $fillable = ['name', 'slug', 'permissions', 'is_system'];

    private static ?array $cache = null;

    // ['admin' => ['name' => 'Yönetici', 'permissions' => ['*'], ...], ...]
    public static function map(): array
    {
        if (self::$cache !== null) return self::$cache;
        $map = [];
        foreach (static::query()->orderBy('id')->get() as $row) {
            $row['permissions'] = json_decode((string) $row['permissions'], true) ?: [];
            $map[$row['slug']] = $row;
        }
        return self::$cache = $map;
    }

    public static function forget(): void
    {
        self::$cache = null;
    }

    // Seçim listesi: ['admin' => 'Yönetici', ...]
    public static function options(): array
    {
        return array_map(fn($r) => $r['name'], self::map());
    }
}
