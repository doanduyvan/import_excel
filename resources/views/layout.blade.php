<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Trang quản trị')</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="bg-gray-100">

   <div class="layout">
        {{-- Sidebar --}}
        <aside class="sidebar">
            <h2>MENU</h2>
            <ul>
                @foreach ($menus as $menu)
                    <li>
                        <a href="{{ $menu['url'] }}"
                        class="{{ request()->url() == $menu['url'] ? 'active' : '' }}">
                            {{ $menu['name'] }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </aside>

        {{-- Main content --}}
        <main class="content">
            @yield('content')
        </main>
    </div>

</body>
</html>
