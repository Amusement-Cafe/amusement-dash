<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Url;
use App\Models\User;
use App\Models\Card;
use App\Models\BotCollection;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')] class extends Component
{
    #[Url]
    public string $id = '';

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

        return [
            'userProfile' => $userProfile,
            'favCard' => $favCard,
            'favCollection' => $favCollection,
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
        @endphp

        <!-- Header Profile Banner -->
        <div class="glass-panel" style="position: relative; padding: 3rem; margin-bottom: 2rem; overflow: hidden; border-top: 4px solid {{ $hexColor }};">
            <!-- Subtle background glow based on user color -->
            <div style="position: absolute; top: -50px; left: 50%; transform: translateX(-50%); width: 200px; height: 100px; background: {{ $hexColor }}; filter: blur(100px); opacity: 0.3; pointer-events: none;"></div>
            
            <div style="display: flex; flex-direction: column; align-items: center; text-align: center; position: relative; z-index: 10;">
                <h1 style="font-size: 3rem; margin: 0; display: flex; align-items: center; gap: 1rem;">
                    {{ $userProfile->username }}
                </h1>
                
                @if(!empty($userProfile->preferences['profile']['title']))
                    <p style="color: {{ $hexColor }}; font-size: 1.2rem; font-weight: bold; text-transform: uppercase; letter-spacing: 2px; margin-top: 0.5rem;">
                        {{ $userProfile->preferences['profile']['title'] }}
                    </p>
                @endif

                <p style="color: var(--text-secondary); font-size: 1.1rem; max-width: 600px; margin: 1.5rem auto 0;">
                    "{{ $userProfile->preferences['profile']['bio'] ?? 'This user has not set a bio' }}"
                </p>
            </div>
        </div>

        <div style="display: flex; flex-wrap: wrap; gap: 2rem;">
            
            <!-- Left Column: Stats & Inventory -->
            <div style="flex: 2; min-width: 300px; display: flex; flex-direction: column; gap: 2rem;">
                
                <!-- Balances -->
                <div class="glass-panel" style="padding: 2rem;">
                    <h2 style="font-size: 1.5rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                        <span>💰</span> Inventory Balances
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
                            <div style="font-size: 2rem; margin-bottom: 0.5rem;">🎁</div>
                            <div style="font-size: 1.2rem; font-weight: bold;">{{ number_format($userProfile->promoBal) }}</div>
                            <div style="font-size: 0.8rem; color: var(--text-secondary); text-transform: uppercase;">Promo</div>
                        </div>
                        @endif
                    </div>
                </div>

                <!-- Stats & Progress -->
                <div class="glass-panel" style="padding: 2rem;">
                    <h2 style="font-size: 1.5rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                        <span>📊</span> Statistics & Progress
                    </h2>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                        <div style="display: flex; justify-content: space-between; padding-bottom: 0.5rem; border-bottom: 1px solid rgba(255,255,255,0.05);">
                            <span style="color: var(--text-secondary);">XP</span>
                            <span style="font-weight: bold;">{{ number_format($userProfile->xp ?? 0) }} 🌟</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; padding-bottom: 0.5rem; border-bottom: 1px solid rgba(255,255,255,0.05);">
                            <span style="color: var(--text-secondary);">Daily Streak</span>
                            <span style="font-weight: bold;">{{ $userProfile->streaks['daily']['count'] ?? 0 }} 🔥</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; padding-bottom: 0.5rem; border-bottom: 1px solid rgba(255,255,255,0.05);">
                            <span style="color: var(--text-secondary);">Completed Collections</span>
                            <span style="font-weight: bold;">{{ count($userProfile->completedCols ?? []) }} 🏆</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; padding-bottom: 0.5rem; border-bottom: 1px solid rgba(255,255,255,0.05);">
                            <span style="color: var(--text-secondary);">Clouted Collections</span>
                            <span style="font-weight: bold;">{{ count($userProfile->cloutedCols ?? []) }} ✨</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; padding-bottom: 0.5rem; border-bottom: 1px solid rgba(255,255,255,0.05);">
                            <span style="color: var(--text-secondary);">Achievements</span>
                            <span style="font-weight: bold;">{{ count($userProfile->achievements ?? []) }} 🏅</span>
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

            <!-- Right Column: Fav Card -->
            <div style="flex: 1; min-width: 250px; display: flex; flex-direction: column;">
                <div class="glass-panel" style="padding: 2rem; flex: 1; display: flex; flex-direction: column; align-items: center;">
                    <h2 style="font-size: 1.5rem; margin-bottom: 1.5rem; width: 100%; text-align: left; display: flex; align-items: center; gap: 0.5rem;">
                        <span>❤️</span> Favorite Card
                    </h2>
                    
                    @if($favCard)
                        <div style="width: 100%; max-width: 300px; margin-bottom: 1.5rem;">
                            <x-card-viewer :card="$favCard" :collectionName="$favCollection['name'] ?? null" />
                        </div>
                    @else
                        <div style="flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; opacity: 0.5; margin-bottom: 1.5rem;">
                            <div style="font-size: 4rem; margin-bottom: 1rem;">🎴</div>
                            <p>No favorite card set.</p>
                        </div>
                    @endif

                    <div style="width: 100%; text-align: center; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 1.5rem; margin-top: auto;">
                        <a href="{{ route('cards.index') }}?owner={{ $userProfile->userID }}" class="btn btn-primary" style="background: var(--accent-solid); color: white; padding: 0.8rem 1.5rem; border-radius: 8px; text-decoration: none; font-weight: bold; display: inline-flex; align-items: center; gap: 0.5rem; width: 100%; justify-content: center; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 5px 15px var(--accent-glow)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                            🎴 View {{ $userProfile->username }}'s Collection
                        </a>
                    </div>
                </div>
            </div>

        </div>
    @endif
</div>
