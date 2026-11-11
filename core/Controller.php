<?php
namespace Core;

// Controller'ların temel sınıfı.
//
//   class PostController extends Controller
//   {
//       protected ?string $layout = 'site';                          // bu controller'ın layout'u
//
//       public function index(): View
//       {
//           return $this->view('Blog::index', ['posts' => Post::all()]);
//       }
//
//       public function store(): void
//       {
//           $data = $this->validate(['title' => 'required|max:200'], ['title' => 'Başlık']);
//           Post::create($data);
//           $this->redirect('blog', 'Yazı eklendi.');
//       }
//   }
abstract class Controller
{
    protected ?string $layout = 'site';

    protected function view(string $name, array $data = []): View
    {
        return View::render($name, $data)->setLayout($this->layout);
    }

    // Yönlendirir; mesaj verilirse bir sonraki sayfada gösterilir
    protected function redirect(string $path, ?string $message = null, string $type = 'ok'): never
    {
        if ($message !== null) Session::flash($type, $message);
        Response::redirect($path);
    }

    protected function back(?string $message = null, string $type = 'ok'): never
    {
        if ($message !== null) Session::flash($type, $message);
        Response::back();
    }

    protected function json(array $data, int $status = 200): string
    {
        return Response::json($data, $status);
    }

    // Hata varsa formu geri döndürür (hatalar + eski değerlerle), yoksa temiz veriyi verir
    protected function validate(array $rules, array $labels = []): array
    {
        return Validator::validate($rules, $labels);
    }

    protected function abort(int $status, string $message = ''): never
    {
        Response::abort($status, $message);
    }
}
