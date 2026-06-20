<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-ড়ান্ত">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>{{ config('app.name', 'Amusement Dashboard') }}</title>

        <!-- Link to our base CSS -->
        <link rel="stylesheet" href="{{ asset('css/app.css') }}">
        <script src="https://unpkg.com/@phosphor-icons/web"></script>
    </head>
    <body class="antialiased">
        
        <!-- Navigation -->
        <nav class="navbar animate-fade-in">
            <div class="nav-brand">Amusement Club</div>
            <div class="nav-links">
                <a href="/" class="nav-link">Home</a>
                <a href="{{ route('cards.index') }}" class="nav-link">All Cards</a>
                <a href="{{ route('auctions.index') }}" class="nav-link">Auctions</a>
                @auth
                    <a href="{{ route('cards.index') }}?owner={{ auth()->user()->userID }}" class="nav-link">My Cards</a>
                    <a href="{{ route('profile.show') }}" class="nav-link">Profile</a>
                    <form action="{{ route('logout') }}" method="POST" style="display:inline;">
                        @csrf
                        <button type="submit" class="btn btn-primary" style="background: rgba(239, 68, 68, 0.8);">Logout</button>
                    </form>
                @else
                    <a href="{{ route('login.discord') }}" class="btn btn-primary">Sign In with Discord</a>
                @endauth
            </div>
        </nav>

        <!-- Main Content Slot -->
        <main class="container animate-fade-in" style="animation-delay: 0.2s;">
            {{ $slot }}
        </main>
        
    </body>
</html>
