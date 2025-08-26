<!DOCTYPE html>
<html lang="vi">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', 'Trang quản trị')</title>
  <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>

<body class="bg-gray-100">

  <div class="layout">
    <input type="checkbox" id="menu-toggle" class="menu-toggle-checkbox" hidden>
    <label for="menu-toggle" class="overlay-sidebar"></label>
    {{-- Sidebar --}}
    <aside class="sidebar">
      <h2 class="sidebar-title">MENU</h2>
      <label for="menu-toggle" class="menu-toggle-close">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
          stroke="currentColor" class="size-6">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
        </svg>
      </label>
      <ul>
        @foreach ($menus as $menu)
          <li>
            <a href="{{ $menu['url'] }}" class="{{ request()->url() == $menu['url'] ? 'active' : '' }}">
              {{ $menu['name'] }}
            </a>
          </li>
        @endforeach
      </ul>
    </aside>

    {{-- Main content --}}
    <main class="content">
      <div class="header">
        <label for="menu-toggle" class="menu-toggle">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
            stroke="currentColor" class="size-6">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
          </svg>
      </div>
      <div class="content-inner">
        @yield('content')
      </div>
    </main>
  </div>

</body>

</html>
