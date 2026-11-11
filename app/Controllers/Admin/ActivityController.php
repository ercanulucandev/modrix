<?php
namespace App\Controllers\Admin;

use App\Models\User;
use App\Services\Activity;
use Core\Database;
use Core\View;

// Admin > Kullanıcılar > İşlem kaydı
class ActivityController extends AdminController
{
    public function index(): View
    {
        $user = (int) Request::get('user', 0);
        $group = (string) Request::get('group', '');
        $q = trim((string) Request::get('q', ''));

        $rows = Database::table('activity_log')
            ->when($user, fn($query) => $query->where('user_id', $user))
            ->when(isset(Activity::GROUPS[$group]), fn($query) => $query->whereRaw('action LIKE ?', [$group . '.%']))
            ->search(['description', 'ip'], $q)
            ->orderByDesc('id')
            ->paginate(50);

        return $this->view('admin/activity/index', [
            'title'  => 'İşlem kaydı',
            'rows'   => $rows,
            'users'  => array_column(User::query()->select('id', 'name')->orderBy('name')->get(), 'name', 'id'),
            'groups' => Activity::GROUPS,
            'filter' => ['user' => $user, 'group' => $group, 'q' => $q],
        ]);
    }
}
