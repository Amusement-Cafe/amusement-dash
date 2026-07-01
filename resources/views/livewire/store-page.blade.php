<?php

use Livewire\Volt\Component;
use Illuminate\Support\Facades\Http;

new class extends Component {
    public array $items = [];
    public array $categories = [
        'ticket' => ['title' => 'Tickets', 'icon' => 'ph-ticket', 'color' => '#60a5fa'],
        'recipe' => ['title' => 'Recipes', 'icon' => 'ph-flask', 'color' => '#10b981'],
        'blueprint' => ['title' => 'Blueprints', 'icon' => 'ph-house-line', 'color' => '#eab308'],
        'bonus' => ['title' => 'Bonuses', 'icon' => 'ph-star', 'color' => '#a855f7'],
    ];

    public string $activeCategory = 'ticket';

    public function mount() {
        try {
            $response = Http::withHeaders([
                'Authorization' => env('AMUSE_API_KEY')
            ])->timeout(5)->get(env('AMUSE_API_ROOT') . '/items');
            
            if ($response->successful()) {
                $this->items = $response->json();
            }
        } catch (\Exception $e) {
            // keep items empty
        }
    }
    
    public function setCategory($cat) {
        $this->activeCategory = $cat;
    }
    
    public function purchase($itemID) {
        // Placeholder for purchase logic
        $this->dispatch('notify', message: "Purchased $itemID!");
    }
    
    public bool $showPreview = false;
    public array $previewCards = [];
    public int $previewRarity = 1;
    public bool $previewRandom = false;

    public function previewTicket($rarity, $isRandom) {
        $this->previewRarity = $rarity;
        $this->previewRandom = $isRandom;
        
        $cardIDs = \App\Models\Card::where('rarity', (int)$rarity)->pluck('cardID')->toArray();
        if (!empty($cardIDs)) {
            shuffle($cardIDs);
            $selectedIDs = array_slice($cardIDs, 0, 4);
            $this->previewCards = \App\Models\Card::whereIn('cardID', $selectedIDs)->get()->toArray();
        } else {
            $this->previewCards = [];
        }
                                
        $this->showPreview = true;
    }
    
    public function closePreview() {
        $this->showPreview = false;
    }
};
?>
<div>
    <style>
        .store-item {
            background: rgba(20, 20, 30, 0.6);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 16px;
            padding: 1.5rem;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: transform 0.3s, box-shadow 0.3s, border-color 0.3s;
        }
        .store-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.4);
            border-color: rgba(255,255,255,0.2);
        }
        
        .category-tab {
            display: flex; 
            align-items: center; 
            gap: 0.5rem; 
            padding: 0.8rem 1.5rem; 
            border-radius: 20px; 
            font-weight: bold; 
            transition: all 0.3s; 
            border: 1px solid rgba(255,255,255,0.1); 
            cursor: pointer;
            background: rgba(0,0,0,0.3);
            color: var(--text-secondary);
            backdrop-filter: blur(10px);
        }
        .category-tab:hover {
            background: rgba(255,255,255,0.15);
        }
        .category-tab.active {
            background: rgba(255,255,255,0.1);
            color: white;
            border-color: rgba(255,255,255,0.3);
        }
        
        .buy-btn {
            font-weight: bold; 
            border: none; 
            padding: 0.5rem 1rem; 
            border-radius: 12px; 
            cursor: pointer; 
            transition: filter 0.2s, transform 0.2s;
            color: #000;
        }
        .buy-btn:hover {
            filter: brightness(1.2);
            transform: scale(1.05);
        }
        
        .preview-overlay {
            position: absolute;
            inset: 0;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(3px);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s;
            z-index: 10;
            border-radius: 16px;
        }
        .store-item:hover .preview-overlay {
            opacity: 1;
        }
        .preview-btn {
            background: var(--item-color);
            color: #000;
            border: none;
            padding: 0.8rem 1.5rem;
            border-radius: 20px;
            font-weight: bold;
            cursor: pointer;
            transform: translateY(20px);
            transition: transform 0.3s, filter 0.2s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .store-item:hover .preview-btn {
            transform: translateY(0);
        }
        .preview-btn:hover {
            filter: brightness(1.2);
        }
    </style>

    <div class="store-header" style="text-align: center; padding: 3rem 0; margin-bottom: 2rem; position: relative;">
        <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: radial-gradient(circle at 50% 50%, rgba(244,63,94,0.15) 0%, transparent 60%); pointer-events: none;"></div>
        <h1 style="font-size: 3rem; font-weight: 800; margin-bottom: 1rem; background: linear-gradient(135deg, #f43f5e, #a855f7); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Amusement Store</h1>
        <p style="color: var(--text-secondary); font-size: 1.2rem; max-width: 600px; margin: 0 auto;">Purchase exclusive tickets, recipes, blueprints, and bonuses for your collection.</p>
    </div>

    <!-- Category Tabs -->
    <div class="category-tabs" style="display: flex; justify-content: center; gap: 1rem; flex-wrap: wrap; margin-bottom: 3rem;">
        @foreach($categories as $key => $cat)
            <button wire:click="setCategory('{{ $key }}')" class="category-tab {{ $activeCategory === $key ? 'active' : '' }}">
                <i class="ph-fill {{ $cat['icon'] }}" style="font-size: 1.2rem; color: {{ $cat['color'] }};"></i>
                {{ $cat['title'] }}
            </button>
        @endforeach
    </div>

    <!-- Items Grid -->
    <div class="items-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 2rem;">
        @foreach($items as $itemID => $item)
            @if(isset($item['type']) && $item['type'] === $activeCategory)
                @php
                    $color = $categories[$activeCategory]['color'] ?? '#ffffff';
                    $icon = $categories[$activeCategory]['icon'] ?? 'ph-star';
                    $rawName = $item['displayName'] ?? '';
                    $displayName = !empty($rawName) ? str_replace('`', '', $rawName) : ucfirst($item['itemID']);
                    
                    $isParsedTicket = false;
                    $ticketAmount = 1;
                    $ticketRandom = false;
                    $ticketStars = '';
                    
                    if ($item['type'] === 'ticket' && preg_match('/^(\d+)x\s+(Random\s+)?([★]+)\s+Claim Ticket/i', trim($displayName), $matches)) {
                        $isParsedTicket = true;
                        $ticketAmount = $matches[1];
                        $ticketRandom = !empty(trim($matches[2] ?? ''));
                        $ticketStars = $matches[3];
                        $displayName = "Claim Ticket";
                    }
                @endphp
                <div class="store-item" style="--item-color: {{ $color }};" onmouseover="this.style.boxShadow='0 15px 30px rgba(0,0,0,0.4), 0 0 20px ' + this.style.getPropertyValue('--item-color').replace('#', 'rgba(') + ', 0.1)'" onmouseout="this.style.boxShadow='none'">
                    
                    @if($isParsedTicket)
                        <div class="preview-overlay">
                            <button wire:click="previewTicket({{ mb_strlen($ticketStars) }}, {{ $ticketRandom ? 'true' : 'false' }})" class="preview-btn">
                                <i class="ph-bold ph-eye"></i> Preview Cards
                            </button>
                        </div>
                    @endif
                    
                    <div style="position: absolute; top: -50px; right: -50px; width: 100px; height: 100px; background: {{ $color }}; filter: blur(50px); opacity: 0.2; pointer-events: none;"></div>
                    
                    <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem;">
                        <div style="display: flex; align-items: center; justify-content: center; width: 64px; height: 64px; border-radius: 16px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); flex-shrink: 0;">
                            <i class="ph-fill {{ $icon }}" style="font-size: 2.5rem; color: {{ $color }}; filter: drop-shadow(0 0 10px {{ $color }});"></i>
                        </div>
                        
                        @if(!$isParsedTicket)
                        <h3 style="font-size: 1.2rem; font-weight: bold; margin: 0; color: white;">
                            {{ $displayName }}
                        </h3>
                        @else
                        <h3 style="font-size: 1.3rem; font-weight: bold; margin: 0; color: white; display: flex; align-items: center; gap: 0.5rem;">
                            x{{ $ticketAmount }} <span style="color: #eab308; text-shadow: 0 0 5px rgba(234, 179, 8, 0.5);">{{ $ticketStars }}</span>
                        </h3>
                        @endif
                    </div>
                    
                    <div style="color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 1.5rem; flex-grow: 1;">
                        @if($isParsedTicket)
                            @if($ticketRandom)
                                <div style="display: flex; align-items: center; gap: 0.4rem; color: #a855f7; margin-bottom: 0.3rem; font-weight: bold;">
                                    <i class="ph-bold ph-dice-three"></i> Random Drop
                                </div>
                                <p style="margin: 0 0 0.8rem 0; font-size: 0.85rem; opacity: 0.8;">Yields random cards from the pool.</p>
                            @else
                                <div style="display: flex; align-items: center; gap: 0.4rem; color: #34d399; margin-bottom: 0.3rem; font-weight: bold;">
                                    <i class="ph-bold ph-hand-pointing"></i> Select Card
                                </div>
                                <p style="margin: 0 0 0.8rem 0; font-size: 0.85rem; opacity: 0.8;">Pick specific cards from the pool.</p>
                            @endif
                        @endif
                        
                        @if(isset($item['uses']) && $item['uses'] > 0)
                            <div style="display: flex; align-items: center; gap: 0.4rem; margin-bottom: 0.3rem;">
                                <i class="ph-bold ph-arrows-clockwise"></i> {{ $item['uses'] }} Uses
                            </div>
                        @endif
                        @if(isset($item['active']) && $item['active'])
                            <div style="display: flex; align-items: center; gap: 0.4rem; color: #34d399;">
                                <i class="ph-bold ph-lightning"></i> Active Item
                            </div>
                        @endif
                        @if(isset($item['single']) && $item['single'])
                            <div style="display: flex; align-items: center; gap: 0.4rem; color: #fbbf24; margin-bottom: 0.3rem;" title="When purchased, this item will be bound to a single randomly selected collection.">
                                <i class="ph-bold ph-cards"></i> Single Collection
                            </div>
                        @endif
                    </div>
                    
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-top: auto; position: relative; z-index: 20;">
                        <div style="display: flex; align-items: center; gap: 0.5rem; background: rgba(0,0,0,0.5); padding: 0.5rem 1rem; border-radius: 12px; border: 1px solid rgba(255,255,255,0.1);">
                            <span>🍅</span>
                            <span style="font-weight: bold; color: white;">{{ number_format(isset($item['cost']) && $item['cost'] > 1 ? $item['cost'] : 1000) }}</span>
                        </div>
                        
                        <button wire:click="purchase('{{ $itemID }}')" class="buy-btn" style="background: {{ $color }};">
                            Buy
                        </button>
                    </div>
                </div>
            @endif
        @endforeach
    </div>
    
    @if(empty($items))
        <div style="text-align: center; padding: 4rem; color: var(--text-secondary); background: rgba(0,0,0,0.2); border-radius: 20px; border: 1px dashed rgba(255,255,255,0.1); margin-top: 2rem;">
            <i class="ph-bold ph-storefront" style="font-size: 4rem; margin-bottom: 1rem; opacity: 0.3;"></i>
            <h2 style="color: white; margin-bottom: 0.5rem;">Store is Empty</h2>
            <p>Could not load store items or no items are available. The API might be down.</p>
        </div>
    @endif
    
    <!-- Preview Modal -->
    @if($showPreview)
        <div x-data>
            <template x-teleport="body">
                <div style="position: fixed; inset: 0; background: rgba(0,0,0,0.8); backdrop-filter: blur(10px); z-index: 9999; display: flex; align-items: center; justify-content: center; padding: 2rem;">
                    <div style="background: rgba(20,20,30,0.95); border: 1px solid rgba(255,255,255,0.1); border-radius: 24px; padding: 2rem; max-width: 800px; width: 100%; position: relative; box-shadow: 0 25px 50px rgba(0,0,0,0.5); animation: zoomIn 0.3s ease-out;">
                        <button wire:click="closePreview" style="position: absolute; top: 1rem; right: 1rem; background: rgba(255,255,255,0.1); border: none; width: 40px; height: 40px; border-radius: 50%; color: white; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.2)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'">
                            <i class="ph-bold ph-x" style="font-size: 1.2rem;"></i>
                        </button>
                        
                        <h2 style="font-size: 2rem; font-weight: bold; margin-top: 0; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                            Ticket Preview
                            <span style="color: #eab308; text-shadow: 0 0 10px rgba(234, 179, 8, 0.5);">{{ str_repeat('★', $previewRarity) }}</span>
                        </h2>
                        
                        <p style="color: var(--text-secondary); margin-bottom: 2rem; font-size: 1.1rem;">
                            @if($previewRandom)
                                <span style="color: #a855f7;"><i class="ph-bold ph-dice-three"></i> Random Drop:</span> You will receive random cards from the {{ $previewRarity }}-Star pool. Here are some examples of what you might get:
                            @else
                                <span style="color: #34d399;"><i class="ph-bold ph-hand-pointing"></i> Your Choice:</span> You can select exactly which card you want from the {{ $previewRarity }}-Star pool! Here are some examples:
                            @endif
                        </p>
                        
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem;">
                            @foreach($previewCards as $card)
                                <div style="background: rgba(0,0,0,0.3); border-radius: 12px; padding: 0.5rem; text-align: center; border: 1px solid rgba(255,255,255,0.05);">
                                    <div style="aspect-ratio: 2/3; background-image: url('{{ $card['cardURL'] ?? '' }}'); background-size: cover; background-position: center; border-radius: 8px; margin-bottom: 0.8rem; box-shadow: 0 5px 15px rgba(0,0,0,0.3);"></div>
                                    <div style="font-weight: bold; font-size: 0.9rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: white; text-transform: capitalize;" title="{{ $card['cardName'] ?? 'Unknown' }}">
                                        {{ $card['cardName'] ?? 'Unknown' }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                
                <style>
                    @keyframes zoomIn {
                        from { opacity: 0; transform: scale(0.95); }
                        to { opacity: 1; transform: scale(1); }
                    }
                </style>
            </template>
        </div>
    @endif
</div>
