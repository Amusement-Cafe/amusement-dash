<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')] class extends Component
{
    //
};
?>

<div class="glass-panel" style="padding: 3rem; text-align: center;">
    @auth
        <h1 style="font-size: 2.5rem; margin-bottom: 1rem;">Welcome back, <span style="color: var(--accent-solid);">{{ auth()->user()->username }}</span>!</h1>
        <p style="color: var(--text-secondary); font-size: 1.1rem; margin-bottom: 2rem;">
            You have {{ auth()->user()->tomatoes ?? 0 }} 🍅 Tomatoes and {{ auth()->user()->xp ?? 0 }} XP.
        </p>
    @else
        <h1 style="font-size: 2.5rem; margin-bottom: 1rem;">Welcome to Amusement Club</h1>
        <p style="color: var(--text-secondary); font-size: 1.1rem; margin-bottom: 2rem;">
            Sign in with Discord to view your card collection, track your stats, and explore the global marketplace.
        </p>
        <a href="{{ route('login.discord') }}" class="btn btn-primary" style="font-size: 1.1rem; padding: 0.8rem 2rem;">
            Get Started
        </a>
    @endauth

    <div style="margin-top: 4rem; text-align: left;">
        <h2 style="font-size: 1.8rem; margin-bottom: 1.5rem; border-bottom: 1px solid var(--glass-border); padding-bottom: 0.5rem;">Trending Cards</h2>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1.5rem;">
            <!-- Placeholder for trending cards -->
            <div class="glass-panel" style="padding: 1rem; text-align: center; border: 1px dashed var(--accent-glow);">
                <div style="height: 250px; background: rgba(0,0,0,0.2); border-radius: 8px; margin-bottom: 1rem; display: flex; align-items: center; justify-content: center; color: var(--text-secondary);">
                    [Card Image]
                </div>
                <h3 style="font-size: 1.1rem;">Example Card</h3>
                <p style="color: var(--text-secondary); font-size: 0.9rem;">⭐⭐⭐⭐</p>
            </div>
            
            <div class="glass-panel" style="padding: 1rem; text-align: center; border: 1px dashed var(--accent-glow);">
                <div style="height: 250px; background: rgba(0,0,0,0.2); border-radius: 8px; margin-bottom: 1rem; display: flex; align-items: center; justify-content: center; color: var(--text-secondary);">
                    [Card Image]
                </div>
                <h3 style="font-size: 1.1rem;">Another Card</h3>
                <p style="color: var(--text-secondary); font-size: 0.9rem;">⭐⭐⭐⭐⭐</p>
            </div>
        </div>
    </div>
</div>