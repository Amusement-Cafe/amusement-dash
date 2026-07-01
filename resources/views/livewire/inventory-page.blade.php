<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\UserInventory;
use App\Models\BotCollection;

use Livewire\Attributes\Title;

new #[Layout('layouts.app')] #[Title('Inventory')] class extends Component
{
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

<div>
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
                @endphp
                <div class="glass-panel" style="padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem; border-top: 4px solid {{ $color }}; position: relative; overflow: hidden;">
                    <div style="position: absolute; top: -20px; right: -20px; font-size: 8rem; color: {{ $color }}; opacity: 0.05; pointer-events: none;">
                        <i class="ph-fill {{ $icon }}"></i>
                    </div>

                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <div style="width: 50px; height: 50px; border-radius: 12px; background: {{ $color }}20; color: {{ $color }}; display: flex; align-items: center; justify-content: center; font-size: 2rem; flex-shrink: 0;">
                            <i class="ph-fill {{ $icon }}"></i>
                        </div>
                        <div>
                            <h3 style="margin: 0; font-size: 1.2rem; text-transform: capitalize;">{{ $displayName }}</h3>
                            <div style="color: {{ $color }}; font-weight: bold; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px; margin-top: 0.2rem;">
                                {{ $type }}
                            </div>
                        </div>
                    </div>

                    <div style="background: rgba(0,0,0,0.2); padding: 1rem; border-radius: 8px; font-size: 0.9rem;">
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
</div>
