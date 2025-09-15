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
                'name' => 'Territory performance',
                'url' => '',
                'roles' => [1],
            ],
            [
                'name' => 'Detail by invoice',
                'url' => route('detailbyinvoice'),
                'roles' => [1, 2],
            ],
            [
                'name' => 'Territory Account',
                'url' => '',
                'roles' => [1],
            ],
            [
                'name' => 'Settings',
                'url' => route('settings'),
                'roles' => [1],
            ]

        ];

        // Lọc menu theo role
        $filtered = array_filter($menus, fn($m) => in_array($role, $m['roles']));

        $view->with('menus', $filtered);
    }
}
