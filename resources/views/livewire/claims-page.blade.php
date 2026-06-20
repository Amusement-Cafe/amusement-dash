<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Url;
use App\Models\Claim;
use App\Models\Card;
use App\Models\BotCollection;
use Livewire\Attributes\Layout;
use MongoDB\BSON\ObjectId;

new #[Layout('layouts.app')] class extends Component
{
    #[Url]
    public string $id = '';

    public $selectedCardId = null;
    public $showModal = false;

    public function openCardModal($id)
    {
        $this->selectedCardId = $id;
        $this->showModal = true;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->selectedCardId = null;
    }

    public function with(): array
    {
        if (empty($this->id)) {
            return ['claim' => null, 'claimUser' => null, 'cards' => [], 'collections' => [], 'userOwned' => [], 'userFavs' => [], 'selectedCard' => null, 'selectedCardCollection' => null];
        }

        $query = Claim::where('claimID', $this->id);
        
        try {
            if (strlen($this->id) === 24) {
                $query->orWhere('_id', new ObjectId($this->id));
            }
        } catch (\Exception $e) {}

        $claim = $query->first();

        $cards = collect();
        $collections = [];
        $claimUser = null;
        $userOwned = [];
        $userFavs = [];

        if ($claim) {
            $claimUser = \App\Models\User::where('userID', $claim->userID)->first();
            
            if (!empty($claim->cardIDs)) {
                $cards = Card::whereIn('cardID', $claim->cardIDs)->get();
                $collectionIDs = $cards->pluck('collectionID')->unique()->toArray();
                $collections = BotCollection::whereIn('collectionID', $collectionIDs)->get()->keyBy('collectionID')->toArray();

                if (auth()->check()) {
                    $userCards = \App\Models\UserCard::where('userID', auth()->user()->userID)
                        ->whereIn('cardID', $cards->pluck('cardID'))
                        ->get();
                    
                    foreach ($userCards as $uc) {
                        $userOwned[$uc->cardID] = true;
                        if ($uc->fav) {
                            $userFavs[$uc->cardID] = true;
                        }
                    }
                }
            }
        }

        $selectedCard = null;
        $selectedCardCollection = null;
        if ($this->selectedCardId) {
            $selectedCard = $cards->firstWhere('cardID', (int) $this->selectedCardId);
            if ($selectedCard) {
                $selectedCardCollection = $collections[$selectedCard->collectionID] ?? null;
            }
        }

        return [
            'claim' => $claim,
            'claimUser' => $claimUser,
            'cards' => $cards,
            'collections' => $collections,
            'userOwned' => $userOwned,
            'userFavs' => $userFavs,
            'selectedCard' => $selectedCard,
            'selectedCardCollection' => $selectedCardCollection
        ];
    }
};
?>

<div>

    @if(empty($id))
        <div class="glass-panel" style="padding: 3rem; text-align: center;">
            <div style="font-size: 3rem; margin-bottom: 1rem;">🔍</div>
            <h2 style="margin-bottom: 0.5rem;">No Claim Specified</h2>
            <p style="color: var(--text-secondary);">Please provide a valid claim ID in the URL parameter (e.g. ?id=xyz).</p>
        </div>
    @elseif(!$claim)
        <div class="glass-panel" style="padding: 3rem; text-align: center; border-color: rgba(239, 68, 68, 0.3);">
            <div style="font-size: 3rem; margin-bottom: 1rem;">❌</div>
            <h2 style="margin-bottom: 0.5rem; color: #f87171;">Claim Not Found</h2>
            <p style="color: var(--text-secondary);">We couldn't find a claim matching ID: <strong>{{ $id }}</strong></p>
        </div>
    @else
        <!-- Claim Summary -->
        <div class="glass-panel" style="padding: 2rem; margin-bottom: 2rem; display: flex; flex-wrap: wrap; gap: 2rem;">
            <div style="flex: 1; min-width: 250px;">
                <h3 style="color: var(--text-secondary); font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px;">Claim ID</h3>
                <p style="font-size: 1.5rem; font-weight: 600; color: var(--accent-glow);">{{ $claim->claimID }}</p>
            </div>
            <div style="flex: 1; min-width: 250px;">
                <h3 style="color: var(--text-secondary); font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px;">User</h3>
                <p style="font-size: 1.2rem;">
                    {{ $claimUser ? $claimUser->username : $claim->userID }}
                </p>
            </div>
            <div style="flex: 1; min-width: 250px;">
                <h3 style="color: var(--text-secondary); font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px;">Cost</h3>
                <p style="font-size: 1.2rem;">{{ $claim->cost ?? 'Free' }}</p>
            </div>
            <div style="flex: 1; min-width: 250px;">
                <h3 style="color: var(--text-secondary); font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px;">Date Claimed</h3>
                <p style="font-size: 1.2rem;">
                    {{ $claim->timeClaimed ? \Carbon\Carbon::parse($claim->timeClaimed)->format('M d, Y - H:i:s') : 'Unknown' }}
                </p>
            </div>
            @if($claim->promo)
            <div style="flex: 1; min-width: 250px;">
                <h3 style="color: var(--text-secondary); font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px;">Promo Claim</h3>
                <p style="font-size: 1.2rem; color: #f472b6;">Yes 🎁</p>
            </div>
            @endif
        </div>

        <!-- Claimed Cards -->
        <h2 style="font-size: 1.8rem; margin-bottom: 1.5rem;">Cards Claimed ({{ count($cards) }})</h2>
        
        @if(count($cards) > 0)
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 1.5rem;">
                @foreach($cards as $card)
                    @php 
                        $col = $collections[$card->collectionID] ?? null; 
                        $owned = $userOwned[$card->cardID] ?? false;
                        $fav = $userFavs[$card->cardID] ?? false;
                    @endphp
                    <div wire:click="openCardModal({{ $card->cardID }})">
                        <x-card-viewer :card="$card" :collectionName="$col['name'] ?? null" :owned="$owned" :fav="$fav" />
                    </div>
                @endforeach
            </div>
        @else
            <div class="glass-panel" style="padding: 2rem; text-align: center;">
                <p style="color: var(--text-secondary);">This claim does not contain any cards.</p>
            </div>
        @endif
    @endif

    <!-- Card Modal -->
    @if($showModal && $selectedCard)
    @teleport('body')
    <div class="modal-overlay" wire:click.self="closeModal">
        <div class="modal-content glass-panel" style="display: flex; flex-direction: row; flex-wrap: wrap; padding: 2rem; gap: 2rem; position: relative;">
            <button wire:click="closeModal" style="position: absolute; top: 1rem; right: 1rem; background: transparent; border: none; color: white; font-size: 1.5rem; cursor: pointer;">&times;</button>
            
            <!-- Left Side: Image -->
            <div style="flex: 1; min-width: 300px; display: flex; align-items: center; justify-content: center; background: transparent; border-radius: 8px; padding: 1rem;">
                @if($selectedCard->cardURL)
                    <img src="{{ $selectedCard->cardURL }}" alt="{{ $selectedCard->displayName ?? $selectedCard->cardName }}" style="max-width: 100%; max-height: 500px; object-fit: contain;">
                @else
                    <span style="color: var(--text-secondary);">No Image Available</span>
                @endif
            </div>
            
            <!-- Right Side: Info -->
            <div style="flex: 1; min-width: 300px; display: flex; flex-direction: column; justify-content: center;">
                <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 0.5rem;">
                    <h2 style="font-size: 2rem; margin: 0;">{{ $selectedCard->displayName ?? $selectedCard->cardName }}</h2>
                    @if(isset($userOwned[$selectedCard->cardID]))
                        <span style="background: rgba(16, 185, 129, 0.2); color: #34d399; padding: 4px 8px; border-radius: 4px; font-size: 0.85rem;">Owned</span>
                    @endif
                    @if(isset($userFavs[$selectedCard->cardID]))
                        <span style="background: rgba(236, 72, 153, 0.2); color: #f472b6; padding: 4px 8px; border-radius: 4px; font-size: 0.85rem;">Favorited ❤️</span>
                    @endif
                </div>
                
                <p style="color: var(--text-secondary); font-size: 1.2rem; margin-bottom: 1.5rem;">
                    {{ str_repeat('⭐', $selectedCard->rarity ?? 1) }} | ID: #{{ $selectedCard->cardID }}
                </p>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 2rem;">
                    <div class="glass-panel" style="padding: 1rem; border: 1px dashed var(--glass-border);">
                        <p style="color: var(--text-secondary); font-size: 0.9rem;">Collection</p>
                        <p style="font-size: 1.1rem; font-weight: 600;">
                            <a href="{{ route('cards.index') }}?collectionID={{ $selectedCard->collectionID }}" style="color: var(--accent-solid); text-decoration: none;">
                                {{ $selectedCardCollection['name'] ?? $selectedCard->collectionID }}
                            </a>
                        </p>
                    </div>
                    <div class="glass-panel" style="padding: 1rem; border: 1px dashed var(--glass-border);">
                        <p style="color: var(--text-secondary); font-size: 0.9rem;">Eval</p>
                        <p style="font-size: 1.1rem; font-weight: 600;">{{ $selectedCard->eval ?? 'N/A' }} 🍅</p>
                    </div>
                    <div class="glass-panel" style="padding: 1rem; border: 1px dashed var(--glass-border);">
                        <p style="color: var(--text-secondary); font-size: 0.9rem;">Total Copies</p>
                        <p style="font-size: 1.1rem; font-weight: 600;">{{ $selectedCard->stats['totalCopies'] ?? 'N/A' }}</p>
                    </div>
                    <div class="glass-panel" style="padding: 1rem; border: 1px dashed var(--glass-border);">
                        <p style="color: var(--text-secondary); font-size: 0.9rem;">Rating</p>
                        <p style="font-size: 1.1rem; font-weight: 600;">
                            @if(($selectedCard->timesRated ?? 0) > 0)
                                {{ number_format(($selectedCard->ratingSum ?? 0) / $selectedCard->timesRated, 1) }} / 5 
                                <span style="font-size: 0.8rem; color: var(--text-secondary);">({{ $selectedCard->timesRated }} votes)</span>
                            @else
                                No Ratings
                            @endif
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endteleport
    @endif
</div>
