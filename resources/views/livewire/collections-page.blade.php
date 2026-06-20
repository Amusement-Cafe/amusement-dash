<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\BotCollection;
use App\Models\Card;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    #[Url]
    public $search = '';

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function with(): array
    {
        $query = BotCollection::query();
        
        if ($this->search) {
            $query->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('collectionID', 'like', '%' . $this->search . '%');
        }

        $query->orderBy('name', 'asc');
        $collections = $query->paginate(24);
        
        $collectionImages = [];
        
        foreach ($collections as $col) {
            $cards = Card::where('collectionID', $col->collectionID)
                ->where('rarity', 3)
                ->get();
            
            if ($cards->isEmpty()) {
                $cards = Card::where('collectionID', $col->collectionID)->get();
            }
            
            $coverCard = $cards->isNotEmpty() ? $cards->random() : null;
            
            $collectionImages[$col->collectionID] = $coverCard ? $coverCard->cardURL : null;
        }

        return [
            'collections' => $collections,
            'collectionImages' => $collectionImages,
        ];
    }
};
?>

<div>
    <div style="margin-bottom: 2rem;">
        <h1 style="font-size: 2.5rem; margin: 0;">Card Collections</h1>
        <p style="color: var(--text-secondary); margin-top: 0.5rem;">Explore all {{ \App\Models\BotCollection::count() }} collections available in the bot.</p>
    </div>

    <div class="glass-panel" style="padding: 1rem; margin-bottom: 2rem; display: flex; align-items: center; gap: 1rem;">
        <i class="ph-fill ph-magnifying-glass" style="font-size: 1.5rem; color: var(--text-secondary);"></i>
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search collections by name or ID..." class="input-glass" style="flex: 1; max-width: 400px;">
    </div>

    @if($collections->count() > 0)
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
            @foreach($collections as $col)
                <div @click="$dispatch('open-collection-modal', { colID: '{{ $col->collectionID }}', colName: '{{ addslashes($col->name) }}', colPromo: {{ $col->promo ? 'true' : 'false' }}, colDate: '{{ $col->dateAdded ? \Carbon\Carbon::parse($col->dateAdded)->format('M d, Y') : 'Unknown' }}' })" class="glass-panel" style="overflow: hidden; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 10px 25px var(--accent-glow)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='var(--glass-shadow)';">
                    
                    <div style="height: 180px; width: 100%; background: #111; position: relative; display: flex; justify-content: center; align-items: center; overflow: hidden;">
                        @if(!empty($collectionImages[$col->collectionID]))
                            <img src="{{ $collectionImages[$col->collectionID] }}" alt="{{ $col->name }}" style="width: 100%; height: 100%; object-fit: cover; opacity: 0.6; filter: blur(2px); position: absolute; top: 0; left: 0;">
                            <img src="{{ $collectionImages[$col->collectionID] }}" alt="{{ $col->name }}" style="max-height: 100%; max-width: 100%; object-fit: contain; position: relative; z-index: 2;">
                        @else
                            <i class="ph-fill ph-books" style="font-size: 4rem; color: var(--text-secondary);"></i>
                        @endif
                    </div>
                    
                    <div style="padding: 1rem; text-align: center;">
                        <h3 style="margin: 0; font-size: 1.2rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="{{ $col->name }}">
                            {{ $col->name }}
                        </h3>
                        <p style="color: var(--text-secondary); margin: 0.3rem 0 0 0; font-size: 0.85rem;">
                            ID: {{ $col->collectionID }}
                            @if($col->promo)
                                <span style="background: rgba(236, 72, 153, 0.2); color: #f472b6; padding: 2px 6px; border-radius: 4px; margin-left: 0.5rem; font-size: 0.75rem;">Promo</span>
                            @endif
                        </p>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="glass-panel" style="padding: 1rem; display: flex; justify-content: center;">
            {{ $collections->links('components.custom-pagination') }}
        </div>
    @else
        <div class="glass-panel" style="padding: 3rem; text-align: center;">
            <p style="color: var(--text-secondary); font-size: 1.2rem;">No collections found.</p>
        </div>
    @endif

    <livewire:collection-modal />
</div>
