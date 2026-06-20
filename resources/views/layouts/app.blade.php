<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-ড়ান্ত">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>{{ config('app.name', 'Amusement Dashboard') }}</title>

        <!-- Link to our base CSS -->
        <link rel="stylesheet" href="{{ asset('css/app.css') }}">
        <link rel="icon" type="image/x-icon" href="https://amu.cards/favicon.ico">
        <script src="https://unpkg.com/@phosphor-icons/web"></script>
    </head>
    <body class="antialiased">
        
        <!-- Navigation -->
        <nav class="navbar animate-fade-in">
            <div class="nav-brand" style="display: flex; align-items: center; gap: 0.8rem;">
                <img src="https://amu.cards/favicon.ico" alt="Amusement Club" style="width: 28px; height: 28px; filter: drop-shadow(0 0 8px rgba(255,255,255,0.2));">
                Amusement Club
            </div>
            <div class="nav-links">
                <a href="/" class="nav-link"><i class="ph-bold ph-house" style="font-size: 1.1rem; color: #a855f7;"></i> Home</a>
                <a href="{{ route('cards.index') }}" class="nav-link"><i class="ph-bold ph-cards" style="font-size: 1.1rem; color: #60a5fa;"></i> All Cards</a>
                <a href="{{ route('collections.index') }}" class="nav-link"><i class="ph-bold ph-books" style="font-size: 1.1rem; color: #34d399;"></i> Collections</a>
                <a href="{{ route('auctions.index') }}" class="nav-link"><i class="ph-bold ph-gavel" style="font-size: 1.1rem; color: #fbbf24;"></i> Auctions</a>
                @auth
                    <a href="{{ route('heroes.index') }}" class="nav-link"><i class="ph-bold ph-mask-happy" style="font-size: 1.1rem; color: #ec4899;"></i> Heroes</a>
                    @php
                        $user = auth()->user();
                        $avatarIndex = is_numeric($user->userID) ? (substr($user->userID, -1) % 6) : 0;
                        $defaultAvatar = "https://cdn.discordapp.com/embed/avatars/{$avatarIndex}.png";
                        $avatarUrl = \Illuminate\Support\Facades\Cache::remember('discord_avatar_' . $user->userID, 86400, function() use ($user, $defaultAvatar) {
                            $botToken = env('DISCORD_BOT_TOKEN');
                            if (!$botToken) return $defaultAvatar;
                            $response = \Illuminate\Support\Facades\Http::withHeaders(['Authorization' => "Bot {$botToken}"])->get("https://discord.com/api/users/{$user->userID}");
                            if ($response->successful() && !empty($response->json('avatar'))) {
                                $hash = $response->json('avatar');
                                $ext = str_starts_with($hash, 'a_') ? 'gif' : 'png';
                                return "https://cdn.discordapp.com/avatars/{$user->userID}/{$hash}.{$ext}?size=256";
                            }
                            return $defaultAvatar;
                        });
                        $incomingTx = \Illuminate\Support\Facades\DB::connection('mongodb')->table('transactions')->where('toID', $user->userID)->where('status', 'pending')->count();
                    @endphp
                    <div x-data="{ open: false }" style="position: relative;">
                        <button @click="open = !open" @click.away="open = false" style="background: transparent; border: none; cursor: pointer; display: flex; align-items: center; gap: 0.8rem; padding: 0.3rem 0.8rem; border-radius: 20px; transition: background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.1)'" onmouseout="this.style.background='transparent'">
                            <img src="{{ $avatarUrl }}" alt="Avatar" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover; border: 2px solid var(--accent-solid);">
                            <span style="color: white; font-weight: bold; font-family: inherit;">{{ $user->username }}</span>
                            <i class="ph-bold ph-caret-down" style="color: var(--text-secondary); transition: transform 0.2s;" :style="{ transform: open ? 'rotate(180deg)' : 'none' }"></i>
                        </button>
                        
                        <div x-show="open" x-transition.opacity.duration.200ms style="display: none; position: absolute; top: 100%; right: 0; margin-top: 0.5rem; background: rgba(20, 20, 30, 0.95); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); min-width: 220px; z-index: 1000; overflow: hidden; padding: 0.5rem;">
                            
                            <a href="{{ route('profile.show') }}" style="display: flex; align-items: center; gap: 0.8rem; padding: 0.8rem 1rem; color: white; text-decoration: none; border-radius: 8px; transition: background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.1)'" onmouseout="this.style.background='transparent'">
                                <i class="ph-fill ph-user" style="color: var(--accent-solid); font-size: 1.2rem;"></i> Profile
                            </a>
                            
                            <a href="{{ route('cards.index') }}?owner={{ $user->userID }}" style="display: flex; align-items: center; gap: 0.8rem; padding: 0.8rem 1rem; color: white; text-decoration: none; border-radius: 8px; transition: background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.1)'" onmouseout="this.style.background='transparent'">
                                <i class="ph-fill ph-cards" style="color: #60a5fa; font-size: 1.2rem;"></i> My Cards
                            </a>
                            
                            <a href="#" style="display: flex; align-items: center; justify-content: space-between; padding: 0.8rem 1rem; color: white; text-decoration: none; border-radius: 8px; transition: background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.1)'" onmouseout="this.style.background='transparent'">
                                <div style="display: flex; align-items: center; gap: 0.8rem;">
                                    <i class="ph-fill ph-arrows-left-right" style="color: #34d399; font-size: 1.2rem;"></i> Transactions
                                </div>
                                @if($incomingTx > 0)
                                    <span style="background: rgba(16, 185, 129, 0.2); color: #34d399; font-size: 0.7rem; padding: 2px 6px; border-radius: 12px; font-weight: bold;">{{ $incomingTx }}</span>
                                @endif
                            </a>
                            
                            <a href="{{ route('preferences.index') }}" style="display: flex; align-items: center; gap: 0.8rem; padding: 0.8rem 1rem; color: white; text-decoration: none; border-radius: 8px; transition: background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.1)'" onmouseout="this.style.background='transparent'">
                                <i class="ph-fill ph-gear" style="color: #a855f7; font-size: 1.2rem;"></i> Preferences
                            </a>
                            
                            <div style="height: 1px; background: rgba(255,255,255,0.1); margin: 0.5rem 0;"></div>
                            
                            <form action="{{ route('logout') }}" method="POST" style="margin: 0;">
                                @csrf
                                <button type="submit" style="display: flex; align-items: center; gap: 0.8rem; padding: 0.8rem 1rem; color: #ef4444; background: transparent; border: none; width: 100%; text-align: left; border-radius: 8px; cursor: pointer; transition: background 0.2s; font-size: 1rem; font-family: inherit;" onmouseover="this.style.background='rgba(239, 68, 68, 0.1)'" onmouseout="this.style.background='transparent'">
                                    <i class="ph-bold ph-sign-out" style="font-size: 1.2rem;"></i> Logout
                                </button>
                            </form>
                        </div>
                    </div>
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
        
        <livewire:global-actions />
        
        <!-- Global Notification Toast -->
        <div x-data="{ show: false, message: '' }" 
             @notify.window="message = $event.detail.message; show = true; setTimeout(() => show = false, 3000)"
             x-show="show" 
             x-transition.opacity.duration.300ms
             style="display: none; position: fixed; bottom: 2rem; right: 2rem; background: #10b981; color: white; padding: 1rem 2rem; border-radius: 8px; box-shadow: 0 10px 25px rgba(0,0,0,0.5); z-index: 9999; font-weight: bold; border: 1px solid rgba(255,255,255,0.2);">
            <i class="ph-bold ph-check-circle" style="margin-right: 0.5rem;"></i> <span x-text="message"></span>
        </div>
    </body>
</html>
