<?php
namespace App\Controllers;

use Core\Controller;
use Core\Hook;
use Core\Request;
use Core\View;

// Site içi arama: /search?q=...  Modüller sonuç ekler:
//   Hook::on('site.search', fn(array $r, string $q) => [...$r, ['title' => .., 'url' => .., 'excerpt' => .., 'type' => 'Ürün', 'date' => ..]]);
// Tema ezebilir: app/Themes/{Tema}/views/search.php
class SearchController extends Controller
{
    public function index(): View
    {
        $q = trim(mb_substr((string) Request::get('q', ''), 0, 100));
        $results = mb_strlen($q) >= 2 ? Hook::filter('site.search', [], $q) : [];
        // Başlıkta geçen önce, sonra tarihe göre
        usort($results, fn($a, $b) => [(int) (mb_stripos($b['title'], $q) !== false), $b['date'] ?? ''] <=> [(int) (mb_stripos($a['title'], $q) !== false), $a['date'] ?? '']);

        return $this->view('search', [
            'title'   => $q !== '' ? "\"$q\" için arama" : 'Arama',
            'q'       => $q,
            'results' => $results,
            'noindex' => true,
        ]);
    }
}
