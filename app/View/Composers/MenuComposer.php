<?php
namespace App\View\Composers;

use Illuminate\View\View;
use Illuminate\Support\Facades\Session;

class MenuComposer
{
    public function compose(View $view)
    {
        // $role = Session::get('user_role'); // ví dụ 'admin' hoặc 'staff'
        $role = 1;

        $menus = [
            [
                'name' => 'Dashboard',
                'url' => route('dashboard'),
                'roles' => [1],
            ],
            [
                'name' => 'Import Excel',
                'url' => '',
                'roles' => [1],
            ],
            [
                'name' => 'Đơn hàng',
                'url' => '',
                'roles' => [1, 2],
            ],
            [
                'name' => 'Người dùng',
                'url' => '',
                'roles' => [1],
            ],
        ];

        // Lọc menu theo role
        $filtered = array_filter($menus, fn($m) => in_array($role, $m['roles']));

        $view->with('menus', $filtered);
    }
}
