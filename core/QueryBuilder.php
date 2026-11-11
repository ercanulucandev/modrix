<?php
namespace Core;

use Closure;
use InvalidArgumentException;
use LogicException;

// Zincirleme sorgu oluşturucu. Değerler her zaman prepared statement ile bağlanır;
// kolon/tablo adları doğrulanıp `tırnaklanır` (geçersiz ad -> hata), operatörler beyaz listeden.
//
// Okuma
//   DB::table('posts')->where('status', 'published')->latest()->limit(10)->get();
//   DB::table('posts')->where('views', '>', 100)->orWhere('featured', 1)->first();
//   DB::table('posts')->whereIn('id', [1, 2, 3])->pluck('title', 'id');      // [1 => 'Başlık', ...]
//   DB::table('posts')->where(fn($q) => $q->where('a', 1)->orWhere('b', 2))->count();   // (a = ? OR b = ?)
//   DB::table('posts')->search(['title', 'body'], $q)->paginate(20);
//   DB::table('posts as p')->join('users as u', 'u.id', '=', 'p.user_id')->select('p.*', 'u.name as author')->get();
//   DB::table('posts')->select('status')->selectRaw('COUNT(*) AS total')->groupBy('status')->get();
//   DB::table('posts')->when($status, fn($q) => $q->where('status', $status))->get();
//
// Yazma
//   DB::table('posts')->insert(['title' => 'A']);                  // eklenen satır sayısı
//   DB::table('posts')->insertGetId(['title' => 'A']);             // yeni id
//   DB::table('posts')->where('id', 5)->update(['title' => 'B']);  // etkilenen satır sayısı
//   DB::table('posts')->where('id', 5)->increment('views');
//   DB::table('posts')->where('id', 5)->delete();
//   Koşulsuz update/delete reddedilir (yanlışlıkla tüm tabloyu silmemek için); bilerek: ->whereRaw('1 = 1')
//
// Hata ayıklama: ->toSql(), ->getBindings()
class QueryBuilder
{
    private const OPERATORS = ['=', '<', '>', '<=', '>=', '<>', '!=', 'LIKE', 'NOT LIKE'];

    private string $table;         // tırnaklanmış: `posts` AS `p`
    private string $tableName;     // ham: posts as p
    private array $columns = [];
    private bool $distinct = false;
    private array $joins = [];
    private array $wheres = [];    // [['boolean' => 'AND', 'sql' => '...', 'bindings' => [...]], ...]
    private array $groups = [];
    private array $havings = [];
    private array $orders = [];
    private ?int $limit = null;
    private ?int $offset = null;

    private array $selectBindings = [];
    private array $orderBindings = [];

    public function __construct(string $table)
    {
        $this->table = self::table($table);
        $this->tableName = $table;
    }

    // ================================================================ SELECT

    // Kolonları değiştirir (önceki selectRaw'lar ve değerleri de kalkar)
    public function select(string ...$columns): self
    {
        $this->columns = array_map([self::class, 'column'], $columns ?: ['*']);
        $this->selectBindings = [];
        return $this;
    }

    public function addSelect(string ...$columns): self
    {
        foreach ($columns as $c) $this->columns[] = self::column($c);
        return $this;
    }

    // Ham ifade: ->selectRaw('COUNT(*) AS total'), ->selectRaw('price * ? AS with_tax', [1.2])
    public function selectRaw(string $sql, array $bindings = []): self
    {
        $this->columns[] = $sql;
        $this->selectBindings = [...$this->selectBindings, ...array_values($bindings)];
        return $this;
    }

    public function distinct(): self
    {
        $this->distinct = true;
        return $this;
    }

    // ================================================================ JOIN

    public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): self
    {
        $type = strtoupper($type);
        if (!in_array($type, ['INNER', 'LEFT', 'RIGHT'], true)) throw new InvalidArgumentException("Geçersiz join türü: $type");

        $this->joins[] = "$type JOIN " . self::table($table) . ' ON ' . self::column($first) . ' ' . self::operator($operator) . ' ' . self::column($second);
        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    public function rightJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'RIGHT');
    }

    // ================================================================ WHERE

    // where('status', 'published')         -> status = ?
    // where('views', '>', 100)              -> views > ?
    // where('deleted_at', null)             -> deleted_at IS NULL
    // where(['status' => 'x', 'type' => 1]) -> status = ? AND type = ?
    // where(fn($q) => ...)                  -> ( ... )  iç içe grup
    public function where(string|array|Closure $column, $operator = null, $value = null, string $boolean = 'AND'): self
    {
        if ($column instanceof Closure) return $this->nested($column, $boolean);

        if (is_array($column)) {
            return $this->nested(function (self $q) use ($column) {
                foreach ($column as $col => $val) $q->where($col, '=', $val);
            }, $boolean);
        }

        // İki argümanlı kullanım: where('status', 'published')
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }
        $operator = self::operator((string) $operator);

        if ($value === null) {
            if (!in_array($operator, ['=', '!=', '<>'], true)) throw new InvalidArgumentException("NULL ile '$operator' kullanılamaz");
            return $operator === '=' ? $this->whereNull($column, $boolean) : $this->whereNotNull($column, $boolean);
        }

        return $this->addWhere($boolean, self::column($column) . " $operator ?", [self::scalar($value)]);
    }

    public function orWhere(string|array|Closure $column, $operator = null, $value = null): self
    {
        return func_num_args() === 2
            ? $this->where($column, '=', $operator, 'OR')
            : $this->where($column, $operator, $value, 'OR');
    }

    public function whereNot(string $column, $value): self
    {
        return $value === null ? $this->whereNotNull($column) : $this->where($column, '!=', $value);
    }

    public function whereIn(string $column, array $values, string $boolean = 'AND', bool $not = false): self
    {
        // Boş liste SQL hatası vermez: IN () her zaman yanlış, NOT IN () her zaman doğru
        if (!$values) return $this->addWhere($boolean, $not ? '1 = 1' : '0 = 1', []);

        $marks = implode(', ', array_fill(0, count($values), '?'));
        return $this->addWhere($boolean, self::column($column) . ($not ? ' NOT IN ' : ' IN ') . "($marks)", array_map([self::class, 'scalar'], array_values($values)));
    }

    public function whereNotIn(string $column, array $values): self    { return $this->whereIn($column, $values, 'AND', true); }
    public function orWhereIn(string $column, array $values): self     { return $this->whereIn($column, $values, 'OR'); }
    public function orWhereNotIn(string $column, array $values): self  { return $this->whereIn($column, $values, 'OR', true); }

    public function whereNull(string $column, string $boolean = 'AND'): self    { return $this->addWhere($boolean, self::column($column) . ' IS NULL', []); }
    public function whereNotNull(string $column, string $boolean = 'AND'): self { return $this->addWhere($boolean, self::column($column) . ' IS NOT NULL', []); }
    public function orWhereNull(string $column): self                           { return $this->whereNull($column, 'OR'); }
    public function orWhereNotNull(string $column): self                        { return $this->whereNotNull($column, 'OR'); }

    public function whereBetween(string $column, array $range, string $boolean = 'AND', bool $not = false): self
    {
        if (count($range) !== 2) throw new InvalidArgumentException('whereBetween iki değer ister: [min, max]');
        [$min, $max] = array_values($range);
        return $this->addWhere($boolean, self::column($column) . ($not ? ' NOT' : '') . ' BETWEEN ? AND ?', [self::scalar($min), self::scalar($max)]);
    }

    public function whereNotBetween(string $column, array $range): self { return $this->whereBetween($column, $range, 'AND', true); }

    // Tarih kısmına göre: ->whereDate('created_at', '>=', '2026-11-01')
    public function whereDate(string $column, string $operator, string $date): self
    {
        return $this->addWhere('AND', 'DATE(' . self::column($column) . ') ' . self::operator($operator) . ' ?', [date('Y-m-d', strtotime($date))]);
    }

    // İçinde geçen: ->whereLike('title', 'php') -> title LIKE '%php%'  (% ve _ kaçırılır)
    public function whereLike(string $column, string $value, string $boolean = 'AND'): self
    {
        return $this->addWhere($boolean, self::column($column) . ' LIKE ?', ['%' . self::escapeLike($value) . '%']);
    }

    public function orWhereLike(string $column, string $value): self { return $this->whereLike($column, $value, 'OR'); }

    // Birden çok kolonda arama: ->search(['title', 'body'], 'php') -> (title LIKE ? OR body LIKE ?). Boş terimde hiçbir şey eklemez.
    public function search(array $columns, ?string $term): self
    {
        $term = trim((string) $term);
        if ($term === '' || !$columns) return $this;

        return $this->where(function (self $q) use ($columns, $term) {
            foreach ($columns as $c) $q->orWhereLike($c, $term);
        });
    }

    // Ham koşul: ->whereRaw('YEAR(created_at) = ?', [2026])
    public function whereRaw(string $sql, array $bindings = [], string $boolean = 'AND'): self
    {
        return $this->addWhere($boolean, $sql, array_values($bindings));
    }

    public function orWhereRaw(string $sql, array $bindings = []): self
    {
        return $this->whereRaw($sql, $bindings, 'OR');
    }

    // ================================================================ GROUP / HAVING / ORDER / LIMIT

    public function groupBy(string ...$columns): self
    {
        foreach ($columns as $c) $this->groups[] = self::column($c);
        return $this;
    }

    // ->having('total', '>', 5)  (total bir select alias'ı olabilir)
    public function having(string $column, string $operator, $value): self
    {
        $this->havings[] = ['sql' => self::column($column) . ' ' . self::operator($operator) . ' ?', 'bindings' => [self::scalar($value)]];
        return $this;
    }

    public function havingRaw(string $sql, array $bindings = []): self
    {
        $this->havings[] = ['sql' => $sql, 'bindings' => array_values($bindings)];
        return $this;
    }

    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $direction = strtoupper($direction);
        if (!in_array($direction, ['ASC', 'DESC'], true)) throw new InvalidArgumentException("Geçersiz sıralama yönü: $direction");
        $this->orders[] = self::column($column) . " $direction";
        return $this;
    }

    public function orderByDesc(string $column): self             { return $this->orderBy($column, 'desc'); }
    public function latest(string $column = 'created_at'): self    { return $this->orderBy($column, 'desc'); }
    public function oldest(string $column = 'created_at'): self    { return $this->orderBy($column, 'asc'); }
    public function inRandomOrder(): self                          { $this->orders[] = 'RAND()'; return $this; }

    public function orderByRaw(string $sql, array $bindings = []): self
    {
        $this->orders[] = $sql;
        $this->orderBindings = [...$this->orderBindings, ...array_values($bindings)];
        return $this;
    }

    public function limit(int $limit): self   { $this->limit = max(0, $limit); return $this; }
    public function offset(int $offset): self { $this->offset = max(0, $offset); return $this; }
    public function take(int $n): self        { return $this->limit($n); }
    public function skip(int $n): self        { return $this->offset($n); }

    public function forPage(int $page, int $perPage = 20): self
    {
        return $this->limit($perPage)->offset((max(1, $page) - 1) * $perPage);
    }

    // Koşullu ekleme: ->when($q, fn($query) => $query->search(['title'], $q))
    public function when($condition, callable $callback, ?callable $default = null): self
    {
        if ($condition) $callback($this, $condition);
        elseif ($default) $default($this, $condition);
        return $this;
    }

    // ================================================================ SONUÇ

    public function get(): array
    {
        return Database::all($this->toSql(), $this->getBindings());
    }

    public function first(): ?array
    {
        return (clone $this)->limit(1)->get()[0] ?? null;
    }

    public function find(int|string $id, string $column = 'id'): ?array
    {
        return (clone $this)->where($column, '=', $id)->first();
    }

    // Tek kolonun değeri: ->where('id', 5)->value('title')
    public function value(string $column)
    {
        $row = (clone $this)->select($column)->first();
        return $row ? reset($row) : null;
    }

    // ->pluck('title') -> ['A', 'B'],  ->pluck('title', 'id') -> [1 => 'A', 2 => 'B']
    public function pluck(string $column, ?string $key = null): array
    {
        $q = clone $this;
        $q->columns = $q->selectBindings = [];
        $q->addSelect($column);
        if ($key) $q->addSelect($key);

        $rows  = $q->get();
        $vName = self::alias($column);
        return $key ? array_column($rows, $vName, self::alias($key)) : array_column($rows, $vName);
    }

    public function exists(): bool
    {
        $q = clone $this;
        $q->columns = ['1'];
        $q->selectBindings = [];
        return $q->limit(1)->get() !== [];
    }

    public function doesntExist(): bool { return !$this->exists(); }

    public function count(string $column = '*'): int  { return (int) $this->aggregate('COUNT', $column); }
    public function sum(string $column): float        { return (float) $this->aggregate('SUM', $column); }
    public function avg(string $column): float        { return (float) $this->aggregate('AVG', $column); }
    public function min(string $column)               { return $this->aggregate('MIN', $column); }
    public function max(string $column)               { return $this->aggregate('MAX', $column); }

    // Sayfalı sonuç (DB::paginate ile aynı yapı): ['data', 'total', 'page', 'per_page', 'pages', 'from', 'to']
    public function paginate(int $perPage = 20, ?int $page = null): array
    {
        $q = clone $this;
        $q->limit = $q->offset = null;
        return Database::paginate($q->toSql(), $q->getBindings(), $perPage, $page);
    }

    // Büyük tabloları parça parça işler (belleği doldurmaz). Callback false dönerse durur.
    //   DB::table('users')->orderBy('id')->chunk(500, function (array $rows) { ... });
    public function chunk(int $size, callable $callback): void
    {
        if (!$this->orders) throw new LogicException('chunk() için orderBy() gerekli (yoksa kayıtlar atlanabilir)');
        for ($page = 1; ; $page++) {
            $rows = (clone $this)->forPage($page, $size)->get();
            if (!$rows || $callback($rows, $page) === false || count($rows) < $size) return;
        }
    }

    // ================================================================ YAZMA

    // Tek satır ya da satır listesi. Eklenen satır sayısını döner.
    public function insert(array $values): int
    {
        if (!$values) return 0;
        $rows = array_is_list($values) && is_array($values[0] ?? null) ? $values : [$values];

        $columns = array_keys($rows[0]);
        $bindings = [];
        foreach ($rows as $i => $row) {
            if (array_keys($row) !== $columns) throw new InvalidArgumentException("insert: $i. satırın kolonları ilk satırla aynı olmalı");
            foreach ($row as $v) $bindings[] = self::scalar($v);
        }

        $marks = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $sql = 'INSERT INTO ' . $this->table . ' (' . implode(', ', array_map([self::class, 'column'], $columns)) . ') VALUES '
             . implode(', ', array_fill(0, count($rows), $marks));

        return Database::query($sql, $bindings)->rowCount();
    }

    public function insertGetId(array $values): int
    {
        $this->insert($values);
        return (int) Database::pdo()->lastInsertId();
    }

    public function update(array $values): int
    {
        if (!$values) return 0;
        $this->guardWrite('update');

        $set = implode(', ', array_map(fn($c) => self::column($c) . ' = ?', array_keys($values)));
        $sql = 'UPDATE ' . $this->table . ' SET ' . $set . $this->compileWheres() . $this->compileOrderLimit();
        return Database::query($sql, [...array_map([self::class, 'scalar'], array_values($values)), ...$this->whereBindings(), ...$this->orderBindings])->rowCount();
    }

    public function increment(string $column, int|float $amount = 1, array $extra = []): int
    {
        $this->guardWrite('increment');
        $col  = self::column($column);
        $set  = ["$col = $col + ?", ...array_map(fn($c) => self::column($c) . ' = ?', array_keys($extra))];
        $sql  = 'UPDATE ' . $this->table . ' SET ' . implode(', ', $set) . $this->compileWheres();
        return Database::query($sql, [$amount, ...array_map([self::class, 'scalar'], array_values($extra)), ...$this->whereBindings()])->rowCount();
    }

    public function decrement(string $column, int|float $amount = 1, array $extra = []): int
    {
        return $this->increment($column, -$amount, $extra);
    }

    public function delete(): int
    {
        $this->guardWrite('delete');
        $sql = 'DELETE FROM ' . $this->table . $this->compileWheres() . $this->compileOrderLimit();
        return Database::query($sql, [...$this->whereBindings(), ...$this->orderBindings])->rowCount();
    }

    // ================================================================ SQL

    public function toSql(): string
    {
        $columns = $this->columns ? implode(', ', $this->columns) : '*';
        $sql = 'SELECT ' . ($this->distinct ? 'DISTINCT ' : '') . $columns . ' FROM ' . $this->table;
        if ($this->joins)   $sql .= ' ' . implode(' ', $this->joins);
        $sql .= $this->compileWheres();
        if ($this->groups)  $sql .= ' GROUP BY ' . implode(', ', $this->groups);
        if ($this->havings) $sql .= ' HAVING ' . implode(' AND ', array_column($this->havings, 'sql'));
        return $sql . $this->compileOrderLimit();
    }

    public function getBindings(): array
    {
        $having = [];
        foreach ($this->havings as $h) $having = [...$having, ...$h['bindings']];
        return [...$this->selectBindings, ...$this->whereBindings(), ...$having, ...$this->orderBindings];
    }

    // ================================================================ İÇ

    private function addWhere(string $boolean, string $sql, array $bindings): self
    {
        $boolean = strtoupper($boolean);
        if (!in_array($boolean, ['AND', 'OR'], true)) throw new InvalidArgumentException("Geçersiz bağlaç: $boolean");
        $this->wheres[] = ['boolean' => $boolean, 'sql' => $sql, 'bindings' => $bindings];
        return $this;
    }

    private function nested(Closure $callback, string $boolean): self
    {
        $q = new self($this->tableName);
        $callback($q);
        if (!$q->wheres) return $this; // boş grup eklenmez
        return $this->addWhere($boolean, '(' . substr($q->compileWheres(), 7) . ')', $q->whereBindings());
    }

    private function compileWheres(): string
    {
        if (!$this->wheres) return '';
        $sql = '';
        foreach ($this->wheres as $i => $w) $sql .= ($i === 0 ? '' : " {$w['boolean']} ") . $w['sql'];
        return ' WHERE ' . $sql;
    }

    private function whereBindings(): array
    {
        $out = [];
        foreach ($this->wheres as $w) $out = [...$out, ...$w['bindings']];
        return $out;
    }

    private function compileOrderLimit(): string
    {
        $sql = '';
        if ($this->orders)          $sql .= ' ORDER BY ' . implode(', ', $this->orders);
        if ($this->limit !== null)  $sql .= ' LIMIT ' . $this->limit;
        if ($this->offset !== null) $sql .= ($this->limit === null ? ' LIMIT 18446744073709551615' : '') . ' OFFSET ' . $this->offset;
        return $sql;
    }

    private function aggregate(string $function, string $column)
    {
        $q = clone $this;
        $q->orders = $q->orderBindings = [];
        $q->limit = $q->offset = null;

        // GROUP BY / DISTINCT varken doğru sayım için alt sorgu
        if ($q->groups || $q->distinct) {
            $inner = $q->toSql();
            $expr  = $column === '*' ? '*' : self::column(self::alias($column));
            return Database::value("SELECT $function($expr) FROM ($inner) AS _agg", $q->getBindings());
        }

        $q->columns = ["$function(" . ($column === '*' ? '*' : self::column($column)) . ') AS _agg'];
        $q->selectBindings = [];
        return Database::value($q->toSql(), $q->getBindings());
    }

    private function guardWrite(string $action): void
    {
        if (!$this->wheres) {
            throw new LogicException("Koşulsuz $action engellendi (tüm tablo etkilenirdi). Bilerek yapıyorsan ->whereRaw('1 = 1') ekle.");
        }
        if ($this->joins) throw new LogicException("$action join içeren sorguda desteklenmiyor; DB::query() kullan.");
    }

    // ---- Ad doğrulama ve tırnaklama

    // 'posts' -> `posts`, 'posts as p' -> `posts` AS `p`
    private static function table(string $name): string
    {
        if (preg_match('/^\s*([A-Za-z0-9_]+)(?:\s+as\s+([A-Za-z0-9_]+))?\s*$/i', $name, $m)) {
            return "`{$m[1]}`" . (isset($m[2]) ? " AS `{$m[2]}`" : '');
        }
        throw new InvalidArgumentException("Geçersiz tablo adı: $name");
    }

    // 'title' -> `title`, 'p.title' -> `p`.`title`, 'p.*' -> `p`.*, 'u.name as author' -> `u`.`name` AS `author`
    private static function column(string $name): string
    {
        $name = trim($name);
        if ($name === '*') return '*';
        if (preg_match('/^([A-Za-z0-9_]+(?:\.(?:[A-Za-z0-9_]+|\*))?)(?:\s+as\s+([A-Za-z0-9_]+))?$/i', $name, $m)) {
            $parts = array_map(fn($p) => $p === '*' ? '*' : "`$p`", explode('.', $m[1]));
            return implode('.', $parts) . (isset($m[2]) ? " AS `{$m[2]}`" : '');
        }
        throw new InvalidArgumentException("Geçersiz kolon adı: $name (ifade için selectRaw/whereRaw kullan)");
    }

    // 'u.name as author' -> 'author', 'p.title' -> 'title' (sonuç dizisindeki anahtar)
    private static function alias(string $name): string
    {
        if (preg_match('/\s+as\s+([A-Za-z0-9_]+)\s*$/i', $name, $m)) return $m[1];
        $parts = explode('.', trim($name));
        return end($parts);
    }

    private static function operator(string $op): string
    {
        $op = strtoupper(trim($op));
        if (!in_array($op, self::OPERATORS, true)) throw new InvalidArgumentException("Geçersiz operatör: $op");
        return $op;
    }

    // Bağlanacak değer: bool -> 1/0, dizi/nesne -> hata (yanlışlıkla dizi gönderilmesin)
    private static function scalar($value)
    {
        if (is_bool($value)) return (int) $value;
        if (is_array($value) || is_object($value) && !method_exists($value, '__toString')) {
            throw new InvalidArgumentException('Sorgu değeri tek bir değer olmalı (dizi için whereIn kullan)');
        }
        return is_object($value) ? (string) $value : $value;
    }

    private static function escapeLike(string $value): string
    {
        return addcslashes($value, '\\%_');
    }
}
