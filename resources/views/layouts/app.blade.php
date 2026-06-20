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

        <!-- Footer -->
        <footer style="width: 100%; border-top: 1px solid var(--glass-border); margin-top: 4rem; padding: 3rem 5%; background: rgba(0, 0, 0, 0.2); backdrop-filter: blur(10px);">
            <div style="display: flex; flex-direction: row; flex-wrap: wrap; justify-content: space-between; gap: 2rem; max-width: 1200px; margin: 0 auto;">
                
                <div style="display: flex; flex-direction: column; gap: 0.5rem; max-width: 300px;">
                    <a href="https://discord.com/" style="margin-bottom: 0.5rem;">
                        <img src="https://a.amu.cards/web/discord_logo_2021.svg" style="width: 136px;" alt="Discord">
                    </a>
                    <img src="https://a.amu.cards/web/amusement-cafe-smalltext.png" style="width: 136px; margin-bottom: 0.5rem;" alt="Amusement Cafe">
                    <span style="font-size: 0.9rem; color: var(--text-secondary);"><b>support@amu.cards</b></span>
                    <span style="font-size: 0.9rem; color: var(--text-secondary);"><b>Website <a href="https://twitter.com/madebynoxc" style="color: var(--accent-solid); text-decoration: none;">@madebynoxc</a></b></span>
                    <span style="font-size: 0.9rem; color: var(--text-secondary);"><b>Cinnabar art by <a href="https://www.artstation.com/elisandra" style="color: var(--accent-solid); text-decoration: none;">Alexandra Li</a></b></span>
                </div>

                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                    <span style="font-size: 1.5rem; font-weight: bold; color: white; margin-bottom: 0.5rem;">Get Started</span>
                    <a href="https://discord.com/api/oauth2/authorize?client_id=340988108222758934&amp;permissions=0&amp;scope=bot%20applications.commands" style="color: var(--text-secondary); text-decoration: none; transition: color 0.2s;" onmouseover="this.style.color='white'" onmouseout="this.style.color='var(--text-secondary)'">Invite</a>
                    <a href="https://docs.amu.cards/" style="color: var(--text-secondary); text-decoration: none; transition: color 0.2s;" onmouseover="this.style.color='white'" onmouseout="this.style.color='var(--text-secondary)'">Documentation</a>
                    <a href="https://discord.gg/kqgAvdX" style="color: var(--text-secondary); text-decoration: none; transition: color 0.2s;" onmouseover="this.style.color='white'" onmouseout="this.style.color='var(--text-secondary)'">Bot Discord</a>
                </div>

                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                    <span style="font-size: 1.5rem; font-weight: bold; color: white; margin-bottom: 0.5rem;">Links</span>
                    <a href="https://github.com/Amusement-Cafe/amusementclub2.0" style="color: var(--text-secondary); text-decoration: none; transition: color 0.2s;" onmouseover="this.style.color='white'" onmouseout="this.style.color='var(--text-secondary)'">GitHub</a>
                    <a href="https://github.com/Amusement-Cafe" style="color: var(--text-secondary); text-decoration: none; transition: color 0.2s;" onmouseover="this.style.color='white'" onmouseout="this.style.color='var(--text-secondary)'">Amusement Cafe</a>
                    <a href="https://github.com/NoxCaos/amusement-club" style="color: var(--text-secondary); text-decoration: none; transition: color 0.2s;" onmouseover="this.style.color='white'" onmouseout="this.style.color='var(--text-secondary)'">Legacy Bot</a>
                    <a href="https://ko-fi.com/amusement" style="color: var(--text-secondary); text-decoration: none; transition: color 0.2s;" onmouseover="this.style.color='white'" onmouseout="this.style.color='var(--text-secondary)'">Donate</a>
                </div>

                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                    <span style="font-size: 1.5rem; font-weight: bold; color: white; margin-bottom: 0.5rem;">Using</span>
                    <a href="https://laravel.com/" style="color: var(--text-secondary); text-decoration: none; transition: color 0.2s;" onmouseover="this.style.color='white'" onmouseout="this.style.color='var(--text-secondary)'">Laravel 11</a>
                    <a href="https://livewire.laravel.com/" style="color: var(--text-secondary); text-decoration: none; transition: color 0.2s;" onmouseover="this.style.color='white'" onmouseout="this.style.color='var(--text-secondary)'">Livewire 3</a>
                    <a href="https://www.mongodb.com/" style="color: var(--text-secondary); text-decoration: none; transition: color 0.2s;" onmouseover="this.style.color='white'" onmouseout="this.style.color='var(--text-secondary)'">MongoDB</a>
                    <a href="https://alpinejs.dev/" style="color: var(--text-secondary); text-decoration: none; transition: color 0.2s;" onmouseover="this.style.color='white'" onmouseout="this.style.color='var(--text-secondary)'">Alpine.js</a>
                    <a href="https://phosphoricons.com/" style="color: var(--text-secondary); text-decoration: none; transition: color 0.2s;" onmouseover="this.style.color='white'" onmouseout="this.style.color='var(--text-secondary)'">Phosphor Icons</a>
                </div>

            </div>
        </footer>
        
    </body>
</html>
