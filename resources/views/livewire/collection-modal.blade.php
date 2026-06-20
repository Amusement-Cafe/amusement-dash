<?php

use Livewire\Volt\Component;
use App\Models\Card;

new class extends Component
{
    public $sampleCards = null;
    public $totalCardsInSelected = null;
    public $loadedCollectionID = null;

    public function fetchDetails($collectionID)
    {
        $this->loadedCollectionID = $collectionID;
        $this->sampleCards = null;
        $this->totalCardsInSelected = null;
        
        $c1 = Card::where('collectionID', $collectionID)->where('rarity', 1)->get();
        $c2 = Card::where('collectionID', $collectionID)->where('rarity', 2)->get();
        $c3 = Card::where('collectionID', $collectionID)->where('rarity', 3)->get();

        if ($c2->isEmpty() && $c3->isEmpty() && $c1->count() > 0) {
            $randoms = $c1->random(min(3, $c1->count()))->values();
            $this->sampleCards = [
                0 => $randoms->get(0),
                1 => $randoms->get(1),
                2 => $randoms->get(2),
            ];
        } else {
            $this->sampleCards = [
                0 => $c1->isNotEmpty() ? $c1->random() : null,
                1 => $c2->isNotEmpty() ? $c2->random() : null,
                2 => $c3->isNotEmpty() ? $c3->random() : null,
            ];
        }
        
        $this->totalCardsInSelected = Card::where('collectionID', $collectionID)->count();
    }
};
?>

<div x-data="{ 
    showModal: false, 
    colID: '', 
    colName: '', 
    colPromo: false, 
    colDate: ''
}" 
@open-collection-modal.window="
    showModal = true;
    colID = $event.detail.colID;
    colName = $event.detail.colName;
    colPromo = $event.detail.colPromo;
    colDate = $event.detail.colDate;
    $wire.fetchDetails(colID);
"
@keydown.escape.window="showModal = false">

    <template x-teleport="body">
        <div x-show="showModal" style="display: none;" x-transition.opacity.duration.200ms>
            <div style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); z-index: 1000; display: flex; align-items: center; justify-content: center; padding: 1rem;">
                <div @click.outside="showModal = false" class="glass-panel" style="position: relative; width: 100%; max-width: 800px; max-height: 90vh; overflow-y: auto; padding: 2rem;">
                    <button @click="showModal = false" style="position: absolute; top: 1rem; right: 1rem; background: transparent; border: none; color: white; font-size: 1.5rem; cursor: pointer;"><i class="ph-bold ph-x"></i></button>
                    
                    <div style="text-align: center; margin-bottom: 2rem;">
                        <h2 x-text="colName" style="font-size: 2.5rem; margin: 0 0 0.5rem 0;"></h2>
                        <p style="color: var(--text-secondary); font-size: 1.1rem; margin: 0;">ID: <span x-text="colID"></span></p>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
                        <div class="glass-panel" style="padding: 1rem; text-align: center; border: 1px dashed var(--glass-border);">
                            <p style="color: var(--text-secondary); font-size: 0.9rem; margin: 0 0 0.5rem 0;">Total Cards</p>
                            <div x-show="colID === $wire.loadedCollectionID" style="display: none;">
                                <p style="font-size: 1.5rem; font-weight: bold; margin: 0;">{{ $totalCardsInSelected ?? 0 }}</p>
                            </div>
                            <div x-show="colID !== $wire.loadedCollectionID" style="display: none;">
                                <i class="ph-bold ph-spinner" style="font-size: 1.5rem; animation: spin 1s linear infinite;"></i>
                            </div>
                        </div>
                        <div class="glass-panel" style="padding: 1rem; text-align: center; border: 1px dashed var(--glass-border);">
                            <p style="color: var(--text-secondary); font-size: 0.9rem; margin: 0 0 0.5rem 0;">Type</p>
                            <p x-text="colPromo ? 'Promo' : 'Standard'" :style="colPromo ? 'color: #f472b6' : 'color: white'" style="font-size: 1.2rem; font-weight: bold; margin: 0;"></p>
                        </div>
                        <div class="glass-panel" style="padding: 1rem; text-align: center; border: 1px dashed var(--glass-border);">
                            <p style="color: var(--text-secondary); font-size: 0.9rem; margin: 0 0 0.5rem 0;">Added On</p>
                            <p x-text="colDate" style="font-size: 1.2rem; font-weight: bold; margin: 0;"></p>
                        </div>
                    </div>

                    <h3 style="margin: 0 0 1rem 0; font-size: 1.5rem; text-align: center;"><i class="ph-fill ph-cards"></i> Card Samples</h3>
                    
                    <div x-show="colID === $wire.loadedCollectionID" style="display: none;">
                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem;">
                            @if(is_array($sampleCards))
                                @foreach([0, 1, 2] as $idx)
                                    <div style="text-align: center;">
                                        @if(isset($sampleCards[$idx]) && $sampleCards[$idx])
                                            <div style="display: flex; justify-content: center; margin: 0 0 0.5rem 0; color: #eab308; font-size: 1.1rem;">
                                                @for($i = 0; $i < $sampleCards[$idx]->rarity; $i++)
                                                    <i class="ph-fill ph-star"></i>
                                                @endfor
                                            </div>
                                            <div class="glass-panel" style="padding: 0.5rem; border: 1px solid var(--glass-border);">
                                                <div style="height: 250px; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.3); border-radius: 4px; overflow: hidden; margin-bottom: 0.5rem;">
                                                    <img src="{{ $sampleCards[$idx]->cardURL }}" alt="Sample Card" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                                                </div>
                                                <p style="margin: 0; font-size: 0.9rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $sampleCards[$idx]->displayName ?? $sampleCards[$idx]->cardName }}</p>
                                            </div>
                                        @else
                                            <div style="display: flex; justify-content: center; margin: 0 0 0.5rem 0; color: #eab308; font-size: 1.1rem;">
                                                @for($i = 0; $i < ($idx + 1); $i++)
                                                    <i class="ph-fill ph-star"></i>
                                                @endfor
                                            </div>
                                            <div class="glass-panel" style="padding: 1rem; height: 250px; display: flex; flex-direction: column; align-items: center; justify-content: center; border: 1px dashed var(--glass-border);">
                                                <i class="ph-fill ph-question" style="font-size: 3rem; color: var(--text-secondary); margin-bottom: 0.5rem;"></i>
                                                <span style="color: var(--text-secondary); font-size: 0.8rem;">No card</span>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    </div>
                    
                    <div x-show="colID !== $wire.loadedCollectionID" style="display: none;">
                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem;">
                            @foreach([0, 1, 2] as $idx)
                                <div style="text-align: center;">
                                    <div style="display: flex; justify-content: center; margin: 0 0 0.5rem 0; color: var(--text-secondary); font-size: 1.1rem;">
                                        <i class="ph-fill ph-star"></i>
                                    </div>
                                    <div class="glass-panel" style="padding: 1rem; height: 250px; display: flex; flex-direction: column; align-items: center; justify-content: center; border: 1px dashed var(--glass-border);">
                                        <i class="ph-bold ph-spinner" style="font-size: 3rem; color: var(--text-secondary); margin-bottom: 0.5rem; animation: spin 1s linear infinite;"></i>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div style="text-align: center; margin-top: 2rem;">
                        <a :href="'{{ route('cards.index') }}?collectionID=' + colID" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.8rem 2rem; font-size: 1.1rem;">
                            View All Cards <i class="ph-bold ph-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>
