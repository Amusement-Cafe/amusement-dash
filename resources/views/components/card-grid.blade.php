@props(['cards', 'collections' => [], 'userOwned' => [], 'userFavs' => []])

<div x-data="{ 
    showModal: false, 
    selectedCard: null,
    openModal(cardData) {
        this.selectedCard = cardData;
        this.showModal = true;
    }
}">
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
        @foreach($cards as $card)
            @php 
                $col = $collections[$card->collectionID] ?? null; 
                $owned = $userOwned[$card->cardID] ?? false;
                $fav = $userFavs[$card->cardID] ?? false;
                
                // Prepare data for Alpine modal
                $cardData = [
                    'cardID' => $card->cardID,
                    'cardName' => $card->cardName,
                    'displayName' => $card->displayName ?? $card->cardName,
                    'cardURL' => $card->cardURL,
                    'rarity' => $card->rarity ?? 1,
                    'collectionID' => $card->collectionID,
                    'collectionName' => (is_array($col) ? $col['name'] : ($col->name ?? $card->collectionID)),
                    'eval' => $card->eval ?? 'N/A',
                    'totalCopies' => $card->stats['totalCopies'] ?? 'N/A',
                    'ratingSum' => $card->ratingSum ?? 0,
                    'timesRated' => $card->timesRated ?? 0,
                    'owned' => $owned,
                    'fav' => $fav
                ];
            @endphp
            <div @click="openModal({{ json_encode($cardData) }})" style="cursor: pointer;">
                <x-card-viewer :card="$card" :collectionName="$cardData['collectionName']" :owned="$owned" :fav="$fav" />
            </div>
        @endforeach
    </div>

    <!-- Modal using Alpine -->
    <template x-teleport="body">
        <div x-show="showModal" style="display: none;" class="modal-overlay" @click.self="showModal = false">
            <div class="modal-content glass-panel" style="display: flex; flex-direction: row; flex-wrap: wrap; padding: 2rem; gap: 2rem; position: relative;">
                <button @click="showModal = false" style="position: absolute; top: 1rem; right: 1rem; background: transparent; border: none; color: white; font-size: 1.5rem; cursor: pointer;">&times;</button>
                
                <!-- Left Side: Image -->
                <div style="flex: 1; min-width: 300px; display: flex; align-items: center; justify-content: center; background: transparent; border-radius: 8px; padding: 1rem;">
                    <template x-if="selectedCard?.cardURL">
                        <img :src="selectedCard.cardURL" :alt="selectedCard.displayName" style="max-width: 100%; max-height: 500px; object-fit: contain;">
                    </template>
                    <template x-if="!selectedCard?.cardURL">
                        <span style="color: var(--text-secondary);">No Image Available</span>
                    </template>
                </div>
                
                <!-- Right Side: Info -->
                <div style="flex: 1; min-width: 300px; display: flex; flex-direction: column; justify-content: center;">
                    <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 0.5rem;">
                        <h2 style="font-size: 2rem; margin: 0;" x-text="selectedCard?.displayName"></h2>
                        <template x-if="selectedCard?.owned">
                            <span style="background: rgba(16, 185, 129, 0.2); color: #34d399; padding: 4px 8px; border-radius: 4px; font-size: 0.85rem;">Owned</span>
                        </template>
                        <template x-if="selectedCard?.fav">
                            <span style="background: rgba(236, 72, 153, 0.2); color: #f472b6; padding: 4px 8px; border-radius: 4px; font-size: 0.85rem;">Favorited ❤️</span>
                        </template>
                    </div>
                    
                    <p style="color: var(--text-secondary); font-size: 1.2rem; margin-bottom: 1.5rem;">
                        <span x-text="'⭐'.repeat(selectedCard?.rarity || 1)"></span> | ID: #<span x-text="selectedCard?.cardID"></span>
                    </p>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 2rem;">
                        <div class="glass-panel" style="padding: 1rem; border: 1px dashed var(--glass-border);">
                            <p style="color: var(--text-secondary); font-size: 0.9rem;">Collection</p>
                            <p style="font-size: 1.1rem; font-weight: 600;">
                                <a :href="'{{ route('cards.index') }}?collectionID=' + selectedCard?.collectionID" style="color: var(--accent-solid); text-decoration: none;">
                                    <span x-text="selectedCard?.collectionName"></span>
                                </a>
                            </p>
                        </div>
                        <div class="glass-panel" style="padding: 1rem; border: 1px dashed var(--glass-border);">
                            <p style="color: var(--text-secondary); font-size: 0.9rem;">Eval</p>
                            <p style="font-size: 1.1rem; font-weight: 600;"><span x-text="selectedCard?.eval"></span> 🍅</p>
                        </div>
                        <div class="glass-panel" style="padding: 1rem; border: 1px dashed var(--glass-border);">
                            <p style="color: var(--text-secondary); font-size: 0.9rem;">Total Copies</p>
                            <p style="font-size: 1.1rem; font-weight: 600;" x-text="selectedCard?.totalCopies"></p>
                        </div>
                        <div class="glass-panel" style="padding: 1rem; border: 1px dashed var(--glass-border);">
                            <p style="color: var(--text-secondary); font-size: 0.9rem;">Rating</p>
                            <p style="font-size: 1.1rem; font-weight: 600;">
                                <template x-if="selectedCard?.timesRated > 0">
                                    <span>
                                        <span x-text="(selectedCard.ratingSum / selectedCard.timesRated).toFixed(1)"></span> / 10 
                                        <span style="font-size: 0.8rem; color: var(--text-secondary);"> (<span x-text="selectedCard.timesRated"></span> votes)</span>
                                    </span>
                                </template>
                                <template x-if="!selectedCard || selectedCard?.timesRated <= 0">
                                    <span>No Ratings</span>
                                </template>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>
