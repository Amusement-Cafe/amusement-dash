<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Livewire\Attributes\Url;
use Livewire\Attributes\On;
use App\Models\Claim;
use App\Models\User;
use App\Models\Card;
use App\Models\Promo;
use App\Models\UserStat;
use App\Models\BotCollection;
use App\Models\UserCard;
use Illuminate\Support\Facades\Cache;

use Livewire\Attributes\Title;

new #[Layout('layouts.app')] #[Title('Claims')] class extends Component
{
    use WithPagination;

    #[Url]
    public ?string $id = null;

    #[Url]
    public string $tab = 'claim'; // 'claim' or 'history'
    
    public string $selectedBannerId = 'standard';
    public int $claimAmount = 1;
    
    public bool $showRevealModal = false;
    public array $revealedCards = [];

    public function selectClaim($id)
    {
        $this->id = $id;
        $this->tab = 'history';
    }

    #[On('close-reveal')]
    public function closeReveal()
    {
        $this->showRevealModal = false;
        $this->revealedCards = [];
        $this->resetPage();
    }

    public function setAmount($amount)
    {
        if ($amount >= 1 && $amount <= 10) {
            $this->claimAmount = $amount;
        }
    }

    public function selectBanner($id)
    {
        $this->selectedBannerId = $id;
    }

    public function getBannersProperty()
    {
        return Cache::remember('claims_banners_v4', 300, function() {
            $banners = [];
            
            $promoCols = BotCollection::where('promo', true)->get();
            $promoColIds = $promoCols->pluck('collectionID')->toArray();
            
            $banners[] = [
                'id' => 'standard',
                'name' => 'Standard Claim',
                'type' => 'regular',
                'baseCost' => 50,
                'description' => 'Claim regular cards from the standard pool. Contains all non-promo collections.',
                'totalCount' => Card::whereNotIn('collectionID', $promoColIds)->whereIn('rarity', [1, 2, 3, 4, 5])->count(),
            ];
            
            $activePromos = Promo::where('expires', '>', new \MongoDB\BSON\UTCDateTime(now()))
                ->where('starts', '<', new \MongoDB\BSON\UTCDateTime(now()))
                ->get();
                
            foreach ($activePromos as $promo) {
                if ($promo->isBoost && !empty($promo->cardIDs)) {
                    $banners[] = [
                        'id' => $promo->promoID,
                        'name' => $promo->promoName ?? 'Boost Promo',
                        'type' => 'promo',
                        'baseCost' => 25,
                        'description' => 'Boosted drops for specific cards!',
                        'totalCount' => count($promo->cardIDs),
                        'cardIDs' => $promo->cardIDs,
                    ];
                } else {
                    $col = $promoCols->firstWhere('collectionID', $promo->promoID);
                    if ($col) {
                        $banners[] = [
                            'id' => $promo->promoID,
                            'name' => $promo->promoName ?? $col->name,
                            'type' => 'promo',
                            'baseCost' => 25,
                            'description' => 'Special event promo cards!',
                            'totalCount' => Card::where('collectionID', $promo->promoID)->count(),
                        ];
                    }
                }
            }
            return $banners;
        });
    }

    public function getShowcasesProperty()
    {
        $cacheKey = 'claims_showcases_v5_' . date('Ymd');
        return Cache::remember($cacheKey, now()->endOfDay(), function() {
            $showcases = [];
            $promoColIds = BotCollection::where('promo', true)->pluck('collectionID')->toArray();

            mt_srand((int) date('Ymd'));

            foreach ($this->banners as $b) {
                $query = Card::query();
                if ($b['type'] === 'regular') {
                    $query->whereNotIn('collectionID', $promoColIds);
                } elseif ($b['type'] === 'promo') {
                    if (isset($b['cardIDs'])) {
                        $query->whereIn('cardID', $b['cardIDs']);
                    } else {
                        $query->where('collectionID', $b['id']);
                    }
                }
                
                $r3Ids = (clone $query)->where('rarity', 3)->pluck('cardID')->toArray();
                $r2Ids = (clone $query)->where('rarity', 2)->pluck('cardID')->toArray();
                $r1Ids = (clone $query)->where('rarity', 1)->pluck('cardID')->toArray();
                
                $r3 = !empty($r3Ids) ? Card::where('cardID', $r3Ids[array_rand($r3Ids)])->first() : null;
                $r2 = !empty($r2Ids) ? Card::where('cardID', $r2Ids[array_rand($r2Ids)])->first() : null;
                $r1 = !empty($r1Ids) ? Card::where('cardID', $r1Ids[array_rand($r1Ids)])->first() : null;
                
                $found = [];
                if ($r3) $found[] = $r3->toArray();
                if ($r2) $found[] = $r2->toArray();
                if ($r1) $found[] = $r1->toArray();
                
                if (count($found) < 3) {
                    $needed = 3 - count($found);
                    $existingIds = array_column($found, 'cardID');
                    $allIds = (clone $query)->whereNotIn('cardID', $existingIds)->pluck('cardID')->toArray();
                    for ($i = 0; $i < $needed; $i++) {
                        if (empty($allIds)) break;
                        $randIdx = array_rand($allIds);
                        $c = Card::where('cardID', $allIds[$randIdx])->first();
                        if ($c) $found[] = $c->toArray();
                        unset($allIds[$randIdx]);
                    }
                }
                
                $cards = [];
                if (count($found) == 3) {
                    $cards[0] = $found[2]; // Left
                    $cards[1] = $found[0]; // Center (rarest)
                    $cards[2] = $found[1]; // Right
                } else {
                    $cards = $found;
                }
                
                $showcases[$b['id']] = array_values($cards);
            }
            
            mt_srand();
            return $showcases;
        });
    }

    public function getPriceProperty()
    {
        if (!auth()->check()) return 0;
        $user = auth()->user();
        
        $userStats = UserStat::where('userID', $user->userID)
            ->where('daily', $user->lastDaily)
            ->first();
            
        $isPromo = $this->selectedBannerId !== 'standard';
        
        $banners = collect($this->banners);
        $banner = $banners->firstWhere('id', $this->selectedBannerId);
        $base = $banner ? $banner['baseCost'] : ($isPromo ? 25 : 50);
        
        $claimCount = 0;
        if ($userStats) {
            $claimCount = $isPromo ? ($userStats->promoClaims ?? $userStats->promoclaims ?? 0) : ($userStats->claims ?? 0);
        }
        
        $price = 0;
        $tempClaims = $claimCount;
        for ($i = 0; $i < $this->claimAmount; $i++) {
            $tempClaims++;
            $price += $tempClaims * $base;
        }
        return $price;
    }

    public function doClaim()
    {
        if (!auth()->check()) return;
        $user = auth()->user();
        
        $price = $this->price;
        if ($user->tomatoes < $price) {
            session()->flash('error', 'Not enough tomatoes!');
            return;
        }
        
        $userStats = UserStat::where('userID', $user->userID)
            ->where('daily', $user->lastDaily)
            ->first();
            
        $isPromo = $this->selectedBannerId !== 'standard';
        
        $claimCount = 0;
        if ($userStats) {
            $claimCount = $isPromo ? ($userStats->promoClaims ?? $userStats->promoclaims ?? 0) : ($userStats->claims ?? 0);
        }
        $tempClaims = $claimCount + $this->claimAmount;
        
        // deduct price
        $user->tomatoes -= $price;
        $user->save();
        
        // update stats
        if ($userStats) {
            if ($isPromo) {
                $userStats->promoClaims = $tempClaims;
                $userStats->promoclaims = $tempClaims;
            } else {
                $userStats->claims = $tempClaims;
            }
            $userStats->save();
        } else {
            $userStats = new UserStat();
            $userStats->userID = $user->userID;
            $userStats->daily = $user->lastDaily;
            if ($isPromo) {
                $userStats->promoClaims = $tempClaims;
                $userStats->promoclaims = $tempClaims;
            } else {
                $userStats->claims = $tempClaims;
            }
            $userStats->save();
        }
        
        // draw cards (mock logic for now)
        $cardsPool = [];
        if ($isPromo) {
            $promo = Promo::where('promoID', $this->selectedBannerId)->first();
            if ($promo && $promo->isBoost && !empty($promo->cardIDs)) {
                $cardsPool = Card::whereIn('cardID', $promo->cardIDs)->pluck('cardID')->toArray();
            } else {
                $cardsPool = Card::where('collectionID', $this->selectedBannerId)->pluck('cardID')->toArray();
            }
        } else {
            $promoColIds = BotCollection::where('promo', true)->pluck('collectionID')->toArray();
            $cardsPool = Card::whereNotIn('collectionID', $promoColIds)->whereIn('rarity', [1, 2, 3, 4, 5])->pluck('cardID')->toArray();
        }
        
        $drawn = [];
        for ($i = 0; $i < $this->claimAmount; $i++) {
            if (!empty($cardsPool)) {
                $drawn[] = $cardsPool[array_rand($cardsPool)];
            }
        }
        
        if (empty($drawn)) {
            session()->flash('error', 'No cards available in this pool!');
            return;
        }
        
        // save as claim
        $claim = new Claim();
        $claim->claimID = \Illuminate\Support\Str::random(10);
        $claim->userID = $user->userID;
        $claim->cardIDs = $drawn;
        $claim->promo = $isPromo;
        $claim->timeClaimed = new \MongoDB\BSON\UTCDateTime(now());
        $claim->cost = $price;
        $claim->save();
        
        // save user cards
        foreach ($drawn as $cid) {
            $uc = UserCard::where('userID', $user->userID)->where('cardID', $cid)->first();
            if ($uc) {
                $uc->amount = ($uc->amount ?? 1) + 1;
                $uc->save();
            } else {
                $nuc = new UserCard();
                $nuc->userID = $user->userID;
                $nuc->cardID = $cid;
                $nuc->amount = 1;
                $nuc->acquired = new \MongoDB\BSON\UTCDateTime(now());
                $nuc->save();
            }
        }
        
        $this->revealedCards = $drawn;
        $this->showRevealModal = true;
    }

    public function with(): array
    {
        $user = auth()->user();

        $claims = Claim::where('userID', $user->userID)
            ->orderBy('timeClaimed', 'desc')
            ->paginate(30);

        if ($this->tab === 'history' && empty($this->id) && $claims->count() > 0) {
            $this->id = (string) $claims->first()->_id;
        }

        $selectedClaim = null;
        $claimCards = [];

        if ($this->id) {
            $selectedClaim = Claim::where('_id', $this->id)->orWhere('claimID', $this->id)->first();
            
            if ($selectedClaim) {
                if (!empty($selectedClaim->cardIDs)) {
                    $cardIDsToFetch = array_slice($selectedClaim->cardIDs, 0, 20);
                    $claimCards = Card::whereIn('cardID', $cardIDsToFetch)->get();
                }
            }
        }

        return [
            'claims' => $claims,
            'selectedClaim' => $selectedClaim,
            'claimCards' => $claimCards,
            'currentUser' => $user,
        ];
    }
};
?>

<div>
    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .claim-tab {
            padding: 1rem 2rem;
            font-size: 1.2rem;
            font-weight: bold;
            color: var(--text-secondary);
            border-bottom: 3px solid transparent;
            cursor: pointer;
            transition: all 0.2s;
        }
        .claim-tab.active {
            color: var(--accent-solid);
            border-bottom-color: var(--accent-solid);
        }
        .claim-tab:hover:not(.active) {
            color: white;
            background: rgba(255,255,255,0.05);
        }
        .banner-card {
            background: rgba(0,0,0,0.3);
            border: 2px solid var(--glass-border);
            border-radius: 16px;
            padding: 1.5rem;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            display: flex;
            flex-direction: column;
            gap: 1rem;
            min-width: 500px;
            flex: 1;
            position: relative;
        }
        .banner-card:hover {
            transform: translateY(-5px);
            border-color: rgba(99, 102, 241, 0.5);
            box-shadow: 0 10px 30px rgba(99, 102, 241, 0.2);
        }
        .banner-card.selected {
            border-color: var(--accent-solid);
            box-shadow: 0 10px 40px rgba(99, 102, 241, 0.4), inset 0 0 20px rgba(99, 102, 241, 0.2);
            background: rgba(99, 102, 241, 0.1);
        }
        .banner-card.selected::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            border-radius: 16px;
            background: radial-gradient(circle at center, rgba(255,255,255,0.1) 0%, transparent 60%);
            pointer-events: none;
        }
        
        .shiny-btn {
            background: linear-gradient(45deg, #ec4899, #8b5cf6, #3b82f6);
            background-size: 200% 200%;
            animation: shinyBg 3s ease infinite;
            border: none;
            border-radius: 50px;
            color: white;
            font-size: 1.8rem;
            font-weight: bold;
            padding: 1rem 4rem;
            cursor: pointer;
            box-shadow: 0 10px 30px rgba(139, 92, 246, 0.5);
            transition: transform 0.2s, box-shadow 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 1rem;
        }
        .shiny-btn:hover:not(:disabled) {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 15px 40px rgba(139, 92, 246, 0.7);
        }
        .shiny-btn:active:not(:disabled) {
            transform: translateY(2px) scale(0.98);
        }
        .shiny-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            animation: none;
            background: rgba(255,255,255,0.1);
            box-shadow: none;
        }
        @keyframes shinyBg {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
    </style>

    <div style="display: flex; gap: 1rem; margin-bottom: 2rem; border-bottom: 1px solid var(--glass-border);">
        <div class="claim-tab {{ $tab === 'claim' ? 'active' : '' }}" wire:click="$set('tab', 'claim')">
            <i class="ph-bold ph-cards"></i> New Claim
        </div>
        <div class="claim-tab {{ $tab === 'history' ? 'active' : '' }}" wire:click="$set('tab', 'history')">
            <i class="ph-bold ph-clock-counter-clockwise"></i> Claim History
        </div>
    </div>
    
    @if(session()->has('error'))
        <div style="background: rgba(239, 68, 68, 0.2); border: 1px solid #ef4444; color: #fca5a5; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem; animation: fadeIn 0.3s ease-out;">
            <i class="ph-fill ph-warning-circle" style="font-size: 1.5rem;"></i> {{ session('error') }}
        </div>
    @endif

    <div x-show="$wire.tab === 'claim'" style="animation: fadeIn 0.3s ease-out;">
        <h2 style="font-size: 2rem; margin-bottom: 0rem;">Select a Banner</h2>
        <div style="display: flex; gap: 2rem; overflow-x: auto; padding: 2rem 1rem; margin: 0 -1rem 1rem -1rem;">
            @foreach($this->banners as $banner)
                <div class="banner-card {{ $selectedBannerId === $banner['id'] ? 'selected' : '' }}" wire:click="selectBanner('{{ $banner['id'] }}')">
                    @if($selectedBannerId === $banner['id'])
                        <div style="position: absolute; top: 1rem; right: 1rem; color: var(--accent-solid); font-size: 1.5rem;">
                            <i class="ph-fill ph-check-circle"></i>
                        </div>
                    @endif
                    
                    <div>
                        <h3 style="margin: 0; font-size: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                            @if($banner['type'] === 'promo')
                                <i class="ph-fill ph-sparkle" style="color: #fbbf24;"></i>
                            @else
                                <i class="ph-fill ph-package" style="color: #60a5fa;"></i>
                            @endif
                            {{ $banner['name'] }}
                        </h3>
                        <p style="color: var(--text-secondary); margin: 0.5rem 0 0 0; font-size: 0.95rem;">
                            {{ $banner['description'] }}
                        </p>
                        <p style="color: var(--text-secondary); margin: 0.2rem 0 0 0; font-size: 0.85rem; font-weight: bold;">
                            Total Cards in Pool: {{ number_format($banner['totalCount']) }}
                        </p>
                    </div>
                    
                    @if(false)
                        <!-- Collection preview removed as per request -->
                    @endif
                    
                    <div style="display: flex; gap: 0; margin-top: 3rem; justify-content: center; align-items: center; padding: 1rem 0;">
                        @php
                            $showcase = $this->showcases[$banner['id']] ?? [];
                        @endphp
                        @foreach($showcase as $i => $c)
                            @php
                                $isCenter = ($i === 1);
                                $isLeft = ($i === 0);
                                $isRight = ($i === 2);
                                
                                $zIndex = $isCenter ? 10 : 1;
                                $scale = $isCenter ? 'scale(1.15)' : 'scale(0.95)';
                                $rotate = $isLeft ? 'rotate(-10deg)' : ($isRight ? 'rotate(10deg)' : 'rotate(0deg)');
                                $translateX = $isLeft ? 'translateX(40px)' : ($isRight ? 'translateX(-40px)' : 'translateX(0)');
                                
                                $transform = "$translateX $scale $rotate";
                                $hoverTransform = "$translateX scale(" . ($isCenter ? '1.25' : '1.05') . ") $rotate";
                            @endphp
                            <div style="position: relative; width: 160px; height: 224px; border-radius: 12px; border: 2px solid var(--glass-border); background: rgba(0,0,0,0.5); box-shadow: 0 15px 30px rgba(0,0,0,0.5); z-index: {{ $zIndex }}; transform: {{ $transform }}; transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);" onmouseover="this.style.transform='{{ $hoverTransform }}'; this.style.zIndex='20'" onmouseout="this.style.transform='{{ $transform }}'; this.style.zIndex='{{ $zIndex }}'">
                                @if(!empty($c['cardURL']))
                                    <img src="{{ $c['cardURL'] }}" alt="Card" style="width: 100%; height: 100%; object-fit: cover; border-radius: 10px;">
                                @else
                                    <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; font-size: 3rem; color: var(--text-secondary);">?</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
        
        <div class="glass-panel" style="padding: 3rem; text-align: center; border-radius: 24px; position: relative; overflow: hidden;">
            <!-- Background flair -->
            <div style="position: absolute; top: -50%; left: -50%; width: 200%; height: 200%; background: radial-gradient(circle at center, rgba(139, 92, 246, 0.1) 0%, transparent 50%); pointer-events: none; z-index: 0;"></div>
            
            <div style="position: relative; z-index: 1;">
                <h3 style="font-size: 1.5rem; margin-bottom: 1.5rem;">How many cards?</h3>
                
                <div style="display: flex; justify-content: center; align-items: center; gap: 2rem; margin-bottom: 2rem;">
                    <button wire:click="setAmount({{ max(1, $claimAmount - 1) }})" style="background: rgba(255,255,255,0.1); border: none; color: white; width: 50px; height: 50px; border-radius: 50%; font-size: 1.5rem; cursor: pointer; transition: background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.2)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'">
                        <i class="ph-bold ph-minus"></i>
                    </button>
                    
                    <div style="font-size: 3rem; font-weight: bold; width: 80px; text-align: center; color: var(--accent-solid); text-shadow: 0 0 20px rgba(99, 102, 241, 0.5);">
                        {{ $claimAmount }}
                    </div>
                    
                    <button wire:click="setAmount({{ min(10, $claimAmount + 1) }})" style="background: rgba(255,255,255,0.1); border: none; color: white; width: 50px; height: 50px; border-radius: 50%; font-size: 1.5rem; cursor: pointer; transition: background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.2)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'">
                        <i class="ph-bold ph-plus"></i>
                    </button>
                </div>
                
                <div style="margin-bottom: 2rem;">
                    <div style="font-size: 1.2rem; color: var(--text-secondary); margin-bottom: 0.5rem;">Total Cost</div>
                    @php $affords = $currentUser->tomatoes >= $this->price; @endphp
                    <div style="font-size: 2.5rem; font-weight: bold; color: {{ $affords ? '#34d399' : '#ef4444' }}; text-shadow: 0 0 20px {{ $affords ? 'rgba(52, 211, 153, 0.4)' : 'rgba(239, 68, 68, 0.4)' }}; display: flex; justify-content: center; align-items: center; gap: 0.5rem;">
                        {{ number_format($this->price) }} 🍅
                    </div>
                    <div style="color: var(--text-secondary); margin-top: 0.5rem;">
                        You have: <span style="font-weight: bold; color: white;">{{ number_format($currentUser->tomatoes) }} 🍅</span>
                    </div>
                </div>
                
                <div style="display: flex; flex-direction: column; align-items: center;">
                    <button class="shiny-btn" wire:click="doClaim" wire:loading.attr="disabled" @if(!$affords) disabled @endif>
                        <i class="ph-fill ph-sparkle"></i> CLAIM NOW <i class="ph-fill ph-sparkle"></i>
                    </button>
                    <div wire:loading.flex wire:target="doClaim" style="margin-top: 1rem; color: var(--accent-solid); font-weight: bold; align-items: center; gap: 0.5rem;">
                        <i class="ph-bold ph-spinner ph-spin"></i> Processing drop...
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- History Tab -->
    <div x-show="$wire.tab === 'history'" style="display: none; animation: fadeIn 0.3s ease-out;" :style="$wire.tab === 'history' ? 'display: block;' : 'display: none;'">
        <div style="display: flex; gap: 2rem; align-items: stretch; flex-wrap: wrap;">
            <!-- Left column: Claims List -->
            <div style="flex: 1; min-width: 350px;">
                <h2 style="font-size: 1.8rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="ph-fill ph-clock-counter-clockwise" style="color: var(--accent-solid);"></i> My Claims
                </h2>
    
                <div class="glass-panel" style="padding: 1rem;">
                    @if($claims->count() > 0)
                        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                            @foreach($claims as $tx)
                                @php
                                    $isSelected = $id == $tx->_id || $id == $tx->claimID;
                                @endphp
                                <div wire:click="selectClaim('{{ $tx->_id }}')" style="background: {{ $isSelected ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.2)' }}; border: 1px solid {{ $isSelected ? 'var(--accent-solid)' : 'transparent' }}; padding: 0.8rem; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.05)'" onmouseout="this.style.background='{{ $isSelected ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.2)' }}'">
                                    <div style="display: flex; align-items: center; gap: 0.8rem;">
                                        <div style="width: 32px; height: 32px; border-radius: 50%; background: rgba(16, 185, 129, 0.2); color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0;">
                                            <i class="ph-bold ph-hand-coins"></i>
                                        </div>
                                        <div>
                                            <div style="font-weight: bold; font-size: 0.9rem;">
                                                {{ $tx->promo ? 'Promo Claim' : 'Regular Claim' }}
                                            </div>
                                            <div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.2rem; display: flex; gap: 0.5rem;">
                                                <span>{{ $tx->timeClaimed ? \Carbon\Carbon::parse($tx->timeClaimed)->diffForHumans() : 'Unknown date' }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div style="text-align: right; font-weight: bold;">
                                        @if($tx->cost > 0)
                                            <div style="font-size: 0.9rem; display: flex; align-items: center; justify-content: flex-end; gap: 0.2rem;">
                                                {{ number_format($tx->cost) }} 🍅
                                            </div>
                                        @endif
                                        @if(!empty($tx->cardIDs))
                                            <div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.2rem;">
                                                <i class="ph-fill ph-cards"></i> {{ count($tx->cardIDs) }} Cards
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        
                        <div style="margin-top: 1.5rem;">
                            {{ $claims->links('components.custom-pagination') }}
                        </div>
                    @else
                        <div style="padding: 3rem; text-align: center;">
                            <i class="ph-light ph-hand-coins" style="font-size: 3rem; color: var(--text-secondary); margin-bottom: 1rem;"></i>
                            <p style="margin: 0; font-size: 1.1rem;">You have no claims yet.</p>
                        </div>
                    @endif
                </div>
            </div>
    
            <!-- Right column: Claim Details -->
            <div style="flex: 2; min-width: 400px;">
                <div style="position: sticky; top: 6rem;">
                    <div class="glass-panel" style="padding: 2rem; min-height: 400px;">
                        @if($selectedClaim)
                            <div style="animation: fadeIn 0.3s ease-out;">
                                <!-- Header -->
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem; border-bottom: 1px solid var(--glass-border); padding-bottom: 1rem;">
                                    <div>
                                        <h2 style="margin: 0 0 0.5rem 0; font-size: 1.8rem; display: flex; align-items: center; gap: 0.5rem;">
                                            <i class="ph-fill ph-hand-coins" style="color: #10b981;"></i> Claim Details
                                        </h2>
                                        <div style="color: var(--text-secondary); font-size: 0.9rem;">
                                            {{ $selectedClaim->timeClaimed ? \Carbon\Carbon::parse($selectedClaim->timeClaimed)->format('M d, Y g:i A') : 'Unknown Date' }}
                                        </div>
                                    </div>
                                    <div style="text-align: right;">
                                        <div style="background: rgba(16, 185, 129, 0.2); color: #10b981; padding: 6px 12px; border-radius: 20px; font-weight: bold; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 0.5rem;">
                                            <i class="ph-bold ph-check-circle"></i> Completed
                                        </div>
                                        @if($selectedClaim->cost > 0)
                                            <div style="margin-top: 0.5rem; font-size: 1.2rem; font-weight: bold; color: white;">
                                                Cost: {{ number_format($selectedClaim->cost) }} 🍅
                                            </div>
                                        @endif
                                    </div>
                                </div>
        
                                <!-- Cards Grid -->
                                <div style="margin-bottom: 2rem;">
                                    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 1rem;">
                                        <h3 style="font-size: 1.2rem; margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                                            <i class="ph-fill ph-cards" style="color: #ec4899;"></i> Cards Claimed
                                        </h3>
                                        <div style="font-size: 0.9rem; color: var(--text-secondary); display: flex; align-items: center; gap: 0.5rem;">
                                            <span>{{ count($selectedClaim->cardIDs) }} Total (Showing {{ min(count($selectedClaim->cardIDs), 20) }})</span>
                                            @if(count($selectedClaim->cardIDs) > 0)
                                                <span style="color: rgba(255,255,255,0.2);">|</span>
                                                @php $claimIdForLink = $selectedClaim->claimID ?? $selectedClaim->_id; @endphp
                                                <a href="/cards?claimID={{ $claimIdForLink }}" style="color: #ec4899; text-decoration: none; font-weight: bold; transition: color 0.2s;" onmouseover="this.style.color='#f472b6'" onmouseout="this.style.color='#ec4899'">View All <i class="ph-bold ph-arrow-square-out"></i></a>
                                            @endif
                                        </div>
                                    </div>
                                    
                                    @if(count($claimCards) > 0)
                                        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                            @foreach($claimCards as $card)
                                                <a href="/cards?search={{ $card->cardID }}" target="_blank" style="text-decoration: none; color: inherit; display: block;">
                                                    <div style="background: rgba(0,0,0,0.3); border-radius: 8px; padding: 0.8rem 1rem; border: 1px solid var(--glass-border); transition: transform 0.2s, background 0.2s; display: flex; justify-content: space-between; align-items: center;" onmouseover="this.style.transform='translateY(-2px)'; this.style.background='rgba(255,255,255,0.05)';" onmouseout="this.style.transform='translateY(0)'; this.style.background='rgba(0,0,0,0.3)';">
                                                        <div style="font-size: 0.95rem; font-weight: bold;">
                                                            {{ $card->displayName ?? $card->cardName }}
                                                            <div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.2rem; font-family: monospace;">ID: {{ $card->cardID }}</div>
                                                        </div>
                                                        <div style="color: var(--text-secondary); font-size: 0.85rem; white-space: nowrap; margin-left: 1rem;">
                                                            {{ str_repeat('⭐', $card->rarity ?? 1) }}
                                                        </div>
                                                    </div>
                                                </a>
                                            @endforeach
                                        </div>
                                    @else
                                        <div style="padding: 2rem; text-align: center; background: rgba(0,0,0,0.2); border-radius: 8px; border: 1px dashed var(--glass-border);">
                                            <i class="ph-light ph-empty" style="font-size: 2rem; color: var(--text-secondary); margin-bottom: 0.5rem;"></i>
                                            <div style="color: var(--text-secondary);">No cards were found for this claim.</div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @else
                            <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: var(--text-secondary); padding: 4rem;">
                                <i class="ph-light ph-hand-coins" style="font-size: 4rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                                <p style="font-size: 1.1rem;">Select a claim from the left to view its details.</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    @if($showRevealModal)
        <livewire:card-reveal :revealedCards="$revealedCards" />
    @endif
</div>
