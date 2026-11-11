# Veritabanı

## Sorgu oluşturucu

```php
DB::table('orders')->where('status', 'paid')->latest()->limit(10)->get();
DB::table('orders')->where('total', '>', 100)->orWhere('vip', 1)->first();
DB::table('orders')->whereIn('id', [1, 2, 3])->pluck('code', 'id');                  // [1 => 'A1', ...]
DB::table('orders')->where(fn($q) => $q->where('a', 1)->orWhere('b', 2))->count();    // (a = ? OR b = ?)
DB::table('orders')->search(['code', 'name'], $q)->paginate(20);                       // ?page= ile sayfalı
DB::table('orders as o')->join('users as u', 'u.id', '=', 'o.user_id')->select('o.*', 'u.name as customer')->get();
DB::table('orders')->select('status')->selectRaw('COUNT(*) AS total')->groupBy('status')->get();
DB::table('orders')->when($status, fn($q) => $q->where('status', $status))->get();

DB::table('orders')->insertGetId(['code' => 'A1']);
DB::table('orders')->where('id', 5)->update(['status' => 'paid']);
DB::table('orders')->where('id', 5)->increment('views');
DB::table('orders')->where('id', 5)->delete();
DB::table('orders')->chunk(500, fn(array $rows) => ...);                               // büyük tablolarda parça parça
DB::transaction(function () { ... });                                                  // hata olursa geri alınır
```

Diğer metotlar: `whereNot`, `whereNull`, `whereNotNull`, `whereBetween`, `whereDate`, `whereLike`, `whereRaw` (bağlamlı),
`having`, `distinct`, `leftJoin`, `orderBy`, `orderByDesc`, `oldest`, `inRandomOrder`, `offset`, `forPage`, `value`, `exists`,
`sum`, `avg`, `min`, `max`, `decrement`, `toSql()`, `getBindings()`.

**Güvenlik:** değerler her zaman bağlanır (prepared statement); kolon/tablo adları doğrulanıp tırnaklanır, operatörler beyaz
listeden. **Koşulsuz `update`/`delete` reddedilir** (yanlışlıkla tüm tabloyu silmemek için); bilerek: `->whereRaw('1 = 1')`.

`paginate()` dönüşü: `['data' => [...], 'total', 'page', 'per_page', 'pages', 'from', 'to']` — görünümde
`View::render('partials/pagination', ['page' => $sonuc])`.

Ham SQL gerekirse: `DB::all($sql, $params)`, `DB::first(...)`, `DB::value(...)`, `DB::query(...)`.

## Model

```php
namespace App\Modules\Siparis\Models;

use Core\Model;

class Order extends Model
{
    protected static string $table = 'orders';
    protected static array $fillable = ['code', 'name', 'email', 'total', 'status'];   // create/update'te sadece bunlar yazılır
    // protected static bool $timestamps = false;                                   // created_at / updated_at istemiyorsanız

    public static function paid(): array
    {
        return static::query()->where('status', 'paid')->latest()->get();
    }
}
```

```php
Order::find(5);   Order::all('id desc');   Order::where(['status' => 'paid'], 'id desc', 10);
Order::first(['code' => 'A1']);   Order::count(['status' => 'paid']);
Order::paginate(20, ['status' => 'paid'], 'created_at desc');
$id = Order::create($veri);   Order::update($id, $veri);   Order::delete($id);
Order::query()->...                                                 // sorgu oluşturucunun tamamı
```

## Migration

Tablolar düz `.sql` dosyalarıyla kurulur; dosyalar adlarına (tarih) göre sırayla bir kez çalışır, çalışanlar
`schema_migrations` tablosuna yazılır.

- Çekirdek: `app/Database/Migrations/`
- Modül: `app/Modules/{Ad}/Migrations/` — her `.sql` için bir **`.down.sql`** (modül kaldırılınca çalışır)

```bash
php modrix make:migration create_orders_table Siparis
```

```sql
-- 2026_11_12_101500_create_orders_table.sql
CREATE TABLE orders (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code       VARCHAR(20) NOT NULL UNIQUE,
    name       VARCHAR(100) NOT NULL,
    total      DECIMAL(10,2) NOT NULL DEFAULT 0,
    status     ENUM('new','paid','cancelled') NOT NULL DEFAULT 'new',
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    INDEX (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

```sql
-- 2026_11_12_101500_create_orders_table.down.sql
DROP TABLE IF EXISTS orders;
```

**Kurallar**
- Çalışmış bir migration'ı **değiştirmeyin**; yeni kolon için yeni migration yazın (`ALTER TABLE ...`).
- Modül tabloları modülün adıyla başlasın (`siparis_orders` gibi) ya da çakışmayacağından emin olun.
- Tek dosyada birden çok komut olabilir (`;` ile).
