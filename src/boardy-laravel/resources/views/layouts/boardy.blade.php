<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>@yield('title', 'Boardy')</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold" href="{{ route('posts.index') }}">
            Boardy
        </a>

        <div class="navbar-nav me-auto">
            <a class="nav-link" href="{{ route('posts.index') }}">
                Все посты
            </a>

            @auth
                <a class="nav-link" href="{{ route('posts.create') }}">
                    Добавить пост
                </a>

                <button
                    type="button"
                    class="btn btn-sm btn-outline-info ms-2"
                    onclick="BoardyAuth.startLogin()"
                >
                    OAuth API login
                </button>
            @endauth
        </div>

        <div class="navbar-nav ms-auto">
            @auth
                <span class="navbar-text me-3">
                    Привет, {{ Auth::user()->name }}!
                </span>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <button class="btn btn-outline-light btn-sm" type="submit">
                        Выйти
                    </button>
                </form>
            @else
                <a class="nav-link" href="{{ route('login') }}">
                    Вход
                </a>

                <a class="nav-link" href="{{ route('register') }}">
                    Регистрация
                </a>

                <button
                    type="button"
                    class="btn btn-sm btn-outline-info ms-2"
                    onclick="BoardyAuth.startLogin()"
                >
                    OAuth API login
                </button>
            @endauth
        </div>
    </div>
</nav>

<main class="container mb-5">
    @if (session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>Проверьте форму:</strong>

            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>
                        {{ $error }}
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @yield('content')
</main>

<script type="module" src="/js/pkce.js"></script>
<script type="module" src="/js/auth.js"></script>
</body>
</html>
