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
    public array $tags = [];
    
    public $tagInput = '';

    #[Url]
    public string $filterOwned = '';
    
    #[Url]
    public string $filterFav = '';
    
    #[Url]
    public string $filterWish = '';

    public function addTag()
    {
        $input = strtolower(trim($this->tagInput));
        if ($input && !in_array($input, $this->tags)) {
            $this->tags[] = $input;
            $this->resetPage();
        }
        $this->tagInput = '';
    }

    public function removeTag($tag)
    {
        $this->tags = array_values(array_diff($this->tags, [$tag]));
        $this->resetPage();
    }
    
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
        if (in_array($property, ['search', 'rarity', 'collectionID', 'sortBy', 'sortDesc', 'owner', 'hidePromos', 'filterOwned', 'filterFav', 'filterWish'])) {
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

        if (!empty($this->tags)) {
            $matchedCardIDs = null;
            foreach ($this->tags as $t) {
                $ids = \App\Models\Tag::where('tagName', 'like', '%' . $t . '%')
                    ->where('status', 'clear')
                    ->pluck('cardID')
                    ->toArray();
                    
                if ($matchedCardIDs === null) {
                    $matchedCardIDs = $ids;
                } else {
                    $matchedCardIDs = array_intersect($matchedCardIDs, $ids);
                }
            }
            if (empty($matchedCardIDs)) {
                $query->where('cardID', -1);
            } else {
                $query->whereIn('cardID', $matchedCardIDs);
            }
        }
        
        if (auth()->check()) {
            if ($this->filterOwned !== '' || $this->filterFav !== '') {
                $userCardsQuery = UserCard::where('userID', auth()->user()->userID);
                $myUserCards = $userCardsQuery->get();
                
                if ($this->filterOwned === 'only') {
                    $query->whereIn('cardID', $myUserCards->pluck('cardID'));
                } elseif ($this->filterOwned === 'exclude') {
                    $query->whereNotIn('cardID', $myUserCards->pluck('cardID'));
                }

                if ($this->filterFav === 'only') {
                    $query->whereIn('cardID', $myUserCards->where('fav', true)->pluck('cardID'));
                } elseif ($this->filterFav === 'exclude') {
                    $query->whereNotIn('cardID', $myUserCards->where('fav', true)->pluck('cardID'));
                }
            }

            if ($this->filterWish !== '') {
                $myWishlists = \App\Models\UserWishlist::where('userID', auth()->user()->userID)->get();
                $wishCardIDs = $myWishlists->pluck('cardID')->map(fn($id) => (int)$id)->toArray();
                
                if ($this->filterWish === 'only') {
                    $query->whereIn('cardID', $wishCardIDs);
                } elseif ($this->filterWish === 'exclude') {
                    $query->whereNotIn('cardID', $wishCardIDs);
                }
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
        
        <!-- Filters Component -->
        <x-card-filters :collections="$collections" :tags="$tags" :sortDesc="$sortDesc" :hidePromos="$hidePromos" />
    </div>
    
    <x-card-grid :cards="$cards" :collections="$collections" :userOwned="$userOwned" :userFavs="$userFavs" :userWishlists="$userWishlists" />

    <!-- Pagination -->
    <div class="glass-panel" style="padding: 1rem; display: flex; justify-content: center;">
        {{ $cards->links('components.custom-pagination') }}
    </div>
</div>
