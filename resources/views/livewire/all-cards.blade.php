<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Card;
use App\Models\BotCollection;
use App\Models\UserCard;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Attributes\On;

use Livewire\Attributes\Title;

new #[Layout('layouts.app')] #[Title('Cards')] class extends Component
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
    
    #[Url]
    public string $transactionID = '';
    
    #[Url]
    public string $claimID = '';

    public $randomSeed;

    public function mount()
    {
        $this->randomSeed = session()->get('cards_random_seed', mt_rand());
        session()->put('cards_random_seed', $this->randomSeed);
        
        if ($this->search !== '' && $this->sortBy === 'random') {
            $this->sortBy = 'rarity';
        }
    }

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
    
    public function clearFilters()
    {
        $this->search = '';
        $this->rarity = '';
        $this->collectionID = '';
        $this->tags = [];
        $this->tagInput = '';
        $this->filterOwned = '';
        $this->filterFav = '';
        $this->filterWish = '';
        $this->hidePromos = false;
        $this->resetPage();
    }
    
    #[Url]
    public string $sortBy = 'random';
    
    #[Url]
    public $sortDesc = true;
    
    #[Url]
    public $hidePromos = false;
    
    #[Url]
    public string $owner = '';

    public function updatingPage()
    {
        $this->dispatch('scroll-to-top');
    }

    #[On('card-updated')]
    public function refreshCardData()
    {
        // Triggers a re-render to update the grid with fresh database state
    }

    public function updated($property)
    {
        if (in_array($property, ['search', 'rarity', 'collectionID', 'sortBy', 'sortDesc', 'owner', 'hidePromos', 'filterOwned', 'filterFav', 'filterWish', 'transactionID', 'claimID'])) {
            $this->resetPage();
        }
        
        if ($property === 'search' && $this->search !== '' && $this->sortBy === 'random') {
            $this->sortBy = 'rarity';
        }
        
        if ($property === 'sortBy' && $this->sortBy === 'random') {
            $this->randomSeed = mt_rand();
            session()->put('cards_random_seed', $this->randomSeed);
        }
        
        $this->dispatch('scroll-to-top');
    }

    public function with(): array
    {
        $collections = Cache::remember('bot_collections_array', 3600, function() {
            return BotCollection::all()->keyBy('collectionID')->toArray();
        });

        $query = Card::query();

        $ownerUser = null;
        $ownerAvatar = null;
        if ($this->owner) {
            $ownerUser = \App\Models\User::where('userID', $this->owner)->first();
            $ownerCardIDs = \App\Models\UserCard::where('userID', $this->owner)->pluck('cardID')->toArray();
            
            if ($ownerUser) {
                $avatarIndex = is_numeric($ownerUser->userID) ? (substr($ownerUser->userID, -1) % 6) : 0;
                $defaultAvatar = "https://cdn.discordapp.com/embed/avatars/{$avatarIndex}.png";
                
                $ownerAvatar = Cache::remember('discord_avatar_' . $ownerUser->userID, 86400, function() use ($ownerUser, $defaultAvatar) {
                    $botToken = env('DISCORD_BOT_TOKEN');
                    if (!$botToken) return $defaultAvatar;
                    
                    $response = \Illuminate\Support\Facades\Http::withHeaders([
                        'Authorization' => "Bot {$botToken}"
                    ])->get("https://discord.com/api/users/{$ownerUser->userID}");
                    
                    if ($response->successful() && !empty($response->json('avatar'))) {
                        $hash = $response->json('avatar');
                        $ext = str_starts_with($hash, 'a_') ? 'gif' : 'png';
                        return "https://cdn.discordapp.com/avatars/{$ownerUser->userID}/{$hash}.{$ext}?size=256";
                    }
                    return $defaultAvatar;
                });
            }

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
                  ->orWhere('displayName', 'like', '%' . $this->search . '%');
                  
                if (is_numeric($this->search)) {
                    $q->orWhere('cardID', (int)$this->search);
                }
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

        if ($this->transactionID !== '') {
            $tx = \App\Models\Transaction::where('transactionID', $this->transactionID)->orWhere('_id', $this->transactionID)->first();
            if ($tx && !empty($tx->cardIDs)) {
                $query->whereIn('cardID', $tx->cardIDs);
            } else {
                $query->where('cardID', -1);
            }
        }

        if ($this->claimID !== '') {
            $claim = \App\Models\Claim::where('claimID', $this->claimID)->orWhere('_id', $this->claimID)->first();
            if ($claim && !empty($claim->cardIDs)) {
                $query->whereIn('cardID', $claim->cardIDs);
            } else {
                $query->where('cardID', -1);
            }
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
                
                if ($this->filterOwned === 'exclude') {
                    $query->whereNotIn('cardID', $myUserCards->pluck('cardID'));
                } elseif ($this->filterOwned === 'only' || is_numeric($this->filterOwned)) {
                    $minCopies = $this->filterOwned === 'only' ? 1 : (int)$this->filterOwned;
                    $validCardIDs = $myUserCards->filter(function($uc) use ($minCopies) {
                        return ($uc->amount ?? 1) >= $minCopies;
                    })->pluck('cardID');
                    $query->whereIn('cardID', $validCardIDs);
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

        if ($this->sortBy === 'random') {
            $cardIDs = $query->pluck('cardID')->toArray();
            mt_srand($this->randomSeed ?? mt_rand());
            shuffle($cardIDs);
            mt_srand();
            
            $page = $this->getPage();
            $perPage = 24;
            $slicedIds = array_slice($cardIDs, ($page - 1) * $perPage, $perPage);
            
            if (empty($slicedIds)) {
                $cards = new \Illuminate\Pagination\LengthAwarePaginator(collect(), count($cardIDs), $perPage, $page, [
                    'path' => \Illuminate\Pagination\Paginator::resolveCurrentPath(),
                ]);
            } else {
                $fetchedCards = Card::whereIn('cardID', $slicedIds)->get()->sortBy(function($card) use ($slicedIds) {
                    return array_search($card->cardID, $slicedIds);
                })->values();
                
                $cards = new \Illuminate\Pagination\LengthAwarePaginator($fetchedCards, count($cardIDs), $perPage, $page, [
                    'path' => \Illuminate\Pagination\Paginator::resolveCurrentPath(),
                ]);
            }
        } else {
            $query->orderBy($this->sortBy, $this->sortDesc ? 'desc' : 'asc')->orderBy('cardID', 'asc');
            $cards = $query->paginate(24);
        }

        $userOwned = [];
        $userFavs = [];
        $userWishlists = [];
        if (auth()->check()) {
            $userCards = UserCard::where('userID', auth()->user()->userID)
                ->whereIn('cardID', $cards->pluck('cardID'))
                ->get();
            
            foreach ($userCards as $uc) {
                $userOwned[$uc->cardID] = $uc->amount ?? 1;
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

        $activeFiltersCount = 0;
        if ($this->search !== '') $activeFiltersCount++;
        if ($this->rarity !== '') $activeFiltersCount++;
        if ($this->collectionID !== '') $activeFiltersCount++;
        if (!empty($this->tags)) $activeFiltersCount++;
        if ($this->hidePromos) $activeFiltersCount++;
        if ($this->filterOwned !== '') $activeFiltersCount++;
        if ($this->filterFav !== '') $activeFiltersCount++;
        if ($this->filterWish !== '') $activeFiltersCount++;

        $allTagsList = Cache::remember('all_distinct_tags', 3600, function() {
            $rawTags = \App\Models\Tag::raw(function($collection) {
                return $collection->distinct('tagName', ['status' => 'clear']);
            });
            
            return array_values(array_filter((array)$rawTags, function($tag) {
                return is_string($tag) && strlen(trim($tag)) > 0;
            }));
        });

        $activeAuctions = \App\Models\Auction::where('ended', false)
            ->where('cancelled', false)
            ->whereIn('cardID', $cards->pluck('cardID'))
            ->get();
            
        $cardAuctions = [];
        foreach ($activeAuctions as $auction) {
            if (!isset($cardAuctions[$auction->cardID]) || $cardAuctions[$auction->cardID]->price > $auction->price) {
                $cardAuctions[$auction->cardID] = $auction;
            }
        }

        return [
            'cards' => $cards,
            'collections' => $collections,
            'userOwned' => $userOwned,
            'userFavs' => $userFavs,
            'userWishlists' => $userWishlists,
            'cardAuctions' => $cardAuctions,
            'ownerUser' => $ownerUser,
            'ownerAvatar' => $ownerAvatar,
            'activeFiltersCount' => $activeFiltersCount,
            'allTags' => $allTagsList
        ];
    }
};
?>

<div x-data @scroll-to-top.window="document.getElementById('card-directory-top').scrollIntoView({ behavior: 'smooth' })">
    <div id="card-directory-top" style="margin-bottom: 1rem;">
        @if($owner)
            <div style="display: flex; align-items: center; gap: 1rem;">
                <img src="{{ $ownerAvatar }}" alt="Avatar" style="width: 64px; height: 64px; border-radius: 50%; object-fit: cover; border: 2px solid var(--accent-solid); box-shadow: 0 0 15px rgba(99, 102, 241, 0.3);">
                <h1 style="font-size: 2.5rem; margin: 0; display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                    <span style="color: var(--accent-solid); text-shadow: 0 0 10px rgba(99, 102, 241, 0.3);">{{ $ownerUser ? $ownerUser->username : "User " . $owner }}</span>'s Cards
                    <span style="color: var(--text-secondary); font-size: 1.5rem; font-weight: normal;">({{ number_format($cards->total()) }})</span>
                </h1>
            </div>
        @else
            <h1 style="font-size: 2.5rem; margin: 0; display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                All Cards Directory 
                <span style="color: var(--text-secondary); font-size: 1.5rem; font-weight: normal;">({{ number_format($cards->total()) }})</span>
            </h1>
        @endif
    </div>
    
    <!-- Filters Component -->
    <x-card-filters :collections="$collections" :tags="$tags" :sortDesc="$sortDesc" :hidePromos="$hidePromos" :activeFiltersCount="$activeFiltersCount" :allTags="$allTags" />
    
    <div wire:loading.remove>
        <x-card-grid :cards="$cards" :collections="$collections" :userOwned="$userOwned" :userFavs="$userFavs" :userWishlists="$userWishlists" :cardAuctions="$cardAuctions" />
    </div>
    
    <div wire:loading style="width: 100%;">
        <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 5rem 0;">
            <i class="ph-bold ph-spinner" style="font-size: 4rem; color: var(--accent-solid); animation: spin 1s linear infinite;"></i>
            <p style="color: var(--text-secondary); margin-top: 1rem; font-size: 1.2rem;">Loading cards...</p>
        </div>
    </div>

    <!-- Pagination -->
    @if($cards->hasPages())
        <div class="glass-panel" style="padding: 1rem; display: flex; justify-content: center;">
            {{ $cards->links('components.custom-pagination') }}
        </div>
    @endif
</div>
