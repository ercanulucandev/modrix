<?php
namespace Core;

// Migration'lar
//   Core:    app/Database/Migrations/*.sql
//   Modül:   app/Modules/{Ad}/Migrations/*.sql   (sadece kurulu modüller)
//   Geri al: aynı adla .down.sql  (ör. 2026_11_10_000002_create_posts_table.down.sql) -> modül kaldırılınca çalışır
//
// Dosyalar adına göre (tarih sırası) çalışır, çalışanlar schema_migrations tablosuna yazılır.
//   php modrix migrate                                       core + kurulu modüllerin bekleyenleri
//   php modrix migrate:status
//   php modrix make:migration create_posts_table [Modül]     .sql ve .down.sql oluşturur
class Migrator
{
    private const TABLE = 'schema_migrations';

    // Core + kurulu (aktif ya da pasif) modüllerin bekleyen migration'ları
    public static function migrate(?callable $onEach = null): array
    {
        $ran = self::run(self::coreFiles(), $onEach);
        foreach (Module::all() as $folder => $m) {
            if ($m['state'] !== 'not_installed') $ran = [...$ran, ...self::migrateModule($folder, $onEach)];
        }
        return $ran;
    }

    public static function migrateModule(string $folder, ?callable $onEach = null): array
    {
        return self::run(self::moduleFiles($folder), $onEach);
    }

    // Modülün çalışmış migration'larını ters sırayla geri alır (.down.sql varsa çalıştırır)
    public static function rollbackModule(string $folder): array
    {
        $done = array_flip(self::done());
        $files = array_reverse(array_filter(self::moduleFiles($folder), fn($f) => isset($done[basename($f)]), ARRAY_FILTER_USE_BOTH));

        $rolled = [];
        foreach ($files as $file) {
            $down = substr($file, 0, -4) . '.down.sql';
            if (is_file($down)) {
                foreach (self::statements(file_get_contents($down)) as $sql) Database::pdo()->exec($sql);
            } else {
                Log::warning('Geri alma dosyası yok, tablo silinmedi: ' . basename($down));
            }
            Database::table(self::TABLE)->where('migration', basename($file))->delete();
            $rolled[] = basename($file);
        }
        return $rolled;
    }

    // ['dosya.sql' => ['module' => 'Blog' | null, 'done' => true], ...]
    public static function status(): array
    {
        $done = array_flip(self::done());
        $out = [];
        foreach (self::coreFiles() as $f) $out[basename($f)] = ['module' => null, 'done' => isset($done[basename($f)])];
        foreach (array_keys(Module::all()) as $folder) {
            foreach (self::moduleFiles($folder) as $f) $out[basename($f)] = ['module' => $folder, 'done' => isset($done[basename($f)])];
        }
        ksort($out);
        return $out;
    }

    // Yeni migration (+ boş .down.sql); modül verilirse modülün Migrations klasörüne
    public static function make(string $name, ?string $module = null): string
    {
        if ($module) Module::assertFolder($module);
        $dir = $module ? ROOT . "/app/Modules/$module/Migrations" : ROOT . '/app/Database/Migrations';
        if (!is_dir($dir)) mkdir($dir, 0775, true);

        $base = $dir . '/' . date('Y_m_d_His') . '_' . preg_replace('/[^a-z0-9_]/', '_', strtolower($name));
        file_put_contents("$base.sql", "-- $name\n\n");
        file_put_contents("$base.down.sql", "-- $name (geri alma; modül kaldırılınca çalışır)\n\n");
        return "$base.sql";
    }

    // ================================================================ İÇ

    private static function coreFiles(): array
    {
        return self::sqlFiles(ROOT . '/app/Database/Migrations');
    }

    private static function moduleFiles(string $folder): array
    {
        Module::assertFolder($folder);
        return self::sqlFiles(ROOT . "/app/Modules/$folder/Migrations");
    }

    // .down.sql hariç, ada göre sıralı
    private static function sqlFiles(string $dir): array
    {
        $files = array_filter(glob("$dir/*.sql") ?: [], fn($f) => !str_ends_with($f, '.down.sql'));
        sort($files);
        return array_values($files);
    }

    private static function run(array $files, ?callable $onEach): array
    {
        $done = array_flip(self::done());
        $ran = [];
        foreach ($files as $file) {
            $name = basename($file);
            if (isset($done[$name])) continue;
            foreach (self::statements(file_get_contents($file)) as $sql) Database::pdo()->exec($sql);
            Database::table(self::TABLE)->insert(['migration' => $name, 'ran_at' => date('Y-m-d H:i:s')]);
            $ran[] = $name;
            if ($onEach) $onEach($name);
        }
        return $ran;
    }

    private static function done(): array
    {
        Database::pdo()->exec('CREATE TABLE IF NOT EXISTS ' . self::TABLE . ' (
            migration VARCHAR(190) PRIMARY KEY,
            ran_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        return Database::table(self::TABLE)->pluck('migration');
    }

    // "--" yorumları atılır; satır sonundaki ";" ifadeyi bitirir
    private static function statements(string $sql): array
    {
        $sql = preg_replace('/^\s*--.*$/m', '', $sql);
        return array_values(array_filter(array_map('trim', preg_split('/;\s*$/m', $sql))));
    }
}
