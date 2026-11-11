<?php
namespace App\Controllers\Admin;

use App\Models\User;
use Core\Controller;

// Admin controller'larının temel sınıfı: admin layout'u ve giriş yapmış admin.
//   class PostController extends AdminController { ... $this->admin()['name'] ... }
abstract class AdminController extends Controller
{
    protected ?string $layout = 'admin';

    protected function admin(): ?array
    {
        return User::admin();
    }
}
