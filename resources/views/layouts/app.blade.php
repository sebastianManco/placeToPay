<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    <link rel="dns-prefetch" href="//fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css?family=Nunito" rel="stylesheet">

    <!-- Scripts & Styles -->
    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
</head>
<body>
    <div id="app">
        <nav class="navbar navbar-expand-md navbar-light bg-white shadow-sm">
            <div class="container">
                <a class="navbar-brand" href="{{ url('/') }}">
                    {{ config('app.name', 'Laravel') }}
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="{{ __('Toggle navigation') }}">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <!-- Left Side Of Navbar -->
                    <ul class="navbar-nav me-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('showcase.index') }}">{{ __('Vitrina de Productos') }}</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link d-flex align-items-center" href="{{ route('cart.index') }}">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-cart3 me-1" viewBox="0 0 16 16">
                                    <path d="M0 1.5A.5.5 0 0 1 .5 1H2a.5.5 0 0 1 .485.379L2.89 3H14.5a.5.5 0 0 1 .49.598l-1 5a.5.5 0 0 1-.465.401l-9.397.472L4.415 11H13a.5.5 0 0 1 0 1H4a.5.5 0 0 1-.491-.408L2.01 3.607 1.61 2H.5a.5.5 0 0 1-.5-.5zM3.102 4l.84 4.479 9.144-.459L13.89 4H3.102zM5 12a2 2 0 1 0 0 4 2 2 0 0 0 0-4zm7 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4zm-7 1a1 1 0 1 1 0 2 1 1 0 0 1 0-2zm7 0a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/>
                                </svg>
                                {{ __('Carrito') }}
                                @php
                                    $navCartService = app(\App\Services\CartService::class);
                                    $navCart = $navCartService->getCart(Auth::user(), session()->getId());
                                    $navCartCount = $navCart ? $navCart->getTotalQuantity() : 0;
                                @endphp
                                @if ($navCartCount > 0)
                                    <span class="badge rounded-pill bg-primary ms-1">{{ $navCartCount }}</span>
                                @endif
                            </a>
                        </li>
                        @auth
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('orders.index') }}">{{ __('Mis Pedidos') }}</a>
                            </li>
                            @if (Auth::user()->isAdmin() || Auth::user()->can('orders.view'))
                                <li class="nav-item">
                                    <a class="nav-link" href="{{ route('admin.orders.index') }}">{{ __('Admin Pedidos') }}</a>
                                </li>
                            @endif
                            @if (Auth::user()->isAdmin() || Auth::user()->can('clients.view'))
                                <li class="nav-item">
                                    <a class="nav-link" href="{{ route('admin.clients.index') }}">{{ __('Admin Clientes') }}</a>
                                </li>
                            @endif
                            @if (Auth::user()->isAdmin() || Auth::user()->can('products.view'))
                                <li class="nav-item">
                                    <a class="nav-link" href="{{ route('admin.products.index') }}">{{ __('Admin Productos') }}</a>
                                </li>
                            @endif
                            @if (Auth::user()->isAdmin() || Auth::user()->can('categories.view'))
                                <li class="nav-item">
                                    <a class="nav-link" href="{{ route('admin.categories.index') }}">{{ __('Admin Categorías') }}</a>
                                </li>
                            @endif
                            @if (Auth::user()->isAdmin() || Auth::user()->can('reports.view'))
                                <li class="nav-item">
                                    <a class="nav-link" href="{{ route('admin.reports.index') }}">{{ __('Admin Reportes') }}</a>
                                </li>
                            @endif
                            @if (Auth::user()->isAdmin() || Auth::user()->can('roles.view'))
                                <li class="nav-item">
                                    <a class="nav-link" href="{{ route('admin.roles.index') }}">{{ __('Admin Roles (ACL)') }}</a>
                                </li>
                            @endif
                        @endauth
                    </ul>

                    <!-- Right Side Of Navbar -->
                    <ul class="navbar-nav ms-auto">
                        <!-- Authentication Links -->
                        @guest
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('login') }}">{{ __('Login') }}</a>
                            </li>
                            @if (Route::has('register'))
                                <li class="nav-item">
                                    <a class="nav-link" href="{{ route('register') }}">{{ __('Register') }}</a>
                                </li>
                            @endif
                        @else
                            <li class="nav-item dropdown">
                                <a id="navbarDropdown" class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" v-pre>
                                    {{ Auth::user()->name }}
                                    @if (Auth::user()->isAdmin())
                                        <span class="badge bg-secondary ms-1">Admin</span>
                                    @endif
                                    <span class="caret"></span>
                                </a>

                                <div class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                                    <a class="dropdown-item" href="{{ route('orders.index') }}">
                                        {{ __('Mis Pedidos') }}
                                    </a>
                                    @if (Auth::user()->isAdmin() || Auth::user()->hasAnyAdminPermission())
                                        <div class="dropdown-divider"></div>
                                        <h6 class="dropdown-header">{{ __('Administración') }}</h6>
                                        @if (Auth::user()->isAdmin() || Auth::user()->can('orders.view'))
                                            <a class="dropdown-item" href="{{ route('admin.orders.index') }}">
                                                {{ __('Gestión de Pedidos') }}
                                            </a>
                                        @endif
                                        @if (Auth::user()->isAdmin() || Auth::user()->can('clients.view'))
                                            <a class="dropdown-item" href="{{ route('admin.clients.index') }}">
                                                {{ __('Gestión de Clientes') }}
                                            </a>
                                        @endif
                                        @if (Auth::user()->isAdmin() || Auth::user()->can('products.view'))
                                            <a class="dropdown-item" href="{{ route('admin.products.index') }}">
                                                {{ __('Gestión de Productos') }}
                                            </a>
                                        @endif
                                        @if (Auth::user()->isAdmin() || Auth::user()->can('categories.view'))
                                            <a class="dropdown-item" href="{{ route('admin.categories.index') }}">
                                                {{ __('Gestión de Categorías') }}
                                            </a>
                                        @endif
                                        @if (Auth::user()->isAdmin() || Auth::user()->can('reports.view'))
                                            <a class="dropdown-item" href="{{ route('admin.reports.index') }}">
                                                {{ __('Reportes del Sistema') }}
                                            </a>
                                        @endif
                                        @if (Auth::user()->isAdmin() || Auth::user()->can('roles.view'))
                                            <a class="dropdown-item" href="{{ route('admin.roles.index') }}">
                                                {{ __('Control de Acceso (ACL)') }}
                                            </a>
                                        @endif
                                    @endif
                                    <div class="dropdown-divider"></div>

                                    <a class="dropdown-item" href="{{ route('logout') }}"
                                       onclick="event.preventDefault();
                                                     document.getElementById('logout-form').submit();">
                                        {{ __('Logout') }}
                                    </a>

                                    <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                                        @csrf
                                    </form>
                                </div>
                            </li>
                        @endguest
                    </ul>
                </div>
            </div>
        </nav>

        <main class="py-4">
            @yield('content')
        </main>
    </div>
</body>
</html>
