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

        $dashboardData = [];
        if (auth()->check()) {
            $user = auth()->user();
            
            $lastDailyStr = $user->lastDaily; 
            $lastDaily = $lastDailyStr ? \Carbon\Carbon::parse($lastDailyStr) : null;
            $canClaimDaily = !$lastDaily || $lastDaily->diffInHours(now()) >= 20;
            $nextDailyTime = $canClaimDaily ? 'Ready to claim!' : $lastDaily->copy()->addHours(20)->diffForHumans(['parts' => 2]);
            
            $dashboardData['daily'] = [
                'canClaim' => $canClaimDaily,
                'nextTime' => $nextDailyTime
            ];

            $hero = null;
            if ($user->hero) {
                $hero = \App\Models\Hero::where('name', $user->hero)->first();
            }
            $heroLevel = $hero ? floor(sqrt(($hero->xp ?? 0) * 2)) : 0;
            $dashboardData['hero'] = $hero;
            $dashboardData['heroLevel'] = $heroLevel;

            $activePromos = \App\Models\Promo::where('expires', '>', now())->get();
            $dashboardData['promos'] = $activePromos;

            $plots = \App\Models\Plot::where('userID', $user->userID)->get();
            $dashboardData['plots'] = $plots;

            $transactions = \App\Models\Transaction::where('toID', $user->userID)
                ->orWhere('fromID', $user->userID)
                ->orderBy('dateCreated', 'desc')
                ->limit(5)
                ->get();
            $dashboardData['transactions'] = $transactions;

            $quests = \App\Models\UserQuest::where('userID', $user->userID)
                ->where('completed', false)
                ->where('expires', '>', now())
                ->get();
            $dashboardData['quests'] = $quests;

            $wishlistIDs = \App\Models\UserWishlist::where('userID', $user->userID)->pluck('cardID')->toArray();
            $wishlistIDs = array_map('intval', $wishlistIDs);
            $wishlistAuctions = [];
            $auctionCards = [];
            if (!empty($wishlistIDs)) {
                $wishlistAuctions = \App\Models\Auction::whereIn('cardID', $wishlistIDs)
                    ->where('ended', false)
                    ->where('cancelled', false)
                    ->where('expires', '>', now())
                    ->get();
                
                $auctionCardIDs = $wishlistAuctions->pluck('cardID')->toArray();
                $auctionCards = \App\Models\Card::whereIn('cardID', $auctionCardIDs)->get()->keyBy('cardID');
            }
            $dashboardData['wishlistAuctions'] = $wishlistAuctions;
            $dashboardData['auctionCards'] = $auctionCards;

            $inventory = \App\Models\UserInventory::where('userID', $user->userID)->get();
            $dashboardData['inventory'] = $inventory;

            // User Level Math
            $xp = $user->xp ?? 0;
            $userLvl = floor(sqrt($xp * 2));
            $currentLevelXp = ($userLvl ** 2) / 2;
            $nextLevelXp = (($userLvl + 1) ** 2) / 2;
            $xpProgress = $xp - $currentLevelXp;
            $xpNeeded = $nextLevelXp - $currentLevelXp;
            $xpPercent = $xpNeeded > 0 ? min(100, max(0, ($xpProgress / $xpNeeded) * 100)) : 100;
            
            $dashboardData['userLevel'] = [
                'level' => $userLvl,
                'xp' => $xp,
                'nextLevelXp' => $nextLevelXp,
                'percent' => $xpPercent
            ];
        }

        return [
            'trendingCards' => $trendingCards,
            'collections' => $collections,
            'dashboardData' => $dashboardData,
        ];
    }
};
?>

<div>
    @auth
        @php $data = $dashboardData; @endphp
        
        <div style="margin-bottom: 2rem; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-end; gap: 1.5rem;">
            <div>
                <h1 style="font-size: 2.5rem; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 1rem;">
                    Welcome back, {{ auth()->user()->username }}!
                </h1>
                
                <div style="display: flex; gap: 1.5rem; align-items: center; margin-top: 1rem;">
                    <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 1.2rem; font-weight: bold; background: var(--glass-bg); padding: 0.5rem 1rem; border-radius: 8px; border: 1px solid var(--glass-border);">
                        🍅 {{ number_format(auth()->user()->tomatoes ?? 0) }}
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 1.2rem; font-weight: bold; background: var(--glass-bg); padding: 0.5rem 1rem; border-radius: 8px; border: 1px solid var(--glass-border);">
                        🍋 {{ number_format(auth()->user()->lemons ?? 0) }}
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 1.2rem; font-weight: bold; background: var(--glass-bg); padding: 0.5rem 1rem; border-radius: 8px; border: 1px solid var(--glass-border);">
                        🧪 {{ number_format(auth()->user()->vials ?? 0) }}
                    </div>
                </div>
            </div>

            <div style="flex: 1; max-width: 400px; min-width: 250px; background: var(--glass-bg); padding: 1rem 1.5rem; border-radius: 12px; border: 1px solid var(--glass-border);">
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; font-weight: bold;">
                    <span style="color: var(--accent-solid);">LEVEL {{ $data['userLevel']['level'] }}</span>
                    <span style="color: var(--text-secondary); font-size: 0.9rem;">{{ number_format($data['userLevel']['xp']) }} / {{ number_format($data['userLevel']['nextLevelXp']) }} XP</span>
                </div>
                <div style="width: 100%; height: 8px; background: rgba(255,255,255,0.1); border-radius: 4px; overflow: hidden;">
                    <div style="width: {{ $data['userLevel']['percent'] }}%; height: 100%; background: var(--accent-solid); border-radius: 4px; box-shadow: 0 0 10px var(--accent-glow);"></div>
                </div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
            
            <!-- Daily Status -->
            <div class="glass-panel" style="padding: 1.5rem; display: flex; align-items: center; gap: 1rem; border-left: 4px solid {{ $data['daily']['canClaim'] ? '#10b981' : '#f59e0b' }};">
                <i class="ph-fill ph-calendar-check" style="font-size: 3.5rem; color: {{ $data['daily']['canClaim'] ? '#10b981' : '#f59e0b' }};"></i>
                <div>
                    <h3 style="margin: 0 0 0.2rem 0; font-size: 1.2rem;">Daily Claim</h3>
                    <p style="margin: 0; color: var(--text-secondary); font-size: 0.95rem;">
                        {{ $data['daily']['canClaim'] ? 'Your daily rewards are ready!' : 'Next daily available ' . $data['daily']['nextTime'] }}
                    </p>
                </div>
            </div>

            <!-- Promos -->
            <div class="glass-panel" style="padding: 1.5rem; display: flex; align-items: center; gap: 1rem; border-left: 4px solid #8b5cf6;">
                <i class="ph-fill ph-megaphone" style="font-size: 3.5rem; color: #8b5cf6;"></i>
                <div>
                    <h3 style="margin: 0 0 0.2rem 0; font-size: 1.2rem;">Active Events</h3>
                    <p style="margin: 0; color: var(--text-secondary); font-size: 0.95rem;">
                        {{ count($data['promos']) }} active promotional events currently running.
                    </p>
                </div>
            </div>

            <!-- Inventory Summary -->
            <a href="/inventory" class="glass-panel" style="padding: 1.5rem; display: flex; align-items: center; gap: 1rem; border-left: 4px solid #3b82f6; text-decoration: none; color: inherit; transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-2px)';" onmouseout="this.style.transform='translateY(0)';">
                <i class="ph-fill ph-backpack" style="font-size: 3.5rem; color: #3b82f6;"></i>
                <div>
                    <h3 style="margin: 0 0 0.2rem 0; font-size: 1.2rem;">Inventory</h3>
                    <p style="margin: 0; color: var(--text-secondary); font-size: 0.95rem;">
                        {{ count($data['inventory']) }} items stored. Click to view.
                    </p>
                </div>
            </a>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 1.5rem; margin-bottom: 3rem;">
            
            <!-- Left Column: Hero & Plots -->
            <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                @if($data['hero'])
                    <div class="glass-panel" style="padding: 2rem 1.5rem; text-align: center; position: relative; overflow: hidden;">
                        <div style="position: absolute; top: 0; left: 0; right: 0; height: 120px; background: linear-gradient(135deg, rgba(139,92,246,0.3), rgba(236,72,153,0.3)); z-index: 1;"></div>
                        <div style="position: relative; z-index: 2;">
                            @if(!empty($data['hero']->pictures) && isset($data['hero']->pictures[0]))
                                <img src="{{ $data['hero']->pictures[0] }}" style="width: 140px; height: 140px; border-radius: 50%; object-fit: cover; border: 4px solid var(--glass-bg); box-shadow: 0 10px 25px rgba(0,0,0,0.5);">
                            @else
                                <div style="width: 140px; height: 140px; border-radius: 50%; background: var(--glass-border); display: inline-flex; align-items: center; justify-content: center; font-size: 4rem; margin: 0 auto; box-shadow: 0 10px 25px rgba(0,0,0,0.5); border: 4px solid var(--glass-bg);">🦸</div>
                            @endif
                            <h2 style="margin: 1.5rem 0 0.5rem 0; font-size: 1.8rem;">{{ $data['hero']->name }}</h2>
                            <div style="display: inline-block; background: var(--accent-solid); color: white; padding: 4px 16px; border-radius: 12px; font-weight: bold; font-size: 0.9rem;">
                                LVL {{ $data['heroLevel'] }}
                            </div>
                            <div style="display: flex; justify-content: center; gap: 1.5rem; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid rgba(255,255,255,0.05);">
                                <div>
                                    <div style="color: var(--text-secondary); font-size: 0.8rem; text-transform: uppercase;">Followers</div>
                                    <div style="font-weight: bold; font-size: 1.2rem;"><i class="ph-fill ph-users" style="color: #3b82f6;"></i> {{ number_format($data['hero']->followers ?? 0) }}</div>
                                </div>
                                <div>
                                    <div style="color: var(--text-secondary); font-size: 0.8rem; text-transform: uppercase;">Hero XP</div>
                                    <div style="font-weight: bold; font-size: 1.2rem;"><i class="ph-fill ph-star" style="color: #fbbf24;"></i> {{ number_format($data['hero']->xp ?? 0) }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="glass-panel" style="padding: 2rem; text-align: center; border: 2px dashed var(--glass-border);">
                        <i class="ph-light ph-user-circle-plus" style="font-size: 4rem; margin-bottom: 1rem; color: var(--text-secondary);"></i>
                        <h3 style="margin: 0 0 0.5rem 0;">No Hero Assigned</h3>
                        <p style="color: var(--text-secondary); margin: 0;">You don't have an active hero.</p>
                    </div>
                @endif

                <div class="glass-panel" style="padding: 1.5rem;">
                    <h3 style="margin: 0 0 1rem 0; display: flex; align-items: center; gap: 0.5rem;"><i class="ph-fill ph-house-line" style="color: #eab308; font-size: 1.5rem;"></i> Your Plots</h3>
                    @if(count($data['plots']) > 0)
                        <div style="display: flex; flex-direction: column; gap: 1rem;">
                            @foreach($data['plots'] as $plot)
                                @php
                                    $lemons = $plot->building['storedLemons'] ?? 0;
                                @endphp
                                <div style="background: rgba(0,0,0,0.2); padding: 1rem; border-radius: 8px; display: flex; justify-content: space-between; align-items: center;">
                                    <div>
                                        <div style="font-weight: bold;">Plot #{{ $loop->iteration }}</div>
                                        <div style="font-size: 0.9rem; color: var(--text-secondary);">LVL {{ $plot->building['level'] ?? 1 }} {{ $plot->building['buildingID'] ?? 'Building' }}</div>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 0.5rem; font-weight: bold; font-size: 1.1rem;">
                                        {{ $lemons }} 🍋
                                        @if($lemons > 0)
                                            <button class="btn btn-primary" style="padding: 4px 10px; font-size: 0.8rem; margin-left: 0.5rem;">Collect</button>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div style="text-align: center; padding: 2rem 0; opacity: 0.6;">
                            <i class="ph-light ph-plant" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                            <p style="margin: 0;">You don't have any plots yet.</p>
                        </div>
                    @endif
                </div>
                
                @if(count($data['wishlistAuctions']) > 0)
                    <div class="glass-panel" style="padding: 1.5rem;">
                        <h3 style="margin: 0 0 1rem 0; display: flex; align-items: center; gap: 0.5rem;"><i class="ph-fill ph-gavel" style="color: #f59e0b; font-size: 1.5rem;"></i> Wishlist in Auction</h3>
                        <div style="display: flex; flex-direction: column; gap: 1rem;">
                            @foreach($data['wishlistAuctions'] as $auc)
                                @php $aucCard = $data['auctionCards'][$auc->cardID] ?? null; @endphp
                                <div style="background: rgba(0,0,0,0.2); border-radius: 8px; text-align: center; overflow: hidden; border: 1px solid var(--glass-border);">
                                    <div style="padding: 1rem;">
                                        @if($aucCard)
                                            <h4 style="margin: 0 0 0.5rem 0; font-size: 1.1rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $aucCard->displayName ?? $aucCard->cardName }}</h4>
                                            <div style="font-size: 0.9rem; color: var(--text-secondary); margin-bottom: 0.8rem;">{{ str_repeat('⭐', $aucCard->rarity ?? 1) }}</div>
                                        @else
                                            <h4 style="margin: 0 0 0.5rem 0; font-size: 1.1rem;">Card #{{ $auc->cardID }}</h4>
                                        @endif
                                        <div style="font-weight: bold; color: #f59e0b; font-size: 1.2rem; display: flex; justify-content: center; align-items: center; gap: 0.3rem;">
                                            {{ number_format($auc->highBid ?? $auc->price ?? 0) }} 🍅
                                        </div>
                                    </div>
                                    <div style="background: rgba(245, 158, 11, 0.1); padding: 0.5rem; font-size: 0.85rem; color: #fcd34d; font-weight: bold;">
                                        Ends {{ \Carbon\Carbon::parse($auc->expires)->diffForHumans() }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <!-- Right Column: Quests & Transactions -->
            <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                <div class="glass-panel" style="padding: 1.5rem;">
                    <h3 style="margin: 0 0 1rem 0; display: flex; align-items: center; gap: 0.5rem;"><i class="ph-fill ph-scroll" style="color: #ec4899; font-size: 1.5rem;"></i> Active Quests</h3>
                    @if(count($data['quests']) > 0)
                        <div style="display: flex; flex-direction: column; gap: 1rem;">
                            @foreach($data['quests'] as $quest)
                                <div style="background: rgba(0,0,0,0.2); padding: 1.2rem; border-radius: 8px; border-left: 4px solid #ec4899;">
                                    <div style="font-weight: bold; text-transform: uppercase; font-size: 1rem; color: #ec4899; letter-spacing: 1px;">{{ str_replace('_', ' ', $quest->type ?? 'Quest') }}</div>
                                    <div style="font-size: 1.1rem; margin-top: 0.5rem;">ID: {{ $quest->questID }}</div>
                                    <div style="font-size: 0.9rem; color: var(--text-secondary); margin-top: 0.8rem; display: flex; align-items: center; gap: 0.3rem; background: rgba(236,72,153,0.1); padding: 0.4rem; border-radius: 4px; display: inline-flex;">
                                        <i class="ph ph-clock"></i> Expires: {{ \Carbon\Carbon::parse($quest->expires)->diffForHumans() }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div style="text-align: center; padding: 4rem 0; opacity: 0.6;">
                            <i class="ph-light ph-check-circle" style="font-size: 4rem; margin-bottom: 1rem;"></i>
                            <p style="margin: 0; font-size: 1.1rem;">No active quests right now. You're all caught up!</p>
                        </div>
                    @endif
                </div>

                <div class="glass-panel" style="padding: 1.5rem; opacity: 0.8;">
                    <h3 style="margin: 0 0 1.5rem 0; display: flex; align-items: center; gap: 0.5rem; font-size: 1.1rem;"><i class="ph-fill ph-arrows-left-right" style="color: var(--text-secondary); font-size: 1.3rem;"></i> Recent Transactions</h3>
                    @if(count($data['transactions']) > 0)
                        <div style="display: flex; flex-direction: column; gap: 0.8rem;">
                            @foreach($data['transactions'] as $tx)
                                @php
                                    $isIncoming = $tx->toID === auth()->user()->userID;
                                    $color = $isIncoming ? '#10b981' : '#ef4444';
                                    $icon = $isIncoming ? 'ph-arrow-down-left' : 'ph-arrow-up-right';
                                    $statusColor = match($tx->status ?? '') {
                                        'completed' => '#10b981',
                                        'pending' => '#f59e0b',
                                        'cancelled' => '#ef4444',
                                        default => 'var(--text-secondary)'
                                    };
                                @endphp
                                <div style="background: rgba(0,0,0,0.2); padding: 0.8rem; border-radius: 8px; display: flex; justify-content: space-between; align-items: center;">
                                    <div style="display: flex; align-items: center; gap: 0.8rem;">
                                        <div style="width: 32px; height: 32px; border-radius: 50%; background: {{ $color }}20; color: {{ $color }}; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0;">
                                            <i class="ph-bold {{ $icon }}"></i>
                                        </div>
                                        <div>
                                            <div style="font-weight: bold; font-size: 0.9rem;">
                                                {{ $isIncoming ? 'From ' . $tx->fromID : 'To ' . $tx->toID }}
                                            </div>
                                            <div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.2rem;">
                                                <span style="color: {{ $statusColor }}; text-transform: capitalize;">{{ $tx->status ?? 'Completed' }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div style="text-align: right; font-weight: bold;">
                                        @if($tx->cost > 0)
                                            <div style="font-size: 0.9rem; display: flex; align-items: center; justify-content: flex-end; gap: 0.2rem;">
                                                {{ number_format($tx->cost) }} 🍅
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div style="text-align: center; padding: 1.5rem 0; opacity: 0.6;">
                            <i class="ph-light ph-receipt" style="font-size: 2rem; margin-bottom: 0.5rem;"></i>
                            <p style="margin: 0; font-size: 0.9rem;">No recent transactions.</p>
                        </div>
                    @endif
                </div>

            </div>
        </div>
    @else
        <div class="glass-panel" style="padding: 3rem; text-align: center; margin-bottom: 2rem;">
            <h1 style="font-size: 2.5rem; margin-bottom: 1rem;">Welcome to Amusement Club</h1>
            <p style="color: var(--text-secondary); font-size: 1.1rem; margin-bottom: 2rem;">
                Sign in with Discord to view your card collection, track your stats, and explore the global marketplace.
            </p>
            <a href="{{ route('login.discord') }}" class="btn btn-primary" style="font-size: 1.1rem; padding: 0.8rem 2rem;">
                Get Started
            </a>
        </div>
    @endauth

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