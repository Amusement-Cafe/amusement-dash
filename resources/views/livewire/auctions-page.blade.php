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

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

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
        // Fetch running auctions
        // Ensure they aren't ended, cancelled, and haven't expired
        // (If expired ones are still ended=false in DB, we should filter by date too, but some DB formats for dates can be tricky. Let's just use ended and cancelled for now, and sort by expires)
        $auctions = Auction::where('ended', false)
            ->where('cancelled', false)
            ->orderBy('expires', 'asc')
            ->paginate(16);

        $cardIDs = $auctions->pluck('cardID')->unique()->toArray();
        $userIDs = $auctions->pluck('userID')->unique()->toArray();

        $cards = Card::whereIn('cardID', $cardIDs)->get()->keyBy('cardID');
        $sellers = User::whereIn('userID', $userIDs)->get()->keyBy('userID');

        $collections = Cache::remember('bot_collections_array', 3600, function() {
            return BotCollection::all()->keyBy('collectionID')->toArray();
        });

        $userOwned = [];
        $userFavs = [];
        if (auth()->check()) {
            $userCards = UserCard::where('userID', auth()->user()->userID)
                ->whereIn('cardID', $cardIDs)
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
            $selectedCard = $cards->get((int) $this->selectedCardId);
            if ($selectedCard) {
                $selectedCardCollection = $collections[$selectedCard->collectionID] ?? null;
            }
        }

        return [
            'auctions' => $auctions,
            'cards' => $cards,
            'sellers' => $sellers,
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
    <div style="margin-bottom: 2rem;">
        <h1 style="font-size: 2.5rem; margin: 0;">Live Auctions ({{ number_format($auctions->total()) }} running)</h1>
        <p style="color: var(--text-secondary); margin-top: 0.5rem;">Bid on cards from other players in real-time Vickrey auctions.</p>
    </div>

    @if($auctions->count() > 0)
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 2rem; margin-bottom: 2rem;">
            @foreach($auctions as $auction)
                @php 
                    $card = $cards->get($auction->cardID);
                    $seller = $sellers->get($auction->userID);
                @endphp
                
                <div class="glass-panel" style="padding: 1rem; border: 1px solid var(--glass-border); transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 10px 25px rgba(0,0,0,0.5)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='var(--glass-shadow)';">
                    
                    <!-- Auction Header -->
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                        <span style="background: rgba(245, 158, 11, 0.2); color: #fbbf24; padding: 4px 8px; border-radius: 4px; font-size: 0.8rem; font-weight: bold;">
                            {{ number_format($auction->price) }} 🍅
                        </span>
                        
                        <span style="color: var(--text-secondary); font-size: 0.8rem;">
                            ID: {{ $auction->auctionID }}
                        </span>
                    </div>

                    @if($card)
                        @php
                            $col = $collections[$card->collectionID] ?? null;
                            $owned = $userOwned[$card->cardID] ?? false;
                            $fav = $userFavs[$card->cardID] ?? false;
                        @endphp
                        
                        <!-- Wrapper to handle modal click -->
                        <div wire:click="openCardModal({{ $card->cardID }})">
                            <x-card-viewer :card="$card" :collectionName="$col['name'] ?? null" :owned="$owned" :fav="$fav" />
                        </div>
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

        <div class="glass-panel" style="padding: 1rem;">
            {{ $auctions->links() }}
        </div>
    @else
        <div class="glass-panel" style="padding: 3rem; text-align: center;">
            <p style="color: var(--text-secondary); font-size: 1.2rem;">No active auctions running right now.</p>
        </div>
    @endif

    <!-- Card Modal (Shared logic from all-cards) -->
    @if($showModal && $selectedCard)
    @teleport('body')
    <div class="modal-overlay" wire:click.self="closeModal">
        <div class="modal-content glass-panel" style="display: flex; flex-direction: row; flex-wrap: wrap; padding: 2rem; gap: 2rem; position: relative;">
            <button wire:click="closeModal" style="position: absolute; top: 1rem; right: 1rem; background: transparent; border: none; color: white; font-size: 1.5rem; cursor: pointer;">&times;</button>
            
            <div style="flex: 1; min-width: 300px; display: flex; align-items: center; justify-content: center; background: transparent; border-radius: 8px; padding: 1rem;">
                @if($selectedCard->cardURL)
                    <img src="{{ $selectedCard->cardURL }}" alt="{{ $selectedCard->displayName ?? $selectedCard->cardName }}" style="max-width: 100%; max-height: 500px; object-fit: contain;">
                @else
                    <span style="color: var(--text-secondary);">No Image Available</span>
                @endif
            </div>
            
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
