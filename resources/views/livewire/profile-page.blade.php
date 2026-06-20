<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use App\Models\User;
use App\Models\Card;
use App\Models\BotCollection;
use App\Models\UserWishlist;
use App\Models\UserCard;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    #[Url]
    public string $id = '';

    public function updatingPage()
    {
        $this->dispatch('scroll-to-wishlist');
    }

    public function with(): array
    {
        $userIdToFetch = $this->id;
        
        if (empty($userIdToFetch) && auth()->check()) {
            $userIdToFetch = auth()->user()->userID;
        }

        if (empty($userIdToFetch)) {
            return [
                'userProfile' => null,
                'favCard' => null,
                'favCollection' => null,
                'wishlistCards' => null,
                'wishlistCollections' => [],
                'userOwned' => [],
                'userFavs' => [],
                'userWishlists' => []
            ];
        }

        $userProfile = User::where('userID', $userIdToFetch)->first();
        
        $favCard = null;
        $favCollection = null;

        if ($userProfile && !empty($userProfile->preferences['profile']['card'])) {
            $favCard = Card::where('cardID', (int) $userProfile->preferences['profile']['card'])->first();
            if ($favCard) {
                $favCollection = BotCollection::where('collectionID', $favCard->collectionID)->first();
            }
        }

        $wishlistCardIDs = UserWishlist::where('userID', $userIdToFetch)->pluck('cardID')->toArray();
        $wishlistCardIDs = array_map('intval', $wishlistCardIDs);
        
        $wishlistCards = null;
        $wishlistCollections = [];
        $userOwned = [];
        $userFavs = [];
        $userWishlists = [];
        
        if (!empty($wishlistCardIDs)) {
            // Ensure pagination runs
            $wishlistCards = Card::whereIn('cardID', $wishlistCardIDs)->paginate(12);
            $collectionIDs = collect($wishlistCards->items())->pluck('collectionID')->unique()->toArray();
            $wishlistCollections = BotCollection::whereIn('collectionID', $collectionIDs)->get()->keyBy('collectionID')->toArray();
            
            if (auth()->check()) {
                $myCardIDs = collect($wishlistCards->items())->pluck('cardID');
                
                $userCards = UserCard::where('userID', auth()->user()->userID)
                    ->whereIn('cardID', $myCardIDs)
                    ->get();
                
                foreach ($userCards as $uc) {
                    $userOwned[$uc->cardID] = $uc->amount ?? 1;
                    if ($uc->fav) {
                        $userFavs[$uc->cardID] = true;
                    }
                }

                $stringIDs = $myCardIDs->map(function($id) { return (string) $id; })->toArray();
                $wishlists = \App\Models\UserWishlist::where('userID', auth()->user()->userID)
                    ->whereIn('cardID', $stringIDs)
                    ->get();
                foreach ($wishlists as $w) {
                    $userWishlists[(int)$w->cardID] = true;
                }
            }
        }

        return [
            'userProfile' => $userProfile,
            'favCard' => $favCard,
            'favCollection' => $favCollection,
            'wishlistCards' => $wishlistCards,
            'wishlistCollections' => $wishlistCollections,
            'userOwned' => $userOwned,
            'userFavs' => $userFavs,
            'userWishlists' => $userWishlists
        ];
    }
};
?>

<div>
    @if(!$userProfile)
        <div class="glass-panel" style="padding: 3rem; text-align: center; border-color: rgba(239, 68, 68, 0.3);">
            <div style="font-size: 3rem; margin-bottom: 1rem;">❌</div>
            <h2 style="margin-bottom: 0.5rem; color: #f87171;">User Not Found</h2>
            <p style="color: var(--text-secondary);">We couldn't find a profile for this ID.</p>
        </div>
    @else
        @php
            $color = $userProfile->preferences['profile']['color'] ?? '16756480';
            // Convert discord int color to hex
            $hexColor = '#' . str_pad(dechex($color), 6, "0", STR_PAD_LEFT);
            
            $xp = $userProfile->xp ?? 0;
            $level = floor(sqrt($xp * 2));
            
            $levelColor = match(true) {
                $level >= 100 => '#fbbf24', // Gold
                $level >= 50 => '#a855f7', // Purple
                $level >= 25 => '#3b82f6', // Blue
                $level >= 10 => '#22c55e', // Green
                default => '#9ca3af' // Gray
            };

            $avatarIndex = is_numeric($userProfile->userID) ? (substr($userProfile->userID, -1) % 6) : 0;
            $defaultAvatar = "https://cdn.discordapp.com/embed/avatars/{$avatarIndex}.png";
            
            $avatarUrl = \Illuminate\Support\Facades\Cache::remember('discord_avatar_' . $userProfile->userID, 86400, function() use ($userProfile, $defaultAvatar) {
                $botToken = config('services.discord.bot_token');
                if (!$botToken) return $defaultAvatar;
                
                $response = \Illuminate\Support\Facades\Http::withToken($botToken, 'Bot')
                    ->timeout(3)
                    ->get("https://discord.com/api/v10/users/{$userProfile->userID}");
                    
                if ($response->successful() && !empty($response->json('avatar'))) {
                    $hash = $response->json('avatar');
                    $ext = str_starts_with($hash, 'a_') ? 'gif' : 'png';
                    return "https://cdn.discordapp.com/avatars/{$userProfile->userID}/{$hash}.{$ext}?size=256";
                }
                return $defaultAvatar;
            });
        @endphp

        <style>
            .fav-card-panel {
                background: var(--glass-bg);
                border-radius: 12px;
                border: 1px solid var(--glass-border);
            }
        </style>

        <!-- Header Profile Banner -->
        <div class="glass-panel" style="position: relative; padding: 3rem; margin-bottom: 2rem; overflow: hidden; border-top: 4px solid {{ $hexColor }}; display: flex; gap: 2rem; align-items: center; flex-wrap: wrap;">
            <!-- Subtle background glow based on user color -->
            <div style="position: absolute; top: -50px; left: 0; width: 300px; height: 300px; background: {{ $hexColor }}; filter: blur(100px); opacity: 0.2; pointer-events: none;"></div>
            
            <div style="position: relative; z-index: 10; flex-shrink: 0;">
                <img src="{{ $avatarUrl }}" alt="Avatar" style="width: 120px; height: 120px; border-radius: 50%; border: 4px solid {{ $levelColor }}; object-fit: cover;">
                <div style="position: absolute; bottom: -10px; left: 50%; transform: translateX(-50%); background: {{ $levelColor }}; color: white; padding: 2px 10px; border-radius: 12px; font-weight: bold; font-size: 0.9rem; box-shadow: 0 2px 10px rgba(0,0,0,0.5); white-space: nowrap;">
                    LVL {{ $level }}
                </div>
            </div>

            <div style="display: flex; flex-direction: column; align-items: flex-start; text-align: left; position: relative; z-index: 10; flex: 1; min-width: 250px;">
                <h1 style="font-size: 3rem; margin: 0;">
                    {{ $userProfile->username }}
                </h1>
                
                @if(!empty($userProfile->preferences['profile']['title']))
                    <p style="color: {{ $hexColor }}; font-size: 1.2rem; font-weight: bold; text-transform: uppercase; letter-spacing: 2px; margin-top: 0.5rem;">
                        {{ $userProfile->preferences['profile']['title'] }}
                    </p>
                @endif

                <p style="color: var(--text-secondary); font-size: 1.1rem; max-width: 600px; margin: 1rem 0 0 0;">
                    "{{ $userProfile->preferences['profile']['bio'] ?? 'This user has not set a bio' }}"
                </p>
            </div>
        </div>

        <div style="display: flex; flex-wrap: wrap; gap: 2rem; flex-direction: row-reverse;">
            
            <!-- Right Column: Stats & Inventory (Now visually right) -->
            <div style="flex: 2; min-width: 300px; display: flex; flex-direction: column; gap: 2rem;">
                
                <!-- Balances -->
                <div class="glass-panel" style="padding: 2rem;">
                    <h2 style="font-size: 1.5rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="ph ph-wallet" style="color: var(--accent-solid);"></i> Inventory Balances
                    </h2>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 1rem;">
                        <div style="background: rgba(0,0,0,0.2); padding: 1rem; border-radius: 8px; text-align: center;">
                            <div style="font-size: 2rem; margin-bottom: 0.5rem;">🍅</div>
                            <div style="font-size: 1.2rem; font-weight: bold;">{{ number_format($userProfile->tomatoes ?? 0) }}</div>
                            <div style="font-size: 0.8rem; color: var(--text-secondary); text-transform: uppercase;">Tomatoes</div>
                        </div>
                        <div style="background: rgba(0,0,0,0.2); padding: 1rem; border-radius: 8px; text-align: center;">
                            <div style="font-size: 2rem; margin-bottom: 0.5rem;">🍋</div>
                            <div style="font-size: 1.2rem; font-weight: bold;">{{ number_format($userProfile->lemons ?? 0) }}</div>
                            <div style="font-size: 0.8rem; color: var(--text-secondary); text-transform: uppercase;">Lemons</div>
                        </div>
                        <div style="background: rgba(0,0,0,0.2); padding: 1rem; border-radius: 8px; text-align: center;">
                            <div style="font-size: 2rem; margin-bottom: 0.5rem;">🧪</div>
                            <div style="font-size: 1.2rem; font-weight: bold;">{{ number_format($userProfile->vials ?? 0) }}</div>
                            <div style="font-size: 0.8rem; color: var(--text-secondary); text-transform: uppercase;">Vials</div>
                        </div>
                        @if(($userProfile->promoBal ?? 0) > 0)
                        <div style="background: rgba(0,0,0,0.2); padding: 1rem; border-radius: 8px; text-align: center;">
                            <i class="ph-fill ph-gift" style="color: #a855f7; font-size: 2.5rem; margin-bottom: 0.5rem; display: inline-block;"></i>
                            <div style="font-size: 1.2rem; font-weight: bold;">{{ number_format($userProfile->promoBal) }}</div>
                            <div style="font-size: 0.8rem; color: var(--text-secondary); text-transform: uppercase;">Promo</div>
                        </div>
                        @endif
                    </div>
                </div>

                <!-- Stats & Progress -->
                <div class="glass-panel" style="padding: 2rem;">
                    <h2 style="font-size: 1.5rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="ph ph-chart-bar" style="color: var(--accent-solid);"></i> Statistics & Progress
                    </h2>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                        <div style="display: flex; justify-content: space-between; padding-bottom: 0.5rem; border-bottom: 1px solid rgba(255,255,255,0.05);">
                            <span style="color: var(--text-secondary);">XP</span>
                            <span style="font-weight: bold;">{{ number_format($userProfile->xp ?? 0) }} <i class="ph-fill ph-star" style="color: #fbbf24;"></i></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; padding-bottom: 0.5rem; border-bottom: 1px solid rgba(255,255,255,0.05);">
                            <span style="color: var(--text-secondary);">Daily Streak</span>
                            <span style="font-weight: bold;">{{ $userProfile->streaks['daily']['count'] ?? 0 }} <i class="ph-fill ph-fire" style="color: #ef4444;"></i></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; padding-bottom: 0.5rem; border-bottom: 1px solid rgba(255,255,255,0.05);">
                            <span style="color: var(--text-secondary);">Completed Collections</span>
                            <span style="font-weight: bold;">{{ count($userProfile->completedCols ?? []) }} <i class="ph-fill ph-trophy" style="color: #fbbf24;"></i></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; padding-bottom: 0.5rem; border-bottom: 1px solid rgba(255,255,255,0.05);">
                            <span style="color: var(--text-secondary);">Clouted Collections</span>
                            <span style="font-weight: bold;">{{ count($userProfile->cloutedCols ?? []) }} <i class="ph-fill ph-sparkle" style="color: #a855f7;"></i></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; padding-bottom: 0.5rem; border-bottom: 1px solid rgba(255,255,255,0.05);">
                            <span style="color: var(--text-secondary);">Achievements</span>
                            <span style="font-weight: bold;">{{ count($userProfile->achievements ?? []) }} <i class="ph-fill ph-medal" style="color: #fbbf24;"></i></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; padding-bottom: 0.5rem; border-bottom: 1px solid rgba(255,255,255,0.05);">
                            <span style="color: var(--text-secondary);">Joined</span>
                            <span style="font-weight: bold;">
                                {{ $userProfile->joined ? \Carbon\Carbon::parse($userProfile->joined)->format('M d, Y') : 'Unknown' }}
                            </span>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Left Column: Fav Card (Now visually left) -->
            <div style="flex: 1; min-width: 250px; display: flex; flex-direction: column;">
                <div class="fav-card-panel" style="padding: 2rem; flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center;">
                    @if($favCard)
                        <div style="width: 100%; max-width: 300px; margin-bottom: 1.5rem;">
                            <x-card-viewer :card="$favCard" :collectionName="$favCollection['name'] ?? null" />
                        </div>
                    @else
                        <div style="flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; opacity: 0.5; margin-bottom: 1.5rem;">
                            <i class="ph-light ph-cards" style="font-size: 4rem; margin-bottom: 1rem;"></i>
                            <p>No favorite card set.</p>
                        </div>
                    @endif

                    <div style="width: 100%; text-align: center; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 1.5rem; margin-top: auto;">
                        <a href="{{ route('cards.index') }}?owner={{ $userProfile->userID }}" class="btn btn-primary" style="background: var(--accent-solid); color: white; padding: 0.8rem 1.5rem; border-radius: 8px; text-decoration: none; font-weight: bold; display: inline-flex; align-items: center; gap: 0.5rem; width: 100%; justify-content: center; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 5px 15px var(--accent-glow)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                            <i class="ph ph-cards"></i> View {{ $userProfile->username }}'s Collection
                        </a>
                    </div>
                </div>
            </div>

        </div>

        @if($wishlistCards && $wishlistCards->total() > 0)
            <div id="wishlist-top" class="glass-panel" style="padding: 2rem; margin-top: 2rem;" x-data @scroll-to-wishlist.window="document.getElementById('wishlist-top').scrollIntoView({ behavior: 'smooth' })">
                <h2 style="font-size: 1.5rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="ph-fill ph-sparkle" style="color: #ec4899;"></i> Wishlist <span style="color: var(--text-secondary); font-size: 1rem; font-weight: normal;">({{ number_format($wishlistCards->total()) }})</span>
                </h2>
                
                <div wire:loading.remove>
                    <x-card-grid :cards="$wishlistCards" :collections="$wishlistCollections" :userOwned="$userOwned" :userFavs="$userFavs" :userWishlists="$userWishlists" />
                </div>
                
                <div wire:loading style="width: 100%;">
                    <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 5rem 0;">
                        <i class="ph-bold ph-spinner" style="font-size: 4rem; color: #ec4899; animation: spin 1s linear infinite;"></i>
                        <p style="color: var(--text-secondary); margin-top: 1rem; font-size: 1.2rem;">Loading wishlist...</p>
                    </div>
                </div>

                @if($wishlistCards->hasPages())
                    <div style="display: flex; justify-content: center; margin-top: 2rem; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 1.5rem;">
                        {{ $wishlistCards->links('components.custom-pagination') }}
                    </div>
                @endif
            </div>
        @endif
    @endif
</div>
