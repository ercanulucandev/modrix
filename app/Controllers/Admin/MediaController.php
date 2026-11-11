<?php
namespace App\Controllers\Admin;

use Core\View;

// Admin > Medya: sayfa iskeleti. İçeriği app/Assets/admin/media-page.js API'den doldurur.
class MediaController extends AdminController
{
    public function index(): View
    {
        return $this->view('admin/media/index', ['title' => 'Medya']);
    }
}
