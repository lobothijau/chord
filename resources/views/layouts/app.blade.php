<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Chords')</title>
    <link rel="stylesheet" href="@assetv('css/app.css')">
</head>
<body @yield('body-attrs')>
    <header class="topbar">
        <a href="{{ route('songs.index') }}" class="brand">&#119070; Chords</a>
        <nav>
            <a href="{{ route('songs.create') }}" class="btn btn-primary">+ Add song</a>
        </nav>
    </header>

    <main class="container">
        @yield('content')
    </main>

    @stack('scripts')
</body>
</html>
