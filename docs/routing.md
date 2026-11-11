# Rotalar, controller'lar, istek ve yanıt

## Rotalar

Dosyalar: `app/Routes/web.php` (site), `app/Routes/admin.php` (`/admin` önekiyle), `app/Routes/api.php` (`/api` önekiyle).
Modüller kendi `routes.php` (site + admin) ve `api.php` dosyalarını kullanır; sadece **aktif** modüllerinki yüklenir.

```php
Router::get('/', [HomeController::class, 'index'])->name('home');
Router::get('/blog/{slug}', [PostController::class, 'show'])->name('blog.show');     // {slug}: tek parça
Router::get('/media/{path*}', [MediaController::class, 'show']);                     // {path*}: eğik çizgi içerebilir
Router::post('/forms/{slug}', [SubmitController::class, 'store']);
Router::put('/api/x/{id}', ...);   Router::delete(...);   Router::any(...);
Router::get('/ping', fn() => ['ok' => true]);                                        // dizi dönerse JSON

Router::group(['prefix' => '/admin/siparis', 'middleware' => ['admin']], function () {
    Router::get('/', [SiparisController::class, 'index'])->name('admin.siparis.index');
});
```

- **Adres üretme:** `Router::url('blog.show', ['slug' => 'merhaba'])` → `/blog/merhaba` (site alt klasördeyse doğru önekle).
  Sabit adres için `Request::url('admin/pages')`.
- **Gruplar:** `web` grubu (site, admin ve modül rotaları) **CSRF korumalıdır**; `api` grubu değildir.
- **Eşleşmeyen adresler:** `Router::fallback(fn(string $path) => ...)` — hiçbir rota eşleşmezse sırayla denenir (sadece GET),
  `null` dönerse 404. Sayfalar modülü `/hakkimizda` gibi adresleri böyle yakalar; tanımlı rotaları asla ezmez.
- **404 sayfası:** `Router::notFound(fn() => View::render('errors/404')->setLayout('site'))` (web.php'de tanımlı).

## Controller

```php
namespace App\Modules\Siparis\Controllers;

use Core\Controller;
use Core\View;

class SiparisController extends Controller
{
    protected ?string $layout = 'site';   // varsayılan; admin controller'ları AdminController'dan türer ('admin')

    public function index(): View
    {
        return $this->view('Siparis::index', ['title' => 'Siparişler', 'orders' => Order::all('id desc')]);
    }

    public function store(): void
    {
        $data = $this->validate(['name' => 'required|max:100', 'email' => 'required|email'], ['name' => 'Ad', 'email' => 'E-posta']);
        Order::create($data);
        $this->redirect('siparis', 'Siparişiniz alındı.');   // mesaj sonraki sayfada flash olarak görünür
    }
}
```

| Metot | Ne yapar |
|---|---|
| `$this->view('Modul::dosya', $veri)` | Görünümü controller'ın layout'u ile döndürür |
| `$this->validate($kurallar, $etiketler)` | Hata varsa forma geri döner (hatalar + eski değerler) ya da AJAX'ta 422 JSON; yoksa temiz veri |
| `$this->redirect('yol', 'mesaj', 'ok'\|'error')` | Yönlendirir |
| `$this->back('mesaj')` | Geldiği sayfaya döner |
| `$this->json($dizi, 201)` | JSON yanıt (ya da metottan dizi döndürün) |
| `$this->abort(404, 'Bulunamadı')` | Hata sayfası / AJAX'ta JSON hata |
| `$this->admin()` | (AdminController) giriş yapmış admin |

**Admin sayfaları** `App\Controllers\Admin\AdminController`'dan türer ve rotada `admin` middleware'i kullanır;
yetki için `can:` ekleyin: `->middleware('admin', 'can:siparis.manage')`. Bkz. [Kullanıcılar ve roller](users.md).

## Doğrulama kuralları

`required`, `nullable`, `email`, `url`, `integer`, `numeric`, `min:n`, `max:n`, `between:a,b`, `in:a,b,c`, `confirmed`,
`same:alan`, `date`, `phone`, `iban`, `regex:/.../`, `unique:tablo,kolon[,haricId]`, `exists:tablo,kolon`.
Uzunluk kuralları metinde karakter, sayıda değer sayar. Elle hata: `Validator::fail(['alan' => 'mesaj'])`.

## İstek (Request)

```php
Request::get('page', 1)          // ?page=
Request::post('title')           // form ya da JSON gövdesi
Request::all()   Request::only('a', 'b')   Request::has('x')
Request::method()   Request::path()   Request::ip()   Request::isAjax()
Request::url('blog')             // tam adres (alt klasörü hesaba katar)
```

## Yanıt (Response)

```php
Response::redirect('admin/pages');                 // ya da tam adres
Response::back();
return Response::json(['ok' => true], 201);
Response::download(Upload::path($file['path']), 'fatura.pdf');
Response::abort(403, 'Yetkiniz yok');
```

## Oturum (Session)

```php
Session::get('key')   Session::set('key', $v)   Session::has('key')   Session::forget('key')
Session::flash('ok', 'Kaydedildi')     // bir sonraki istekte Session::flash('ok') ile okunur (partials/flash otomatik gösterir)
Session::token()                       // CSRF anahtarı (formlarda View::csrf())
```

## Middleware

```php
namespace App\Modules\Siparis\Middlewares;

use Core\Middleware;

class OnlyWeekdays extends Middleware
{
    public function handle(): void
    {
        if (date('N') > 5) Response::abort(403, 'Hafta sonu kapalıyız.');
    }
}
```

Kullanım: rotada sınıf adıyla `->middleware(OnlyWeekdays::class)` ya da `app/Config/app.php` → `middleware` listesine kısa adla
ekleyip `->middleware('weekdays')`. Parametre: `->middleware('role:admin,editor')` → `$this->params === ['admin', 'editor']`.
Her istekte çalışanlar: `global_middleware` (ör. bakım modu).

## Hatalar

`HttpException` fırlatın (`throw new HttpException(404)`) ya da `$this->abort()`. Hata yakalayıcı:
debug açıkken ayrıntılı sayfa, kapalıyken sade sayfa + log'da aranabilen hata kodu; AJAX isteklerinde JSON
(`{ "error": "...", "status": 422, "errors": {...} }`). Loglar: `Log::info('mesaj', ['bağlam' => 1])`, `Log::warning`, `Log::error`.
