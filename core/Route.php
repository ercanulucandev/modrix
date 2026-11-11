<?php
namespace Core;

// Tek bir route. Router::get(...) bunu döner; zincirle middleware ve isim eklenir.
class Route
{
    public array $middleware = [];
    public ?string $name = null;
    private string $regex;

    public function __construct(public array $methods, public string $path, public $action)
    {
        // /blog/{slug}     -> #^/blog/(?<slug>[^/]+)$#     (tek parça)
        // /media/{path*}   -> #^/media/(?<path>.+)$#       (eğik çizgi içerebilir)
        $regex = preg_replace('#\{(\w+)\*\}#', '(?<$1>.+)', $path);
        $this->regex = '#^' . preg_replace('#\{(\w+)\}#', '(?<$1>[^/]+)', $regex) . '$#';
    }

    public function middleware(string ...$names): self
    {
        $this->middleware = array_values(array_unique([...$this->middleware, ...$names]));
        return $this;
    }

    public function name(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    // Eşleşirse parametreleri döner (['slug' => 'merhaba']), eşleşmezse null
    public function match(string $path): ?array
    {
        if (!preg_match($this->regex, $path, $m)) return null;
        return array_map('rawurldecode', array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY));
    }
}
