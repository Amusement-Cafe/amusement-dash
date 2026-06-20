<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Card;
use App\Models\BotCollection;
use App\Models\UserCard;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public $search = '';
    public $rarity = '';
    public $collectionID = '';
    public string $sortBy = 'rarity';
    public $sortDesc = true;
    public $hidePromos = false;
    
    #[Url]
    public string $owner = '';

    public $selectedCardId = null;
    public $showModal = false;

    public function updated($property)
    {
        if (in_array($property, ['search', 'rarity', 'collectionID', 'sortBy', 'sortDesc', 'owner', 'hidePromos'])) {
            $this->resetPage();
        }
    }

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
        $collections = Cache::remember('bot_collections_array', 3600, function() {
            return BotCollection::all()->keyBy('collectionID')->toArray();
        });

        $query = Card::query();

        $ownerUser = null;
        if ($this->owner) {
            $ownerUser = \App\Models\User::where('userID', $this->owner)->first();
            $ownerCardIDs = \App\Models\UserCard::where('userID', $this->owner)->pluck('cardID')->toArray();
            
            // If the user owns no cards, force an impossible condition so it returns 0 results cleanly
            if (empty($ownerCardIDs)) {
                $query->where('cardID', -1);
            } else {
                $query->whereIn('cardID', $ownerCardIDs);
            }
        }

        if ($this->search) {
            $query->where(function($q) {
                $q->where('cardName', 'like', '%' . $this->search . '%')
                  ->orWhere('displayName', 'like', '%' . $this->search . '%')
                  ->orWhere('cardID', (int)$this->search);
            });
        }

        if ($this->rarity !== '') {
            $query->where('rarity', (int) $this->rarity);
        }

        if ($this->hidePromos) {
            $promoCollectionIDs = collect($collections)->filter(function($col) { return !empty($col['promo']); })->keys()->toArray();
            $query->whereNotIn('collectionID', $promoCollectionIDs);
        }

        if ($this->collectionID !== '') {
            $query->where('collectionID', $this->collectionID);
        }

        $query->orderBy($this->sortBy, $this->sortDesc ? 'desc' : 'asc');
        $cards = $query->paginate(24);

        $userOwned = [];
        $userFavs = [];
        if (auth()->check()) {
            $userCards = UserCard::where('userID', auth()->user()->userID)
                ->whereIn('cardID', $cards->pluck('cardID'))
                ->get();
            
            foreach ($userCards as $uc) {
                $userOwned[$uc->cardID] = true;
                if ($uc->fav) {
                    $userFavs[$uc->cardID] = true;
                }
            }
        }

        $selectedCard = null;
        $selectedCardCollection = null;
        if ($this->selectedCardId) {
            $selectedCard = Card::where('cardID', (int) $this->selectedCardId)->first();
            if ($selectedCard) {
                $selectedCardCollection = $collections[$selectedCard->collectionID] ?? null;
            }
        }

        return [
            'cards' => $cards,
            'collections' => $collections,
            'userOwned' => $userOwned,
            'userFavs' => $userFavs,
            'selectedCard' => $selectedCard,
            'selectedCardCollection' => $selectedCardCollection,
            'ownerUser' => $ownerUser
        ];
    }
};
?>

<div>
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-size: 2.5rem; margin: 0;">{{ $owner ? ($ownerUser ? $ownerUser->username . "'s Cards" : "User " . $owner . "'s Cards") : 'All Cards Directory' }}</h1>
            @if($owner)
                <p style="color: var(--text-secondary); margin-top: 0.5rem;">Viewing collection of {{ $ownerUser ? $ownerUser->username : $owner }}</p>
            @endif
        </div>
        
        <!-- Filters -->
        <div class="glass-panel" style="padding: 1rem; display: flex; gap: 1rem; flex-wrap: wrap;">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search name or ID..." class="input-glass">
            
            <select wire:model.live="rarity" class="input-glass">
                <option value="">All Rarities</option>
                <option value="1">1 Star</option>
                <option value="2">2 Stars</option>
                <option value="3">3 Stars</option>
                <option value="4">4 Stars</option>
                <option value="5">5 Stars</option>
                <option value="6">6 Stars (Promo)</option>
            </select>
            
            <select wire:model.live="collectionID" class="input-glass" style="max-width: 200px;">
                <option value="">All Collections</option>
                @foreach($collections as $col)
                    <option value="{{ $col['collectionID'] }}">{{ $col['name'] }}</option>
                @endforeach
            </select>

            <select wire:model.live="sortBy" class="input-glass">
                <option value="cardID">Sort by ID</option>
                <option value="rarity">Sort by Rarity</option>
                <option value="dateAdded">Sort by Date</option>
                <option value="eval">Sort by Eval</option>
            </select>
            
            <button wire:click="$toggle('sortDesc')" class="input-glass" style="cursor: pointer; padding: 0.5rem 1rem; user-select: none;">
                {{ $sortDesc ? 'Descending ⬇️' : 'Ascending ⬆️' }}
            </button>

            <button wire:click="$toggle('hidePromos')" class="input-glass" style="cursor: pointer; padding: 0.5rem 1rem; user-select: none; background: {{ $hidePromos ? 'var(--accent-solid)' : 'var(--glass-bg)' }}; border-color: {{ $hidePromos ? 'var(--accent-solid)' : 'var(--glass-border)' }}; transition: all 0.2s;">
                {{ $hidePromos ? '🙈 Promos Hidden' : '👁️ Promos Shown' }}
            </button>
        </div>
    </div>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
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

    <!-- Pagination -->
    <div class="glass-panel" style="padding: 1rem;">
        {{ $cards->links() }}
    </div>

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
                            <a href="#" wire:click.prevent="$set('collectionID', '{{ $selectedCard->collectionID }}'); closeModal();" style="color: var(--accent-solid); text-decoration: none;">
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
