<?php

use Livewire\Volt\Component;
use App\Models\Card;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public $ticketStars = 0;
    public $ticketAmount = 0;
    public $ticketCollection = null;
    public $search = '';
    public $selectedCards = [];
    public $collectionName = null;

    public function mount($ticketStars, $ticketAmount, $ticketCollection)
    {
        \Illuminate\Support\Facades\Log::info('card-search mount', ['ticketStars' => $ticketStars, 'ticketAmount' => $ticketAmount, 'ticketCollection' => $ticketCollection]);
        $this->ticketStars = (int) $ticketStars;
        $this->ticketAmount = (int) $ticketAmount;
        $this->ticketCollection = $ticketCollection;

        if ($this->ticketCollection) {
            $col = \App\Models\BotCollection::where('collectionID', $this->ticketCollection)->first();
            $this->collectionName = $col ? $col->name : $this->ticketCollection;
        }
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function selectCard($cardId)
    {
        if (in_array($cardId, $this->selectedCards)) {
            $this->deselectCard($cardId);
            return;
        }

        if (count($this->selectedCards) < $this->ticketAmount) {
            $this->selectedCards[] = $cardId;
        }
    }

    public function deselectCard($cardId)
    {
        $this->selectedCards = array_values(array_diff($this->selectedCards, [$cardId]));
    }

    public function confirmSelection()
    {
        if (count($this->selectedCards) === $this->ticketAmount) {
            $this->dispatch('cards-selected', ['cards' => $this->selectedCards]);
        }
    }

    public function getCardsProperty()
    {
        $query = Card::where('rarity', $this->ticketStars);

        if ($this->ticketCollection) {
            $query->where('collectionID', $this->ticketCollection);
        }

        if ($this->search) {
            $query->where(function($q) {
                $q->where('displayName', 'like', '%' . $this->search . '%')
                  ->orWhere('cardName', 'like', '%' . $this->search . '%');
                if (is_numeric($this->search)) {
                    $q->orWhere('cardID', (int) $this->search);
                }
            });
        }

        return $query->paginate(8);
    }
}; ?>

<div x-data="{ selectedCount: $wire.entangle('selectedCards').live }">
        <div class="modal-backdrop" style="z-index: 10000; padding: 2rem;">
            <div class="modal-content glass-panel" style="width: 95%; max-width: 1400px; max-height: 95vh; display: flex; flex-direction: column; padding: 2rem; background: var(--bg-dark);">
                
                <!-- Header -->
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-shrink: 0;">
                    <div>
                        <h2 style="margin: 0; font-size: 2rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="ph-fill ph-cards" style="color: var(--accent-solid);"></i> 
                            Select Cards 
                            <span style="color: var(--text-secondary); font-size: 1.2rem; font-weight: normal;">
                                ({{ count($selectedCards) }} / {{ $ticketAmount }})
                            </span>
                        </h2>
                        <p style="margin: 0.2rem 0 0 0; color: var(--text-secondary);">
                            Filter: {{ $ticketStars }}★ 
                            @if($collectionName)
                            | Collection: {{ $collectionName }}
                            @endif
                        </p>
                    </div>
                    <button wire:click="$dispatch('close-search')" style="background: rgba(255,255,255,0.1); border: none; color: white; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.2)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'">
                        <i class="ph-bold ph-x" style="font-size: 1.2rem;"></i>
                    </button>
                </div>
                
                <!-- Search -->
                <div style="margin-bottom: 1.5rem; flex-shrink: 0;">
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search by name or ID..." class="input-glass" style="width: 100%; max-width: 400px; font-size: 1.1rem; padding: 0.8rem 1rem;">
                </div>

                <!-- Grid -->
                <div style="flex: 1; overflow-y: auto; padding-right: 1rem; scrollbar-width: none; -ms-overflow-style: none;">
                    <style>
                        .modal-content > div::-webkit-scrollbar {
                            display: none;
                        }
                    </style>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
                        @foreach($this->cards as $card)
                            <div wire:click="selectCard({{ $card->cardID }})" style="position: relative;">
                                <x-card-viewer :card="$card" :collectionName="$card->collectionID" />
                                
                                @if(in_array($card->cardID, $selectedCards))
                                    <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(99, 102, 241, 0.2); border: 3px solid var(--accent-solid); border-radius: 16px; display: flex; align-items: center; justify-content: center; z-index: 20; pointer-events: none; backdrop-filter: blur(2px);">
                                        <i class="ph-fill ph-check-circle" style="font-size: 4rem; color: white; filter: drop-shadow(0 0 10px rgba(0,0,0,0.5));"></i>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    
                    @if($this->cards->hasPages())
                        <div style="margin-top: 1rem; display: flex; justify-content: center; background: rgba(0,0,0,0.2); padding: 1rem; border-radius: 12px;">
                            {{ $this->cards->links('components.custom-pagination') }}
                        </div>
                    @endif
                </div>

                <!-- Footer -->
                <div style="margin-top: 2rem; display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--glass-border); padding-top: 1.5rem; flex-shrink: 0;">
                    <div style="display: flex; gap: 0.5rem; overflow-x: auto; max-width: 60%; padding-bottom: 0.5rem;">
                        @foreach($selectedCards as $cardId)
                            <span wire:click="deselectCard({{ $cardId }})" style="background: var(--accent-solid); color: white; padding: 0.4rem 1rem; border-radius: 20px; font-size: 0.9rem; cursor: pointer; display: flex; align-items: center; gap: 0.5rem; font-weight: bold; transition: opacity 0.2s;" onmouseover="this.style.opacity=0.8" onmouseout="this.style.opacity=1">
                                #{{ $cardId }} <i class="ph-bold ph-x" style="font-size: 0.8rem;"></i>
                            </span>
                        @endforeach
                        @if(count($selectedCards) == 0)
                            <span style="color: var(--text-secondary); font-style: italic;">No cards selected</span>
                        @endif
                    </div>
                    
                    <div class="modal-actions" style="margin: 0; display: flex; gap: 1rem;">
                        <button wire:click="$dispatch('close-search')" class="btn" style="background: rgba(255,255,255,0.1); color: white; padding: 0.8rem 1.5rem; font-size: 1rem;">Cancel</button>
                        <button wire:click="confirmSelection" class="btn btn-primary" style="padding: 0.8rem 1.5rem; font-size: 1rem; @if(count($selectedCards) !== $ticketAmount) opacity: 0.5; cursor: not-allowed; @endif" 
                                @if(count($selectedCards) !== $ticketAmount) disabled @endif>
                            Confirm Selection
                        </button>
                    </div>
                </div>
                
            </div>
        </div>
</div>
