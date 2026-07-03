<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\UserInventory;
use App\Models\BotCollection;

use Livewire\Attributes\Title;

use Livewire\Attributes\On; 

new #[Layout('layouts.app')] #[Title('Inventory')] class extends Component
{
    public $showUseItemModal = false;
    public $selectedItemId;
    public $revealedCards = [];
    public $showCardReveal = false;

    public $showCardSearch = false;
    public $ticketStars = 0;
    public $ticketAmount = 0;
    public $ticketCollection = null;

    #[On('cards-selected')]
    public function cardsSelected($data)
    {
        $this->revealedCards = $data['cards'];
        $this->showCardSearch = false;
        $this->showCardReveal = true;

        $user = auth()->user();
        $item = UserInventory::where('userID', $user->userID)
            ->get()
            ->firstWhere('id', $this->selectedItemId);
            
        if($item) {
            $item->delete();
        }
    }

    #[On('close-search')]
    public function closeCardSearch()
    {
        $this->showCardSearch = false;
    }

    #[On('close-reveal')]
    public function closeCardReveal()
    {
        $this->addCardsToInventory($this->revealedCards);
        $this->showCardReveal = false;
    }

    public function addCardsToInventory($cardIds)
    {
        $user = auth()->user();
        foreach ($cardIds as $cardId) {
            $userCard = \App\Models\UserCard::where('userID', $user->userID)
                ->where('cardID', $cardId)
                ->first();

            if ($userCard) {
                $userCard->increment('amount');
            } else {
                \App\Models\UserCard::create([
                    'userID' => $user->userID,
                    'cardID' => $cardId,
                    'amount' => 1,
                    'acquired' => now(),
                ]);
            }
        }
    }

    public function confirmUseItem($itemId = null)
    {
        if ($itemId) {
            $this->selectedItemId = $itemId;
        }
        $this->dispatch('log-to-console', ['message' => 'confirmUseItem called']);
        
        $user = auth()->user();
        $item = UserInventory::where('userID', $user->userID)
            ->get()
            ->firstWhere('id', $this->selectedItemId);

        if (!$item) {
            $this->dispatch('log-to-console', ['message' => 'Item not found or not owned by user.']);
            return;
        }

        if ($item->type === 'ticket') {
            $this->dispatch('log-to-console', ['message' => 'Item is a ticket.']);
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => env('AMUSE_API_KEY')
            ])->timeout(5)->get(env('AMUSE_API_ROOT') . '/items');
            
            if ($response->successful()) {
                $storeItems = $response->json();
                $storeItem = $storeItems[$item->itemID] ?? null;
                $displayName = $storeItem && !empty($storeItem['displayName']) 
                    ? str_replace('`', '', $storeItem['displayName']) 
                    : ucfirst($item->itemID ?? $item->type);

                $isParsedTicket = false;
                $ticketAmount = 1;
                $ticketRandom = false;
                $ticketStars = '';
                
                if (preg_match('/^(\d+)x\s+(Random\s+)?([★]+)\s+Claim Ticket/i', trim($displayName), $matches)) {
                    $isParsedTicket = true;
                    $this->ticketAmount = $matches[1];
                    $ticketRandom = !empty(trim($matches[2] ?? ''));
                    $this->ticketStars = mb_strlen($matches[3], 'UTF-8');
                    $this->dispatch('log-to-console', ['message' => "isParsedTicket: $isParsedTicket, ticketAmount: $this->ticketAmount, ticketRandom: $ticketRandom, ticketStars: $this->ticketStars"]);
                }

                if ($isParsedTicket) {
                    $query = \App\Models\Card::where('rarity', $this->ticketStars)->where('canDrop', true);

                    if ($this->ticketStars == 4) {
                        $this->ticketCollection = 'special';
                        $query->where('collectionID', 'special');
                    } else {
                        $excludedCols = \App\Models\BotCollection::all()->filter(function($col) {
                            return !empty($col->promo) || in_array($col->collectionID, ['limitedcraft', 'special']);
                        })->pluck('collectionID')->toArray();
                        
                        $query->whereNotIn('collectionID', $excludedCols);

                        if ($storeItem && isset($storeItem['single']) && $storeItem['single']) {
                            $validCols = \App\Models\BotCollection::all()->filter(function($col) use ($excludedCols) {
                                return !in_array($col->collectionID, $excludedCols);
                            });
                            $this->ticketCollection = $validCols->random()->collectionID;
                            $query->where('collectionID', $this->ticketCollection);
                        } else if (isset($item->collectionID) && $item->collectionID !== 'random') {
                            $this->ticketCollection = $item->collectionID;
                            $query->where('collectionID', $this->ticketCollection);
                        }
                    }

                    if ($ticketRandom) {
                        $this->dispatch('log-to-console', ['message' => 'Ticket is random.']);
                        $cards = $query->get();
                        $this->revealedCards = $cards->shuffle()->take($this->ticketAmount)->pluck('cardID')->toArray();
                        $this->showCardReveal = true;
                        $item->delete();
                    } else {
                        $this->dispatch('log-to-console', ['message' => 'Ticket is not random.']);
                        $this->showCardSearch = true;
                    }
                } else {
                    $this->dispatch('log-to-console', ['message' => 'Could not parse ticket name.']);
                }
            } else {
                $this->dispatch('log-to-console', ['message' => 'Could not fetch store items.']);
            }
        } else {
            $this->dispatch('log-to-console', ['message' => 'Item is not a ticket.']);
        }
    }

    public function with(): array
    {
        $user = auth()->user();
        
        $inventoryItems = UserInventory::where('userID', $user->userID)
            ->orderBy('acquired', 'desc')
            ->get();

        $collectionIDs = $inventoryItems->pluck('collectionID')->filter()->unique()->toArray();
        $collections = [];
        if (!empty($collectionIDs)) {
            $collections = BotCollection::whereIn('collectionID', $collectionIDs)->get()->keyBy('collectionID')->toArray();
        }

        $storeItems = [];
        try {
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => env('AMUSE_API_KEY')
            ])->timeout(5)->get(env('AMUSE_API_ROOT') . '/items');
            
            if ($response->successful()) {
                $storeItems = $response->json();
            }
        } catch (\Exception $e) {}

        return [
            'inventoryItems' => $inventoryItems,
            'collections' => $collections,
            'storeItems' => $storeItems,
        ];
    }
};
?>

<div x-data="{ 
    showConfirmModal: false, 
    selectedItemId: null,
    showSearch: @entangle('showCardSearch').live,
    showReveal: @entangle('showCardReveal').live
}" 
x-effect="document.body.style.overflow = (showConfirmModal || showSearch || showReveal) ? 'hidden' : ''">
    <div style="margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: flex-end;">
        <div>
            <h1 style="font-size: 2.5rem; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 1rem;">
                <i class="ph-fill ph-backpack" style="color: var(--accent-solid);"></i> My Inventory
            </h1>
            <p style="color: var(--text-secondary); font-size: 1.1rem; margin: 0;">
                You have {{ count($inventoryItems) }} items stored.
            </p>
        </div>
        <a href="/" class="btn btn-secondary" style="display: flex; align-items: center; gap: 0.5rem;">
            <i class="ph ph-arrow-left"></i> Back to Dashboard
        </a>
    </div>

    @if(count($inventoryItems) > 0)
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 1.5rem;">
            @foreach($inventoryItems as $item)
                @php
                    $type = $item->type ?? 'unknown';
                    $color = match($type) {
                        'ticket' => '#60a5fa',
                        'recipe' => '#10b981',
                        'blueprint' => '#eab308',
                        'bonus' => '#a855f7',
                        default => '#3b82f6'
                    };
                    $icon = match($type) {
                        'ticket' => 'ph-ticket',
                        'recipe' => 'ph-flask',
                        'blueprint' => 'ph-house-line',
                        'bonus' => 'ph-star',
                        default => 'ph-archive'
                    };
                    $colName = $item->collectionID ? ($collections[$item->collectionID]['name'] ?? 'Unknown Collection') : null;
                    
                    $storeItem = $storeItems[$item->itemID] ?? null;
                    $displayName = $storeItem && !empty($storeItem['displayName']) 
                        ? str_replace('`', '', $storeItem['displayName']) 
                        : ucfirst($item->itemID ?? $item->type);
                        
                    $itemIsParsedTicket = false;
                    $itemTicketAmount = 1;
                    $itemTicketRandom = false;
                    $itemTicketStars = '';
                    
                    if ($type === 'ticket' && preg_match('/^(\d+)x\s+(Random\s+)?([★]+)\s+Claim Ticket/i', trim($displayName), $matches)) {
                        $itemIsParsedTicket = true;
                        $itemTicketAmount = $matches[1];
                        $itemTicketRandom = !empty(trim($matches[2] ?? ''));
                        $itemTicketStars = $matches[3];
                        $displayName = "Claim Ticket";
                    }
                @endphp
                <div class="glass-panel item-card" style="padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem; border-top: 4px solid {{ $color }}; position: relative; overflow: hidden; border-radius: 12px;">
                    <div style="position: absolute; top: -20px; right: -20px; font-size: 8rem; color: {{ $color }}; opacity: 0.05; pointer-events: none;">
                        <i class="ph-fill {{ $icon }}"></i>
                    </div>

                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <div style="width: 50px; height: 50px; border-radius: 12px; background: {{ $color }}20; color: {{ $color }}; display: flex; align-items: center; justify-content: center; font-size: 2rem; flex-shrink: 0;">
                            <i class="ph-fill {{ $icon }}"></i>
                        </div>
                        <div>
                            @if(!$itemIsParsedTicket)
                            <h3 style="margin: 0; font-size: 1.2rem; text-transform: capitalize;">{{ $displayName }}</h3>
                            @else
                            <h3 style="font-size: 1.3rem; font-weight: bold; margin: 0; color: white; display: flex; align-items: center; gap: 0.5rem;">
                                x{{ $itemTicketAmount }} <span style="color: #eab308; text-shadow: 0 0 5px rgba(234, 179, 8, 0.5);">{{ $itemTicketStars }}</span>
                            </h3>
                            @endif
                            <div style="color: {{ $color }}; font-weight: bold; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px; margin-top: 0.2rem;">
                                {{ $type }}
                            </div>
                        </div>
                    </div>

                    <div style="background: rgba(0,0,0,0.2); padding: 1rem; border-radius: 8px; font-size: 0.9rem;">
                        @if($itemIsParsedTicket)
                            @if($itemTicketRandom)
                                <div style="display: flex; align-items: center; gap: 0.4rem; color: #a855f7; margin-bottom: 0.3rem; font-weight: bold;">
                                    <i class="ph-bold ph-dice-three"></i> Random Drop
                                </div>
                                <p style="margin: 0 0 0.8rem 0; font-size: 0.85rem; opacity: 0.8; color: var(--text-secondary);">Yields random cards from the pool.</p>
                            @else
                                <div style="display: flex; align-items: center; gap: 0.4rem; color: #34d399; margin-bottom: 0.3rem; font-weight: bold;">
                                    <i class="ph-bold ph-hand-pointing"></i> Select Card
                                </div>
                                <p style="margin: 0 0 0.8rem 0; font-size: 0.85rem; opacity: 0.8; color: var(--text-secondary);">Pick specific cards from the pool.</p>
                            @endif
                        @endif
                        
                        @if($storeItem && isset($storeItem['single']) && $storeItem['single'])
                            <div style="display: flex; align-items: center; gap: 0.4rem; color: #fbbf24; margin-bottom: 0.8rem;" title="This item is bound to a single randomly selected collection.">
                                <i class="ph-bold ph-cards"></i> Single Collection
                            </div>
                        @endif
                        
                        @if($colName)
                            <div style="margin-bottom: 0.5rem; display: flex; justify-content: space-between;">
                                <span style="color: var(--text-secondary);">Collection:</span>
                                <span style="font-weight: bold; text-align: right;">{{ $colName }}</span>
                            </div>
                        @endif
                        @if(!empty($item->cards))
                            <div style="margin-bottom: 0.5rem; display: flex; justify-content: space-between;">
                                <span style="color: var(--text-secondary);">Cards Inside:</span>
                                <span style="font-weight: bold;">{{ count($item->cards) }}</span>
                            </div>
                        @endif
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: var(--text-secondary);">Acquired:</span>
                            <span>{{ $item->acquired ? \Carbon\Carbon::parse($item->acquired)->diffForHumans() : 'Unknown' }}</span>
                        </div>
                    </div>
                    
                    <div class="item-action-overlay">
                        <button @click="selectedItemId = '{{ $item->id }}'; showConfirmModal = true;" class="btn btn-primary" style="font-size: 1.1rem; padding: 0.8rem 2rem; box-shadow: 0 0 20px {{ $color }}60; border: 1px solid {{ $color }};">
                            <i class="ph-bold ph-magic-wand"></i> Use Item
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="glass-panel" style="padding: 4rem 2rem; text-align: center;">
            <i class="ph-light ph-backpack" style="font-size: 5rem; margin-bottom: 1.5rem; color: var(--text-secondary);"></i>
            <h2 style="margin: 0 0 1rem 0;">Your inventory is empty</h2>
            <p style="color: var(--text-secondary); margin: 0; font-size: 1.1rem; max-width: 500px; margin: 0 auto;">
                Buy packs from the store, complete quests, or participate in events to fill up your inventory!
            </p>
        </div>
    @endif

    @if($showCardReveal)
        <livewire:card-reveal :revealedCards="$revealedCards" />
    @endif

    <!-- AlpineJS Confirmation Modal (Instant, zero latency) -->
    <div x-show="showConfirmModal" class="modal-backdrop" x-transition.opacity.duration.300ms style="display: none; z-index: 10005;">
        <div class="glass-panel modal-content" x-show="showConfirmModal" x-transition.scale.90.duration.300ms style="background: rgba(20,20,30,0.95); border: 1px solid rgba(255,255,255,0.1); padding: 2.5rem; text-align: center; display: flex; flex-direction: column; align-items: center;">
            <i class="ph-fill ph-warning-circle" style="font-size: 4rem; color: #fbbf24; margin-bottom: 1rem; filter: drop-shadow(0 0 15px rgba(251,191,36,0.4));"></i>
            <h2 style="margin: 0 0 1rem 0; font-size: 1.8rem; color: white;">Use this Item?</h2>
            <p style="color: var(--text-secondary); margin-bottom: 2rem; font-size: 1.1rem; line-height: 1.5;">
                Are you sure you want to use this item? This action will permanently consume the item from your inventory.
            </p>
            <div class="modal-actions" style="justify-content: center; gap: 1.5rem; width: 100%; margin-top: 0;">
                <button @click="showConfirmModal = false" class="btn" style="background: rgba(255,255,255,0.1); color: white; padding: 0.8rem 2.5rem; font-size: 1.1rem; border-radius: 8px;">Cancel</button>
                <button @click="showConfirmModal = false; $wire.confirmUseItem(selectedItemId)" class="btn btn-primary" style="padding: 0.8rem 2.5rem; font-size: 1.1rem; background: var(--accent-solid); border-radius: 8px; box-shadow: 0 0 20px rgba(99,102,241,0.5);">Confirm</button>
            </div>
        </div>
    </div>

    @if($showCardSearch)
        <livewire:card-search 
            :ticketStars="$ticketStars" 
            :ticketAmount="$ticketAmount" 
            :ticketCollection="$ticketCollection" 
        />
    @endif
</div>

<script>
    document.addEventListener('livewire:init', () => {
       Livewire.on('log-to-console', (event) => {
           console.log(event[0].message);
        });
    });
</script>
<style>
    .item-card {
        transition: transform 0.2s ease-out, box-shadow 0.2s ease-out;
    }
    
    .item-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.5);
    }
    
    .item-action-overlay {
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(10, 10, 15, 0.7);
        backdrop-filter: blur(8px);
        display: flex;
        justify-content: center;
        align-items: center;
        opacity: 0;
        transition: opacity 0.2s ease-in-out;
        z-index: 10;
        border-radius: inherit;
    }
    
    .item-card:hover .item-action-overlay {
        opacity: 1;
    }

    .modal-backdrop {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 9998;
    }

    .modal-content {
        background-color: #2d3748;
        padding: 2rem;
        border-radius: 16px;
        width: 90%;
        max-width: 450px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
    }

    .modal-actions {
        display: flex;
        justify-content: flex-end;
        gap: 1rem;
        margin-top: 2rem;
    }
</style>
