<?php
namespace Core;

// Modellerin temel sınıfı. Kayıtlar dizi olarak döner. Tüm sorgular QueryBuilder üzerinden gider.
//
//   class Post extends Model
//   {
//       protected static string $table = 'posts';
//       protected static array $fillable = ['title', 'slug', 'body'];
//   }
//
//   Post::all('created_at desc');             Post::find(5);
//   Post::where(['status' => 'published'], 'created_at desc', 10);
//   Post::first(['slug' => 'merhaba']);       Post::count(['status' => 'draft']);
//   Post::paginate(20, ['status' => 'published'], 'created_at desc');   // sayfalı
//   Post::create([...]);                      Post::update(5, [...]);       Post::delete(5);
//
// Daha karmaşık sorgular: Post::query()->where(...)->orWhere(...)->latest()->get()  (core/QueryBuilder.php)
// $orderBy: 'kolon' ya da 'kolon desc', virgülle birden çok: 'status asc, created_at desc'
abstract class Model
{
    protected static string $table = '';
    protected static string $primaryKey = 'id';
    protected static array $fillable = [];      // create/update'te sadece bu alanlar yazılır (boşsa hepsi)
    protected static bool $timestamps = true;   // created_at / updated_at otomatik

    public static function query(): QueryBuilder
    {
        return new QueryBuilder(static::$table);
    }

    public static function all(string $orderBy = ''): array
    {
        return self::ordered(static::query(), $orderBy)->get();
    }

    public static function find(int|string $id): ?array
    {
        return static::query()->find($id, static::$primaryKey);
    }

    public static function where(array $conditions, string $orderBy = '', ?int $limit = null): array
    {
        $q = self::ordered(static::query()->where($conditions), $orderBy);
        return ($limit ? $q->limit($limit) : $q)->get();
    }

    public static function first(array $conditions, string $orderBy = ''): ?array
    {
        return self::ordered(static::query()->where($conditions), $orderBy)->first();
    }

    public static function count(array $conditions = []): int
    {
        return static::query()->where($conditions)->count();
    }

    public static function paginate(int $perPage = 20, array $conditions = [], string $orderBy = '', ?int $page = null): array
    {
        return self::ordered(static::query()->where($conditions), $orderBy)->paginate($perPage, $page);
    }

    public static function create(array $data): int
    {
        $data = self::fill($data);
        if (static::$timestamps) $data += ['created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')];
        return static::query()->insertGetId($data);
    }

    public static function update(int|string $id, array $data): int
    {
        $data = self::fill($data);
        if (static::$timestamps) $data['updated_at'] = date('Y-m-d H:i:s');
        return static::query()->where(static::$primaryKey, $id)->update($data);
    }

    public static function delete(int|string $id): int
    {
        return static::query()->where(static::$primaryKey, $id)->delete();
    }

    private static function fill(array $data): array
    {
        return static::$fillable ? array_intersect_key($data, array_flip(static::$fillable)) : $data;
    }

    // 'status asc, created_at desc' -> ->orderBy('status', 'asc')->orderBy('created_at', 'desc')
    // Kolon adı ve yön QueryBuilder'da doğrulanır (SQL'e ham metin eklenmez).
    private static function ordered(QueryBuilder $q, string $orderBy): QueryBuilder
    {
        foreach (array_filter(array_map('trim', explode(',', $orderBy))) as $part) {
            $bits = preg_split('/\s+/', $part);
            if (count($bits) > 2) throw new \InvalidArgumentException("Geçersiz sıralama: $part (biçim: 'kolon' ya da 'kolon desc')");
            $q->orderBy($bits[0], $bits[1] ?? 'asc');
        }
        return $q;
    }
}
