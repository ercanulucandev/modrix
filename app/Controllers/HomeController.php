<?php
namespace App\Controllers;

use Core\Controller;
use Core\Hook;
use Core\View;

class HomeController extends Controller
{
    public function index(): View
    {
        // Ana sayfa yerine başka bir içerik gösterilebilir (ör. Sayfalar modülü: Ayarlar'da seçilen sayfa)
        $front = Hook::filter('site.front_page', null);
        if ($front instanceof View) return $front;

        return $this->view('home', [
            'title'    => null, // ana sayfa başlığı: sadece site adı (Ayarlar > Genel)
            // Modüller ana sayfaya bölüm ekler: Hook::on('site.home', fn($s) => [...$s, ['order' => 10, 'html' => '...']])
            'sections' => self::sorted(Hook::filter('site.home', [])),
        ]);
    }

    private static function sorted(array $items): array
    {
        usort($items, fn($a, $b) => ($a['order'] ?? 50) <=> ($b['order'] ?? 50));
        return $items;
    }
}
