<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Card;
use App\Models\BotCollection;
use Illuminate\Support\Facades\Cache;

new #[Layout('layouts.app')] class extends Component
{
    public function with(): array
    {
        $collections = Cache::remember('bot_collections_array', 3600, function() {
            return BotCollection::all()->keyBy('collectionID')->toArray();
        });

        $candidates = Card::whereNotNull('eval')
                          ->orderBy('eval', 'desc')
                          ->limit(200)
                          ->get();

        $scoredCards = $candidates->map(function ($card) {
            $evalScore = ($card->eval ?? 0) * 0.1;
            $ratingScore = ($card->timesRated > 0) ? (($card->ratingSum / $card->timesRated) * 50) : 0;
            $votesScore = ($card->timesRated ?? 0) * 5;
            $ownerScore = ($card->ownerCount ?? 0) * 2;
            $auctionScore = ($card->stats['auctionCount'] ?? 0) * 10;
            $rng = rand(0, 100);

            $card->trendingScore = $evalScore + $ratingScore + $votesScore + $ownerScore + $auctionScore + $rng;
            return $card;
        });

        $trendingCards = $scoredCards->sortByDesc('trendingScore')->take(12)->values();

        return [
            'trendingCards' => $trendingCards,
            'collections' => $collections,
        ];
    }
};
?>

<div>
    <div class="glass-panel" style="padding: 3rem; text-align: center; margin-bottom: 2rem;">
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
    </div>

    @if(!auth()->check())
    <div style="text-align: left;">
        <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 1.5rem; border-bottom: 1px solid var(--glass-border); padding-bottom: 0.5rem;">
            <h2 style="font-size: 1.8rem; margin: 0;">🔥 Trending Cards</h2>
            <p style="color: var(--text-secondary); margin: 0; font-size: 0.9rem;">Calculated dynamically from activity & eval</p>
        </div>
        
        <x-card-grid :cards="$trendingCards" :collections="$collections" />
    </div>
    @endif
</div>