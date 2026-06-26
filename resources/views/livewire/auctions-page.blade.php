<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Auction;
use App\Models\Card;
use App\Models\User;
use App\Models\BotCollection;
use App\Models\UserCard;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;

use Livewire\Attributes\Title;

new #[Layout('layouts.app')] #[Title('Auctions')] class extends Component
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
    public string $sortBy = 'expires';
    
    #[Url]
    public $sortDesc = false;
    
    #[Url]
    public $hidePromos = false;

    public $selectedAuctionId = null;
    public $showModal = false;
    public $bidAmount = 0;

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

    public function updated($property)
    {
        if (in_array($property, ['search', 'rarity', 'collectionID', 'sortBy', 'sortDesc', 'hidePromos', 'filterOwned', 'filterFav', 'filterWish'])) {
            $this->resetPage();
        }
    }

    public function openAuctionModal($id)
    {
        $this->selectedAuctionId = $id;
        $auction = Auction::where('auctionID', $id)->first();
        if ($auction) {
            $this->bidAmount = $auction->price + 1;
        }
        $this->showModal = true;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->selectedAuctionId = null;
    }
    
    public function incrementBid() {
        $this->bidAmount += 1;
    }
    
    public function decrementBid() {
        $auction = Auction::where('auctionID', $this->selectedAuctionId)->first();
        if ($auction) {
            $minBid = $auction->price + 1;
            if ($this->bidAmount - 1 >= $minBid) {
                $this->bidAmount -= 1;
            } else {
                $this->bidAmount = $minBid;
            }
        }
    }

    public function placeBid()
    {
        if (!auth()->check() || !$this->selectedAuctionId) return;
        
        $user = User::where('userID', auth()->user()->userID)->first();
        $auction = Auction::where('auctionID', $this->selectedAuctionId)->first();
        
        if (!$auction || $auction->ended || $auction->cancelled) return;
        if ($this->bidAmount <= $auction->highBid) return;
        if ($user->tomatoes < $this->bidAmount) return;

        // Refund previous bidder
        if ($auction->lastBidderID) {
            User::where('userID', $auction->lastBidderID)->increment('tomatoes', $auction->highBid);
        }

        // Deduct tomatoes
        $user->decrement('tomatoes', $this->bidAmount);

        // Update auction
        $newPrice = $auction->highBid > 0 ? $auction->highBid + 1 : $auction->price;
        if ($newPrice > $this->bidAmount) $newPrice = $this->bidAmount;

        $auction->price = $newPrice;
        $auction->highBid = $this->bidAmount;
        $auction->lastBidderID = $user->userID;
        
        $bids = $auction->bids ?? [];
        $bids[] = [
            'user' => $user->userID,
            'bid' => $this->bidAmount,
            'time' => new \MongoDB\BSON\UTCDateTime()
        ];
        $auction->bids = $bids;
        $auction->save();

        $this->closeModal();
    }

    public function with(): array
    {
        $auctionQuery = Auction::where('ended', false)->where('cancelled', false);

        $collections = Cache::remember('bot_collections_array', 3600, function() {
            return BotCollection::all()->keyBy('collectionID')->toArray();
        });

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

        if ($activeFiltersCount > 0) {
            $allAuctionCardIDs = Auction::where('ended', false)->where('cancelled', false)->distinct('cardID')->pluck('cardID')->toArray();
            
            $cardQuery = Card::whereIn('cardID', $allAuctionCardIDs);

            if ($this->search) {
                $cardQuery->where(function($q) {
                    $q->where('cardName', 'like', '%' . $this->search . '%')
                      ->orWhere('displayName', 'like', '%' . $this->search . '%');
                      
                    if (is_numeric($this->search)) {
                        $q->orWhere('cardID', (int)$this->search);
                    }
                });
            }

            if ($this->rarity !== '') {
                $cardQuery->where('rarity', (int) $this->rarity);
            }

            if ($this->collectionID !== '') {
                $cardQuery->where('collectionID', $this->collectionID);
            }

            if ($this->hidePromos) {
                $promoCollectionIDs = collect($collections)->filter(function($col) { return !empty($col['promo']); })->keys()->toArray();
                $cardQuery->whereNotIn('collectionID', $promoCollectionIDs);
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
                    $cardQuery->where('cardID', -1);
                } else {
                    $cardQuery->whereIn('cardID', $matchedCardIDs);
                }
            }

            if (auth()->check()) {
                if ($this->filterOwned !== '' || $this->filterFav !== '') {
                    $userCardsQuery = UserCard::where('userID', auth()->user()->userID);
                    $myUserCards = $userCardsQuery->get();
                    
                    if ($this->filterOwned === 'exclude') {
                        $cardQuery->whereNotIn('cardID', $myUserCards->pluck('cardID'));
                    } elseif ($this->filterOwned === 'only' || is_numeric($this->filterOwned)) {
                        $minCopies = $this->filterOwned === 'only' ? 1 : (int)$this->filterOwned;
                        $validCardIDs = $myUserCards->filter(function($uc) use ($minCopies) {
                            return ($uc->amount ?? 1) >= $minCopies;
                        })->pluck('cardID');
                        $cardQuery->whereIn('cardID', $validCardIDs);
                    }

                    if ($this->filterFav === 'only') {
                        $cardQuery->whereIn('cardID', $myUserCards->where('fav', true)->pluck('cardID'));
                    } elseif ($this->filterFav === 'exclude') {
                        $cardQuery->whereNotIn('cardID', $myUserCards->where('fav', true)->pluck('cardID'));
                    }
                }

                if ($this->filterWish !== '') {
                    $myWishlists = \App\Models\UserWishlist::where('userID', auth()->user()->userID)->get();
                    $wishCardIDs = $myWishlists->pluck('cardID')->map(fn($id) => (int)$id)->toArray();
                    
                    if ($this->filterWish === 'only') {
                        $cardQuery->whereIn('cardID', $wishCardIDs);
                    } elseif ($this->filterWish === 'exclude') {
                        $cardQuery->whereNotIn('cardID', $wishCardIDs);
                    }
                }
            }
            
            $filteredCardIDs = $cardQuery->pluck('cardID')->toArray();
            
            if (empty($filteredCardIDs)) {
                $auctionQuery->where('cardID', -1);
            } else {
                $auctionQuery->whereIn('cardID', $filteredCardIDs);
            }
        }

        if ($this->sortBy === 'expires') {
            $auctionQuery->orderBy('expires', $this->sortDesc ? 'desc' : 'asc');
        } elseif ($this->sortBy === 'price') {
            $auctionQuery->orderBy('price', $this->sortDesc ? 'desc' : 'asc');
        } else {
            $auctionQuery->orderBy('expires', 'asc');
        }
        
        $auctions = $auctionQuery->paginate(16);

        $cardIDs = $auctions->pluck('cardID')->unique()->toArray();
        $userIDs = $auctions->pluck('userID')->unique()->toArray();

        $cards = Card::whereIn('cardID', $cardIDs)->get()->keyBy('cardID');
        $sellers = User::whereIn('userID', $userIDs)->get()->keyBy('userID');

        $userOwned = [];
        $userFavs = [];
        if (auth()->check()) {
            $userCards = UserCard::where('userID', auth()->user()->userID)
                ->whereIn('cardID', $cardIDs)
                ->get();
            
            foreach ($userCards as $uc) {
                $userOwned[$uc->cardID] = $uc->amount ?? 1;
                if ($uc->fav) {
                    $userFavs[$uc->cardID] = true;
                }
            }
        }

        $selectedAuction = null;
        $selectedCard = null;
        $selectedCardCollection = null;
        $sellerUser = null;
        
        if ($this->selectedAuctionId) {
            $selectedAuction = Auction::where('auctionID', $this->selectedAuctionId)->first();
            if ($selectedAuction) {
                $selectedCard = Card::where('cardID', $selectedAuction->cardID)->first();
                if ($selectedCard) {
                    $selectedCardCollection = $collections[$selectedCard->collectionID] ?? null;
                }
                $sellerUser = User::where('userID', $selectedAuction->userID)->first();
            }
        }

        $sortOptions = [
            'expires' => 'Ending Soon',
            'price' => 'Price'
        ];

        return [
            'auctions' => $auctions,
            'cards' => $cards,
            'sellers' => $sellers,
            'collections' => $collections,
            'userOwned' => $userOwned,
            'userFavs' => $userFavs,
            'selectedAuction' => $selectedAuction,
            'selectedCard' => $selectedCard,
            'selectedCardCollection' => $selectedCardCollection,
            'sellerUser' => $sellerUser,
            'activeFiltersCount' => $activeFiltersCount,
            'sortOptions' => $sortOptions,
            'allTags' => $allTagsList
        ];
    }
};
?>

<div>
    <div style="margin-bottom: 2rem;">
        <h1 style="font-size: 2.5rem; margin: 0;">Live Auctions ({{ number_format($auctions->total()) }} running)</h1>
    </div>

    <!-- Filters Component -->
    <x-card-filters :collections="$collections" :tags="$tags" :sortDesc="$sortDesc" :hidePromos="$hidePromos" :activeFiltersCount="$activeFiltersCount" :sortOptions="$sortOptions" :allTags="$allTags" />

    @if($auctions->count() > 0)
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 2rem; margin-bottom: 2rem;">
            @foreach($auctions as $auction)
                @php 
                    $card = $cards->get($auction->cardID);
                    $seller = $sellers->get($auction->userID);
                    
                    $myBidStatus = null;
                    if(auth()->check()) {
                        $uid = auth()->user()->userID;
                        $hasBid = collect($auction->bids)->contains('user', $uid);
                        if($hasBid) {
                            if($auction->lastBidderID === $uid) {
                                $myBidStatus = 'active';
                            } else {
                                $myBidStatus = 'outbid';
                            }
                        }
                    }
                    
                    $borderColor = 'var(--glass-border)';
                    if ($myBidStatus === 'active') {
                        $borderColor = '#10b981'; // green
                    } elseif ($myBidStatus === 'outbid') {
                        $borderColor = '#ef4444'; // red
                    }
                @endphp
                
                <div x-data="{ hovered: false }" @mouseenter="hovered = true" @mouseleave="hovered = false" class="glass-panel" style="padding: 1rem; border: 1px solid {{ $borderColor }}; position: relative; overflow: hidden; {{ $myBidStatus === 'active' ? 'box-shadow: 0 0 15px rgba(16, 185, 129, 0.2);' : '' }} {{ $myBidStatus === 'outbid' ? 'box-shadow: 0 0 15px rgba(239, 68, 68, 0.2);' : '' }}">
                    
                    <!-- Hover Overlay over the entire auction card -->
                    <div x-show="hovered" x-transition.opacity.duration.200ms style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px); z-index: 20; border-radius: 16px;">
                        <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;">
                            <button wire:click="openAuctionModal('{{ $auction->auctionID }}')" class="btn btn-primary" style="background: var(--accent-solid); color: white; border-radius: 9999px; padding: 0.6rem 1.5rem; font-weight: bold; border: none; cursor: pointer; box-shadow: 0 4px 15px var(--accent-glow); display: flex; align-items: center; gap: 0.5rem; transform: scale(1.1);">
                                <i class="ph-bold ph-eye"></i> View Details
                            </button>
                        </div>
                    </div>

                    <!-- Auction Header -->
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; position: relative; z-index: 30;">
                        <span style="background: rgba(245, 158, 11, 0.2); color: #fbbf24; padding: 4px 8px; border-radius: 4px; font-size: 0.8rem; font-weight: bold;">
                            {{ number_format($auction->price) }} 🍅
                        </span>
                        
                        <button style="background: rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.1); color: var(--text-secondary); padding: 4px 8px; border-radius: 4px; font-size: 0.8rem; font-weight: bold; cursor: pointer; display: flex; align-items: center; gap: 0.3rem; transition: background 0.2s, color 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.1)'; this.style.color='white';" onmouseout="this.style.background='rgba(0,0,0,0.5)'; this.style.color='var(--text-secondary)';" onclick="navigator.clipboard.writeText('{{ $auction->auctionID }}'); Livewire.dispatch('notify', { message: 'Copied Auction ID!' });" title="Copy ID">
                            <i class="ph-bold ph-copy"></i> Copy ID
                        </button>
                    </div>

                    @if($card)
                        @php
                            $col = $collections[$card->collectionID] ?? null;
                            $owned = $userOwned[$card->cardID] ?? false;
                            $fav = $userFavs[$card->cardID] ?? false;
                        @endphp
                        
                        <x-card-viewer :card="$card" :collectionName="$col['name'] ?? null" :owned="$owned" :fav="$fav" />
                    @else
                        <div style="height: 250px; background: rgba(0,0,0,0.2); display: flex; align-items: center; justify-content: center; border-radius: 8px; margin-bottom: 1rem;">
                            <span style="color: var(--text-secondary);">Card #{{ $auction->cardID }} not found</span>
                        </div>
                    @endif

                    <!-- Seller Info -->
                    <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid rgba(255,255,255,0.05); text-align: center;">
                        <p style="font-size: 0.9rem; color: var(--text-secondary);">
                            Seller: 
                            <a href="/profile?id={{ $auction->userID }}" style="color: var(--accent-solid); text-decoration: none; font-weight: bold;">
                                {{ $seller ? $seller->username : $auction->userID }}
                            </a>
                        </p>
                        <p style="font-size: 0.8rem; color: var(--text-secondary); margin-top: 0.3rem;">
                            Ends: {{ \Carbon\Carbon::parse($auction->expires)->diffForHumans() }}
                        </p>
                    </div>
                </div>
            @endforeach
        </div>

        @if($auctions->hasPages())
            <div class="glass-panel" style="padding: 1rem; display: flex; justify-content: center;">
                {{ $auctions->links('components.custom-pagination') }}
            </div>
        @endif
    @else
        <div class="glass-panel" style="padding: 3rem; text-align: center;">
            <p style="color: var(--text-secondary); font-size: 1.2rem;">No active auctions running right now.</p>
        </div>
    @endif

    <!-- Auction Details Modal -->
    @if($showModal && $selectedAuction && $selectedCard)
    @teleport('body')
    <div class="modal-overlay" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); z-index: 1000; align-items: center; justify-content: center; display: flex; padding: 1rem;" wire:click.self="closeModal">
        <div class="modal-content glass-panel" style="display: flex; flex-direction: row; flex-wrap: wrap; padding: 2rem; gap: 2rem; position: relative; max-width: 1000px; width: 100%; max-height: 90vh; overflow-y: auto; background: var(--bg-dark);">
            <button wire:click="closeModal" style="position: absolute; top: 1rem; right: 1rem; background: transparent; border: none; color: white; font-size: 1.5rem; cursor: pointer;">&times;</button>
            
            <div style="flex: 1; min-width: 300px; display: flex; align-items: flex-start; justify-content: center; background: transparent; border-radius: 8px; padding: 1rem; position: sticky; top: 0;">
                @if($selectedCard->cardURL)
                    <img src="{{ $selectedCard->cardURL }}" alt="{{ $selectedCard->displayName ?? $selectedCard->cardName }}" style="max-width: 100%; max-height: 500px; object-fit: contain;">
                @else
                    <span style="color: var(--text-secondary);">No Image Available</span>
                @endif
            </div>
            
            <div style="flex: 1; min-width: 300px; display: flex; flex-direction: column; justify-content: center;">
                <div style="display: flex; align-items: center; gap: 0.8rem; margin-bottom: 0.5rem;">
                    <h2 style="font-size: 2rem; margin: 0;">{{ $selectedCard->displayName ?? $selectedCard->cardName }}</h2>
                    <div style="display: flex; gap: 0.5rem;">
                        @if(isset($userOwned[$selectedCard->cardID]))
                            <i class="ph-fill ph-check-circle" title="Owned" style="color: #34d399; font-size: 1.8rem; display: flex; align-items: center; justify-content: center; background: rgba(16, 185, 129, 0.2); border-radius: 50%; padding: 4px;"></i>
                        @endif
                        @if(isset($userFavs[$selectedCard->cardID]))
                            <i class="ph-fill ph-heart" title="Favorited" style="color: #f472b6; font-size: 1.8rem; display: flex; align-items: center; justify-content: center; background: rgba(236, 72, 153, 0.2); border-radius: 50%; padding: 4px;"></i>
                        @endif
                    </div>
                </div>
                
                <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
                    <p style="color: var(--text-secondary); font-size: 1.2rem; margin: 0;">
                        {{ str_repeat('⭐', $selectedCard->rarity ?? 1) }} | Card ID: #{{ $selectedCard->cardID }}
                    </p>
                    <span style="color: var(--text-secondary); font-size: 0.9rem; background: rgba(0,0,0,0.3); padding: 4px 8px; border-radius: 4px; border: 1px solid var(--glass-border); cursor: pointer; transition: color 0.2s;" onmouseover="this.style.color='white'" onmouseout="this.style.color='var(--text-secondary)'" onclick="navigator.clipboard.writeText('{{ $selectedAuction->auctionID }}'); Livewire.dispatch('notify', { message: 'Copied Auction ID!' });" title="Copy Auction ID">
                        Auction ID: {{ $selectedAuction->auctionID }} <i class="ph-bold ph-copy"></i>
                    </span>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 2rem;">
                    <!-- Auction Info -->
                    <div class="glass-panel" style="padding: 1rem; border: 1px dashed var(--glass-border);">
                        <p style="color: var(--text-secondary); font-size: 0.9rem;">Current Price</p>
                        <p style="font-size: 1.5rem; font-weight: bold; color: #fbbf24;">{{ number_format($selectedAuction->price) }} 🍅</p>
                    </div>
                    <div class="glass-panel" style="padding: 1rem; border: 1px dashed var(--glass-border);">
                        <p style="color: var(--text-secondary); font-size: 0.9rem;">Time Remaining</p>
                        <p style="font-size: 1.1rem; font-weight: 600;">{{ \Carbon\Carbon::parse($selectedAuction->expires)->diffForHumans() }}</p>
                    </div>
                    <div class="glass-panel" style="padding: 1rem; border: 1px dashed var(--glass-border);">
                        <p style="color: var(--text-secondary); font-size: 0.9rem;">Seller</p>
                        <p style="font-size: 1.1rem; font-weight: 600;">
                            <a href="/profile?id={{ $selectedAuction->userID }}" style="color: var(--accent-solid); text-decoration: none;">
                                {{ $sellerUser ? $sellerUser->username : $selectedAuction->userID }}
                            </a>
                        </p>
                    </div>
                    <div class="glass-panel" style="padding: 1rem; border: 1px dashed var(--glass-border);">
                        <p style="color: var(--text-secondary); font-size: 0.9rem;">High Bidder</p>
                        <p style="font-size: 1.1rem; font-weight: 600;">{{ $selectedAuction->lastBidderID ? 'Hidden' : 'None' }}</p>
                    </div>
                    
                    <!-- Card Info -->
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
                        <p style="font-size: 1.1rem; font-weight: 600;">
                            {{ $selectedCard->stats['totalCopies'] ?? 'N/A' }}
                            @if(isset($userOwned[$selectedCard->cardID]) && $userOwned[$selectedCard->cardID] > 0)
                                <span style="color: var(--accent-solid); font-size: 0.9rem; margin-left: 0.5rem;">({{ $userOwned[$selectedCard->cardID] }} yours)</span>
                            @endif
                        </p>
                    </div>
                    <div class="glass-panel" style="padding: 1rem; border: 1px dashed var(--glass-border);">
                        <p style="color: var(--text-secondary); font-size: 0.9rem;">Rating</p>
                        <p style="font-size: 1.1rem; font-weight: 600;">
                            @if(($selectedCard->timesRated ?? 0) > 0)
                                {{ number_format(($selectedCard->ratingSum ?? 0) / $selectedCard->timesRated, 1) }} / 10 
                                <span style="font-size: 0.8rem; color: var(--text-secondary);">({{ $selectedCard->timesRated }} votes)</span>
                            @else
                                No Ratings
                            @endif
                        </p>
                    </div>
                </div>

                @auth
                    @php
                        $isWinning = $selectedAuction->lastBidderID === auth()->user()->userID;
                        $hasBalance = auth()->user()->tomatoes >= ($selectedAuction->price + 1);
                    @endphp
                    
                    @if($isWinning)
                        <div class="glass-panel" style="padding: 1.5rem; text-align: center; border: 1px solid #10b981; background: rgba(16, 185, 129, 0.1);">
                            <i class="ph-fill ph-check-circle" style="color: #10b981; font-size: 2.5rem; margin-bottom: 0.5rem;"></i>
                            <p style="color: #10b981; font-weight: bold; font-size: 1.2rem;">You are the highest bidder!</p>
                            <p style="color: var(--text-secondary); font-size: 0.9rem;">We'll notify you if you get outbid.</p>
                        </div>
                    @elseif(!$hasBalance)
                        <div class="glass-panel" style="padding: 1.5rem; text-align: center; border: 1px solid #ef4444; background: rgba(239, 68, 68, 0.1);">
                            <i class="ph-bold ph-warning-circle" style="color: #ef4444; font-size: 2.5rem; margin-bottom: 0.5rem;"></i>
                            <p style="color: #ef4444; font-weight: bold; font-size: 1.2rem;">Not enough tomatoes to bid.</p>
                            <p style="color: var(--text-secondary); font-size: 0.9rem;">Current balance: {{ number_format(auth()->user()->tomatoes) }} 🍅</p>
                        </div>
                    @else
                        <div class="glass-panel" style="padding: 1.5rem; border: 1px solid var(--glass-border); background: rgba(0,0,0,0.2);">
                            <h3 style="margin: 0 0 1rem 0; font-size: 1.2rem;">Place a Bid</h3>
                            
                            <div style="display: flex; gap: 1rem; align-items: center;">
                                <div style="display: flex; border: 1px solid var(--glass-border); border-radius: 8px; overflow: hidden; background: rgba(0,0,0,0.3);">
                                    <button wire:click="decrementBid" style="background: rgba(255,255,255,0.05); border: none; color: white; padding: 0.8rem 1rem; cursor: pointer; transition: background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.1)'" onmouseout="this.style.background='rgba(255,255,255,0.05)'"><i class="ph-bold ph-minus"></i></button>
                                    
                                    <input type="number" wire:model.live="bidAmount" style="background: transparent; border: none; color: white; font-size: 1.2rem; font-weight: bold; width: 120px; text-align: center; outline: none; -moz-appearance: textfield;" min="{{ $selectedAuction->price + 1 }}">
                                    
                                    <button wire:click="incrementBid" style="background: rgba(255,255,255,0.05); border: none; color: white; padding: 0.8rem 1rem; cursor: pointer; transition: background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.1)'" onmouseout="this.style.background='rgba(255,255,255,0.05)'"><i class="ph-bold ph-plus"></i></button>
                                </div>
                                
                                <button wire:click="placeBid" class="btn btn-primary" style="flex: 1; padding: 0.8rem; font-size: 1.1rem; background: #10b981; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4);">
                                    Place Bid
                                </button>
                            </div>
                            <p style="color: var(--text-secondary); font-size: 0.85rem; margin-top: 0.8rem;">
                                Your balance: <span style="color: white; font-weight: bold;">{{ number_format(auth()->user()->tomatoes) }} 🍅</span>
                            </p>
                        </div>
                    @endif
                @else
                    <div class="glass-panel" style="padding: 1.5rem; text-align: center; border: 1px solid rgba(239, 68, 68, 0.3);">
                        <p style="color: var(--text-secondary);">You must be signed in to place a bid.</p>
                        <a href="{{ route('login.discord') }}" class="btn btn-primary" style="margin-top: 1rem;">Sign In</a>
                    </div>
                @endauth
            </div>
        </div>
    </div>
    @endteleport
    @endif
</div>
