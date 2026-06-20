@props(['cards', 'collections' => [], 'userOwned' => [], 'userFavs' => [], 'userWishlists' => []])

<div x-data="{ 
    showModal: false, 
    selectedCard: null,
    tags: [],
    tagsLoading: false,
    openModal(cardData) {
        this.selectedCard = cardData;
        this.showModal = true;
        this.tags = [];
        this.tagsLoading = true;
        fetch('/api/tags/' + cardData.cardID)
            .then(res => res.json())
            .then(data => {
                this.tags = data;
                this.tagsLoading = false;
            })
            .catch(err => {
                this.tagsLoading = false;
                console.error(err);
            });
    }
}">
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
        @foreach($cards as $card)
            @php 
                $col = $collections[$card->collectionID] ?? null; 
                $owned = $userOwned[$card->cardID] ?? false;
                $fav = $userFavs[$card->cardID] ?? false;
                $wishlisted = $userWishlists[$card->cardID] ?? false;
                
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
                    'userCopies' => $owned ? (int)$owned : 0,
                    'fav' => $fav,
                    'wishlisted' => $wishlisted
                ];
            @endphp
            <div @click="openModal({{ json_encode($cardData) }})" style="cursor: pointer;">
                <x-card-viewer :card="$card" :collectionName="$cardData['collectionName']" :owned="$owned" :fav="$fav" :wishlisted="$wishlisted" />
            </div>
        @endforeach
    </div>

    <style>
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
    </style>

    <!-- Modal using Alpine -->
    <template x-teleport="body">
        <div x-show="showModal" style="display: none;" x-transition.opacity.duration.200ms>
            <div style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); z-index: 1000; align-items: center; justify-content: center; display: flex; padding: 1rem;" @click.self="showModal = false">
                <div class="glass-panel no-scrollbar" style="display: flex; flex-direction: row; flex-wrap: wrap; padding: 2rem; gap: 2rem; position: relative; max-width: 1000px; width: 100%; max-height: 90vh; overflow-y: auto;">
                    <button @click="showModal = false" style="position: absolute; top: 1rem; right: 1rem; background: transparent; border: none; color: white; font-size: 1.5rem; cursor: pointer;">&times;</button>
                    
                    <!-- Left Side: Image -->
                    <div style="flex: 1; min-width: 300px; display: flex; align-items: flex-start; justify-content: center; background: transparent; border-radius: 8px; padding: 1rem; position: sticky; top: 0;">
                    <template x-if="selectedCard?.cardURL">
                        <img :src="selectedCard.cardURL" :alt="selectedCard.displayName" style="max-width: 100%; max-height: 500px; object-fit: contain;">
                    </template>
                    <template x-if="!selectedCard?.cardURL">
                        <span style="color: var(--text-secondary);">No Image Available</span>
                    </template>
                </div>
                
                <!-- Right Side: Info -->
                <div style="flex: 1; min-width: 300px; display: flex; flex-direction: column; justify-content: center;">
                    <div style="display: flex; align-items: center; gap: 0.8rem; margin-bottom: 0.5rem;">
                        <h2 style="font-size: 2rem; margin: 0;" x-text="selectedCard?.displayName"></h2>
                        <div style="display: flex; gap: 0.5rem;">
                            <template x-if="selectedCard?.owned">
                                <i class="ph-fill ph-check-circle" title="Owned" style="color: #34d399; font-size: 1.8rem; display: flex; align-items: center; justify-content: center; background: rgba(16, 185, 129, 0.2); border-radius: 50%; padding: 4px;"></i>
                            </template>
                            <template x-if="selectedCard?.fav">
                                <i class="ph-fill ph-heart" title="Favorited" style="color: #f472b6; font-size: 1.8rem; display: flex; align-items: center; justify-content: center; background: rgba(236, 72, 153, 0.2); border-radius: 50%; padding: 4px;"></i>
                            </template>
                            <template x-if="selectedCard?.wishlisted">
                                <i class="ph-fill ph-star" title="Wishlisted" style="color: #fbbf24; font-size: 1.8rem; display: flex; align-items: center; justify-content: center; background: rgba(245, 158, 11, 0.2); border-radius: 50%; padding: 4px;"></i>
                            </template>
                        </div>
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
                            <p style="font-size: 1.1rem; font-weight: 600;">
                                <span x-text="selectedCard?.totalCopies"></span>
                                <template x-if="selectedCard?.userCopies > 0">
                                    <span style="color: var(--accent-solid); font-size: 0.9rem; margin-left: 0.5rem;" x-text="'(' + selectedCard.userCopies + ' yours)'"></span>
                                </template>
                            </p>
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
                    
                    <div style="margin-top: 1rem;">
                        <h4 style="margin: 0 0 0.5rem 0; color: var(--text-secondary); display: flex; align-items: center; gap: 0.5rem;">
                            <i class="ph-fill ph-tag"></i> Tags
                        </h4>
                        
                        <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                            <template x-if="tagsLoading">
                                <span style="color: var(--text-secondary); font-size: 0.9rem; font-style: italic;">Loading tags...</span>
                            </template>
                            
                            <template x-if="!tagsLoading && tags.length === 0">
                                <span style="color: var(--text-secondary); font-size: 0.9rem;">No tags for this card.</span>
                            </template>

                            <template x-if="!tagsLoading && tags.length > 0">
                                <template x-for="tag in tags" :key="tag.id">
                                    <a :href="'{{ route('cards.index') }}?tag=' + encodeURIComponent(tag.tagName)" 
                                       style="background: rgba(59, 130, 246, 0.2); border: 1px solid rgba(59, 130, 246, 0.5); color: #60a5fa; padding: 4px 10px; border-radius: 12px; text-decoration: none; font-size: 0.85rem; display: flex; align-items: center; gap: 0.3rem; transition: background 0.2s;"
                                       onmouseover="this.style.background='rgba(59, 130, 246, 0.4)'"
                                       onmouseout="this.style.background='rgba(59, 130, 246, 0.2)'">
                                        #<span x-text="tag.tagName"></span>
                                        <span style="background: rgba(0,0,0,0.3); padding: 2px 6px; border-radius: 8px; font-size: 0.75rem;">
                                            <i class="ph-fill ph-arrow-up"></i> <span x-text="tag.upvotes ? tag.upvotes.length : 0"></span>
                                        </span>
                                    </a>
                                </template>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>
