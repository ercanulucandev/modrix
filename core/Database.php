<?php
namespace Core;

use PDO, PDOStatement, Throwable;

// Veritabanı (PDO, prepared sorgular). Global takma adı: DB
//
// Önerilen: query builder
//   DB::table('posts')->where('status', 'published')->latest()->paginate(20)     (core/QueryBuilder.php)
//
// Ham SQL gerektiğinde:
//   DB::all('SELECT * FROM posts WHERE status = ?', ['published'])
//   DB::first('SELECT * FROM posts WHERE id = ?', [5])
//   DB::value('SELECT COUNT(*) FROM posts')
//   DB::insert('posts', ['title' => 'Merhaba'])            // id döner
//   DB::update('posts', ['title' => 'Yeni'], 'id = ?', [5]) // etkilenen satır
//   DB::delete('posts', 'id = ?', [5])
//   DB::transaction(function () { ... })                    // hata olursa geri alınır
class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo) return self::$pdo;

        $c = Config::get('database');
        $dsn = "mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset={$c['charset']}";

        return self::$pdo = new PDO($dsn, $c['user'], $c['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    // Query builder: DB::table('posts')->where('status', 'published')->latest()->paginate(20)
    // Tüm metotlar için core/QueryBuilder.php
    public static function table(string $table): QueryBuilder
    {
        return new QueryBuilder($table);
    }

    public static function query(string $sql, array $params = []): PDOStatement
    {
        $st = self::pdo()->prepare($sql);
        $st->execute(array_values($params));
        return $st;
    }

    public static function all(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    public static function first(string $sql, array $params = []): ?array
    {
        return self::query($sql, $params)->fetch() ?: null;
    }

    public static function value(string $sql, array $params = [])
    {
        $v = self::query($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    // Sayfalı sorgu. Sayfa numarası verilmezse adresteki ?page= okunur.
    //   $result = DB::paginate('SELECT * FROM posts WHERE status = ? ORDER BY id DESC', ['published'], 20);
    // Dönüş: ['data' => [...], 'total' => 135, 'page' => 2, 'per_page' => 20, 'pages' => 7, 'from' => 21, 'to' => 40]
    // Görünümde: echo View::render('partials/pagination', ['page' => $result]);
    public static function paginate(string $sql, array $params = [], int $perPage = 20, ?int $page = null): array
    {
        $perPage = max(1, $perPage);
        $total   = (int) self::value("SELECT COUNT(*) FROM ($sql) AS _count", $params);
        $pages   = max(1, (int) ceil($total / $perPage));
        $page    = min(max(1, $page ?? (int) Request::get('page', 1)), $pages);
        $offset  = ($page - 1) * $perPage;

        $data = $total ? self::all("$sql LIMIT $perPage OFFSET $offset", $params) : [];

        return [
            'data'     => $data,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'pages'    => $pages,
            'from'     => $data ? $offset + 1 : 0,
            'to'       => $offset + count($data),
        ];
    }

    // Kısayollar (QueryBuilder üzerinden; tablo/kolon adları doğrulanır)
    public static function insert(string $table, array $data): int
    {
        return self::table($table)->insertGetId($data);
    }

    public static function update(string $table, array $data, string $where, array $params = []): int
    {
        return self::table($table)->whereRaw($where, $params)->update($data);
    }

    public static function delete(string $table, string $where, array $params = []): int
    {
        return self::table($table)->whereRaw($where, $params)->delete();
    }

    // İç içe çağrılabilir: zaten bir işlem içindeyse içteki ona katılır (hata olursa en dıştaki geri alır)
    public static function transaction(callable $callback)
    {
        $pdo = self::pdo();
        if ($pdo->inTransaction()) return $callback();
        $pdo->beginTransaction();
        try {
            $result = $callback();
            $pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}
