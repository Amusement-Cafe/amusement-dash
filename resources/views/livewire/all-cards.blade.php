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

    #[Url]
    public $search = '';
    
    #[Url]
    public $rarity = '';
    
    #[Url]
    public $collectionID = '';
    
    #[Url]
    public $tag = '';
    
    #[Url]
    public string $sortBy = 'rarity';
    
    #[Url]
    public $sortDesc = true;
    
    #[Url]
    public $hidePromos = false;
    
    #[Url]
    public string $owner = '';

    public function updated($property)
    {
        if (in_array($property, ['search', 'rarity', 'collectionID', 'tag', 'sortBy', 'sortDesc', 'owner', 'hidePromos'])) {
            $this->resetPage();
        }
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

        if ($this->tag) {
            $tagCardIDs = \App\Models\Tag::where('tagName', 'like', '%' . $this->tag . '%')
                ->where('status', 'clear')
                ->pluck('cardID')
                ->toArray();
                
            if (empty($tagCardIDs)) {
                $query->where('cardID', -1);
            } else {
                $query->whereIn('cardID', $tagCardIDs);
            }
        }

        $query->orderBy($this->sortBy, $this->sortDesc ? 'desc' : 'asc')->orderBy('cardID', 'asc');
        $cards = $query->paginate(24);

        $userOwned = [];
        $userFavs = [];
        $userWishlists = [];
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

            $stringIDs = $cards->pluck('cardID')->map(function($id) { return (string) $id; })->toArray();
            $wishlists = \App\Models\UserWishlist::where('userID', auth()->user()->userID)
                ->whereIn('cardID', $stringIDs)
                ->get();
            foreach ($wishlists as $w) {
                $userWishlists[(int)$w->cardID] = true;
            }
        }

        return [
            'cards' => $cards,
            'collections' => $collections,
            'userOwned' => $userOwned,
            'userFavs' => $userFavs,
            'userWishlists' => $userWishlists,
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
            
            <input type="text" wire:model.live.debounce.300ms="tag" placeholder="Search tags..." class="input-glass">
            
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
    
    <x-card-grid :cards="$cards" :collections="$collections" :userOwned="$userOwned" :userFavs="$userFavs" :userWishlists="$userWishlists" />

    <!-- Pagination -->
    <div class="glass-panel" style="padding: 1rem; display: flex; justify-content: center;">
        {{ $cards->links('components.custom-pagination') }}
    </div>
</div>
