@props(['cards', 'collections' => [], 'userOwned' => [], 'userFavs' => [], 'userWishlists' => [], 'cardAuctions' => []])

@php
    $contributorIds = [];
    foreach ($cards as $card) {
        $meta = (array) ($card->meta ?? []);
        if (!empty($meta['contributor'])) {
            $contributorIds[] = (string) $meta['contributor'];
        }
    }
    $contributorIds = array_unique($contributorIds);
    $contributors = [];
    if (!empty($contributorIds)) {
        $contributors = \App\Models\User::whereIn('userID', $contributorIds)->pluck('username', 'userID')->toArray();
    }
@endphp
<div x-data="{ 
    showModal: false, 
    selectedCard: null,
    tags: [],
    tagsLoading: false,
    canWrite: {{ auth()->user()?->canWrite() ? 'true' : 'false' }},
    init() {
        this.$watch('showModal', value => {
            document.body.style.overflow = value ? 'hidden' : '';
        });
    },
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
    },
    handleCardUpdate(e) {
        if (this.selectedCard && this.selectedCard.cardID == e.detail.cardId) {
            if (e.detail.type === 'fav') this.selectedCard.fav = e.detail.value;
            if (e.detail.type === 'wishlist') this.selectedCard.wishlisted = e.detail.value;
        }
    }
}" @card-updated.window="handleCardUpdate($event)">
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
                    'wishlisted' => $wishlisted,
                    'auctionPrice' => isset($cardAuctions[$card->cardID]) ? $cardAuctions[$card->cardID]->price : null,
                    'meta' => $card->meta ?? null,
                    'contributorName' => !empty(((array)($card->meta ?? []))['contributor']) ? ($contributors[(string)(((array)($card->meta ?? []))['contributor'])] ?? null) : null
                ];
            @endphp
            <div wire:key="card-{{ $card->cardID }}"
                 x-data="{ cardItemData: {{ json_encode($cardData) }} }"
                 @card-updated.window="if ($event.detail.cardId == cardItemData.cardID) { 
                    if ($event.detail.type === 'fav') cardItemData.fav = $event.detail.value;
                    if ($event.detail.type === 'wishlist') cardItemData.wishlisted = $event.detail.value;
                 }"
                 @click="openModal(cardItemData)" 
                 style="cursor: pointer;">
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
        
        .card-action-icon-btn {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            border: 1px solid transparent;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer !important;
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            flex-shrink: 0;
            backdrop-filter: blur(10px);
            outline: none;
        }
        .card-action-icon-btn:hover {
            transform: scale(1.15);
            filter: brightness(1.25);
        }
        .card-action-icon-btn:active {
            transform: scale(0.95);
        }
        
        .card-action-icon-btn:disabled,
        .card-action-text-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed !important;
        }
        .card-action-icon-btn:disabled:hover,
        .card-action-text-btn:disabled:hover,
        .card-action-icon-btn:disabled:active,
        .card-action-text-btn:disabled:active {
            transform: none;
            filter: none;
        }
        
        .card-action-text-btn {
            flex: 1;
            height: 48px;
            padding: 0 1.5rem;
            border-radius: 16px;
            border: 1px solid transparent;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.8rem;
            font-weight: bold;
            font-size: 1.1rem;
            cursor: pointer !important;
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            backdrop-filter: blur(10px);
            text-decoration: none;
            outline: none;
        }
        .card-action-text-btn:hover {
            transform: scale(1.05);
            filter: brightness(1.2);
        }
        .card-action-text-btn:active {
            transform: scale(0.98);
        }
    </style>

    <script>
    (() => {
        if (window.createBurst) return;

        const layer = () => {
            let el = document.getElementById('burst-layer');
            if (!el) {
                el = document.createElement('div');
                el.id = 'burst-layer';
                el.style.cssText = 'position: fixed; inset: 0; pointer-events: none; overflow: hidden; z-index: 9998;';
                document.body.appendChild(el);
            }
            return el;
        };

        const rand = (min, max) => Math.random() * (max - min) + min;

        const alpha = (hex, a) => {
            const h = hex.replace('#', '');
            const n = parseInt(h.length === 3 ? h.split('').map(c => c + c).join('') : h, 16);
            return `rgba(${(n >> 16) & 255}, ${(n >> 8) & 255}, ${n & 255}, ${a})`;
        };

        /**
         * Celebratory particle burst, fired when a card is *added* to favorites / wishlist.
         * Removal is intentionally silent.
         */
        window.createBurst = function (source, iconClass = 'ph-fill ph-heart', color = '#ec4899') {
            const origin = source instanceof Element ? source : (source && (source.currentTarget || source.target));
            if (!origin || !origin.getBoundingClientRect) return;

            const rect = origin.getBoundingClientRect();
            const cx = rect.left + rect.width / 2;
            const cy = rect.top + rect.height / 2;
            const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            const root = layer();

            const spawn = (css, inner) => {
                const node = document.createElement('div');
                node.style.cssText = `position: absolute; left: ${cx}px; top: ${cy}px; transform: translate(-50%, -50%); will-change: transform, opacity; ${css}`;
                if (inner) node.innerHTML = inner;
                root.appendChild(node);
                return node;
            };

            const play = (node, frames, options) => {
                const anim = node.animate(frames, options);
                const cleanup = () => node.remove();
                anim.onfinish = cleanup;
                anim.oncancel = cleanup;
                setTimeout(cleanup, (options.duration || 0) + (options.delay || 0) + 400);
            };

            // Squash-and-stretch pop on the button itself.
            origin.animate([
                { transform: 'scale(1)' },
                { transform: 'scale(0.82)', offset: 0.22 },
                { transform: 'scale(1.22)', offset: 0.52 },
                { transform: 'scale(1)' }
            ], { duration: 520, easing: 'cubic-bezier(0.34, 1.56, 0.64, 1)' });

            // Expanding ring.
            play(spawn(`width: ${rect.width}px; height: ${rect.height}px; border-radius: 50%; border: 2px solid ${color};`), [
                { transform: 'translate(-50%, -50%) scale(0.55)', opacity: 0.95 },
                { transform: 'translate(-50%, -50%) scale(2.8)', opacity: 0 }
            ], { duration: 620, easing: 'cubic-bezier(0.2, 0.8, 0.3, 1)' });

            // Soft glow flash behind the button.
            play(spawn(`width: ${rect.width * 2.2}px; height: ${rect.width * 2.2}px; border-radius: 50%; background: radial-gradient(circle, ${alpha(color, 0.55)} 0%, ${alpha(color, 0)} 70%);`), [
                { transform: 'translate(-50%, -50%) scale(0.4)', opacity: 0 },
                { transform: 'translate(-50%, -50%) scale(1)', opacity: 1, offset: 0.25 },
                { transform: 'translate(-50%, -50%) scale(1.5)', opacity: 0 }
            ], { duration: 560, easing: 'ease-out' });

            if (reduced) return;

            // Icon particles: fan out, hang, then drop away.
            const count = 14;
            for (let i = 0; i < count; i++) {
                const angle = (i / count) * Math.PI * 2 + rand(-0.2, 0.2);
                const distance = rand(60, 135);
                const dx = Math.cos(angle) * distance;
                const dy = Math.sin(angle) * distance - rand(10, 45);
                const size = rand(13, 27);
                const spin = rand(-170, 170);
                const fall = rand(35, 85);

                play(spawn('', `<i class="${iconClass}" style="display: block; line-height: 1; font-size: ${size}px; color: ${color}; filter: drop-shadow(0 2px 6px ${alpha(color, 0.65)});"></i>`), [
                    { transform: 'translate(-50%, -50%) translate(0px, 0px) scale(0.3) rotate(0deg)', opacity: 0 },
                    { transform: `translate(-50%, -50%) translate(${dx * 0.62}px, ${dy * 0.62}px) scale(1.15) rotate(${spin * 0.5}deg)`, opacity: 1, offset: 0.28 },
                    { transform: `translate(-50%, -50%) translate(${dx}px, ${dy + fall}px) scale(0.55) rotate(${spin}deg)`, opacity: 0 }
                ], { duration: rand(720, 1060), delay: rand(0, 90), easing: 'cubic-bezier(0.16, 0.72, 0.3, 1)', fill: 'backwards' });
            }

            // Small sparks for extra texture.
            for (let i = 0; i < 10; i++) {
                const angle = rand(0, Math.PI * 2);
                const distance = rand(35, 105);
                const dot = rand(3, 6);

                play(spawn(`width: ${dot}px; height: ${dot}px; border-radius: 50%; background: ${i % 3 === 0 ? '#ffffff' : color}; box-shadow: 0 0 8px ${alpha(color, 0.9)};`), [
                    { transform: 'translate(-50%, -50%) translate(0px, 0px) scale(0.4)', opacity: 1 },
                    { transform: `translate(-50%, -50%) translate(${Math.cos(angle) * distance}px, ${Math.sin(angle) * distance}px) scale(1)`, opacity: 0 }
                ], { duration: rand(420, 700), delay: rand(0, 120), easing: 'cubic-bezier(0.12, 0.8, 0.3, 1)', fill: 'backwards' });
            }
        };
    })();
    </script>

    <!-- Modal using Alpine -->
    <template x-teleport="body">
        <div x-show="showModal" style="display: none;" x-transition.opacity.duration.200ms>
            <div style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); z-index: 1000; align-items: center; justify-content: center; display: flex; padding: 1rem;" @click.self="showModal = false">
                <div class="glass-panel no-scrollbar" style="display: flex; flex-direction: row; flex-wrap: wrap; padding: 2rem; gap: 2rem; position: relative; max-width: 1000px; width: 100%; max-height: 90vh; overflow-y: auto;">
                    <button @click="showModal = false" style="position: absolute; top: 1rem; right: 1rem; background: transparent; border: none; color: white; font-size: 1.5rem; cursor: pointer;">&times;</button>
                    
                    <!-- Left Side: Image -->
                    <div x-data="{ imgStatus: 'loading' }"
                         x-init="$watch('selectedCard?.cardURL', () => {
                            imgStatus = 'loading';
                            $nextTick(() => { if ($refs.cardImg?.complete && $refs.cardImg?.naturalWidth) imgStatus = 'loaded'; });
                         })"
                         style="flex: 1; min-width: 300px; display: flex; align-items: center; justify-content: center; background: transparent; border-radius: 8px; padding: 1rem; align-self: flex-start; min-height: 400px; position: relative;">

                        <div x-show="selectedCard?.cardURL && imgStatus === 'loading'" style="position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;">
                            <i class="ph-bold ph-spinner" style="font-size: 3rem; color: var(--text-secondary); animation: spin 1s linear infinite;"></i>
                        </div>

                        <div x-cloak x-show="selectedCard?.cardURL && imgStatus === 'error'" style="position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #ef4444;">
                            <i class="ph-fill ph-warning-octagon" style="font-size: 4rem; margin-bottom: 0.5rem; display: block;"></i>
                            <span style="font-size: 1rem; font-weight: bold; display: block; text-align: center;">Failed to load image</span>
                        </div>

                        <template x-if="selectedCard?.cardURL">
                            <div style="width: 100%; display: flex; justify-content: center; align-items: center; min-height: 300px;">
                                <img :src="selectedCard.cardURL" 
                                     :alt="selectedCard.displayName" 
                                     x-ref="cardImg"
                                     x-on:load="imgStatus = 'loaded'" 
                                     x-on:error="imgStatus = 'error'"
                                     x-show="imgStatus === 'loaded'"
                                     style="max-width: 100%; max-height: 500px; object-fit: contain;">
                            </div>
                        </template>

                        <template x-if="!selectedCard?.cardURL">
                            <div style="display: flex; height: 100%; width: 100%; align-items: center; justify-content: center;">
                                <span style="color: var(--text-secondary);">No Image Available</span>
                            </div>
                        </template>
                    </div>
                
                <!-- Right Side: Info -->
                <div style="flex: 1; min-width: 300px; display: flex; flex-direction: column; justify-content: center;">
                    <div style="display: flex; align-items: center; gap: 0.8rem; margin-bottom: 0.5rem; display: block;">
                        <h2 style="font-size: 2rem; margin: 0;" x-text="selectedCard?.displayName"></h2>
                        <div style="display: flex; gap: 0.5rem; flex-shrink: 0;">
                            <template x-if="selectedCard?.owned">
                                <div title="Owned" style="display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-left: 0.5rem;">
                                    <i class="ph-fill ph-check-circle" style="color: #34d399; font-size: 1.6rem; filter: drop-shadow(0 2px 5px rgba(52, 211, 153, 0.3));"></i>
                                </div>
                            </template>
                            
                            @auth
                                {{-- Both actions stay in the DOM at all times: toggling one must never resize the modal. --}}
                                <button @click="if (selectedCard) { $dispatch('toggle-fav', { cardId: selectedCard.cardID }); if (canWrite && !selectedCard.fav) window.createBurst($event.currentTarget, 'ph-fill ph-heart', '#ec4899'); }" 
                                        :disabled="!selectedCard?.userCopies"
                                        :title="!selectedCard?.userCopies ? 'You need to own this card to favorite it' : (selectedCard?.fav ? 'Remove from favorites' : 'Add to favorites')"
                                        class="card-action-icon-btn"
                                        style="padding: 0;"
                                        :style="selectedCard?.fav ? 'background: rgba(236, 72, 153, 0.2); border-color: rgba(236, 72, 153, 0.5); color: #ec4899; box-shadow: 0 4px 15px rgba(236, 72, 153, 0.4);' : 'background: rgba(0, 0, 0, 0.4); border-color: rgba(255,255,255,0.1); color: white; box-shadow: 0 4px 15px rgba(0,0,0,0.3);'">
                                    <i class="ph-bold ph-heart" :class="selectedCard?.fav ? 'ph-fill' : ''" style="font-size: 1.4rem;"></i> 
                                </button>
                                
                                <button @click="if (selectedCard) { $dispatch('toggle-wishlist', { cardId: selectedCard.cardID }); if (canWrite && !selectedCard.wishlisted) window.createBurst($event.currentTarget, 'ph-fill ph-star', '#fbbf24'); }" 
                                        :disabled="selectedCard?.userCopies > 0 && !selectedCard?.wishlisted"
                                        :title="selectedCard?.wishlisted ? 'Remove from wishlist' : (selectedCard?.userCopies > 0 ? 'You already own this card' : 'Add to wishlist')"
                                        class="card-action-icon-btn"
                                        style="padding: 0;"
                                        :style="selectedCard?.wishlisted ? 'background: rgba(245, 158, 11, 0.2); border-color: rgba(245, 158, 11, 0.5); color: #fbbf24; box-shadow: 0 4px 15px rgba(245, 158, 11, 0.4);' : 'background: rgba(0, 0, 0, 0.4); border-color: rgba(255,255,255,0.1); color: white; box-shadow: 0 4px 15px rgba(0,0,0,0.3);'">
                                    <i class="ph-bold ph-star" :class="selectedCard?.wishlisted ? 'ph-fill' : ''" style="font-size: 1.4rem;"></i> 
                                </button>
                            @else
                                <template x-if="selectedCard?.fav">
                                    <div title="Favorited" class="card-action-icon-btn" style="background: rgba(236, 72, 153, 0.2); border: 1px solid transparent;">
                                        <i class="ph-fill ph-heart" style="color: #f472b6; font-size: 1.4rem;"></i>
                                    </div>
                                </template>
                                <template x-if="selectedCard?.wishlisted">
                                    <div title="Wishlisted" class="card-action-icon-btn" style="background: rgba(245, 158, 11, 0.2); border: 1px solid transparent;">
                                        <i class="ph-fill ph-star" style="color: #fbbf24; font-size: 1.4rem;"></i>
                                    </div>
                                </template>
                            @endauth
                        </div>
                    </div>
                    
                    <p style="color: var(--text-secondary); font-size: 1.2rem; margin-bottom: 1.5rem;">
                        <span x-text="'⭐'.repeat(selectedCard?.rarity || 1)"></span> | ID: #<span x-text="selectedCard?.cardID"></span>
                    </p>
                    
                    <div style="display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
                        @auth
                            <template x-if="selectedCard?.userCopies > 0">
                                <div style="display: flex; gap: 1rem; width: 100%;">
                                    <button @click="if (selectedCard?.fav) $dispatch('set-profile-fav', { cardId: selectedCard.cardID })" 
                                            :disabled="!selectedCard?.fav"
                                            :title="selectedCard?.fav ? 'Show this card on your profile' : 'Favorite this card first to use it as your profile card'"
                                            class="card-action-text-btn"
                                            style="background: rgba(59, 130, 246, 0.2); border-color: rgba(59, 130, 246, 0.5); color: #60a5fa; box-shadow: 0 8px 25px rgba(59, 130, 246, 0.3);">
                                        <i class="ph-bold ph-user" style="font-size: 1.4rem;"></i> Set as Profile Fav
                                    </button>
                                </div>
                            </template>
                            
                            <template x-if="(!selectedCard || selectedCard?.userCopies <= 0) && selectedCard?.auctionPrice">
                                <div style="display: flex; gap: 1rem; width: 100%;">
                                    <a href="/auctions" 
                                       class="card-action-text-btn"
                                       style="background: rgba(16, 185, 129, 0.2); border-color: rgba(16, 185, 129, 0.5); color: #10b981; box-shadow: 0 8px 25px rgba(16, 185, 129, 0.3);">
                                        <i class="ph-bold ph-gavel" style="font-size: 1.4rem;"></i> View Auction (<span x-text="selectedCard?.auctionPrice"></span> 🍅)
                                    </a>
                                </div>
                            </template>
                        @else
                            <div style="width: 100%; padding: 1rem; background: rgba(0, 0, 0, 0.4); backdrop-filter: blur(10px); border: 1px dashed var(--glass-border); border-radius: 12px; text-align: center; color: var(--text-secondary); box-shadow: inset 0 2px 10px rgba(0,0,0,0.5);">
                                <a href="{{ route('login.discord') }}" style="color: var(--accent-solid); text-decoration: none; font-weight: bold;">Sign in</a> for more actions like favorite and wishlist.
                            </div>
                        @endauth
                    </div>
                    
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
                    
                    <template x-if="selectedCard?.meta && Object.keys(selectedCard.meta).length > 0 && Object.values(selectedCard.meta).some(v => v !== null && v !== undefined && v !== '')">
                        <div style="margin-top: 1rem;">
                            <h4 style="margin: 0 0 0.5rem 0; color: var(--text-secondary); display: flex; align-items: center; gap: 0.5rem;">
                                <i class="ph-fill ph-info"></i> Metadata
                            </h4>
                            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 0.5rem;">
                                <template x-for="(value, key) in selectedCard.meta" :key="key">
                                    <template x-if="value !== null && value !== undefined && value !== ''">
                                        <div class="glass-panel" style="padding: 0.5rem; border: 1px dashed var(--glass-border); border-radius: 8px;">
                                            <p style="color: var(--text-secondary); font-size: 0.8rem; margin: 0; text-transform: capitalize;" x-text="key.replace(/([A-Z])/g, ' $1').trim()"></p>
                                            
                                            <template x-if="key === 'contributor'">
                                                <p style="font-size: 0.95rem; font-weight: 600; margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                                    <a :href="'{{ route('profile.show') }}?id=' + value" style="color: var(--accent-solid); text-decoration: none;">
                                                        <span x-text="selectedCard.contributorName ? selectedCard.contributorName : value"></span>
                                                    </a>
                                                </p>
                                            </template>
                                            
                                            <template x-if="key !== 'contributor' && typeof value === 'string' && (value.startsWith('http://') || value.startsWith('https://'))">
                                                <p style="font-size: 0.95rem; font-weight: 600; margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                                    <a :href="value" target="_blank" style="color: var(--accent-solid); text-decoration: none; display: flex; align-items: center; gap: 0.2rem;">
                                                        Link <i class="ph-bold ph-arrow-square-out" style="font-size: 0.9em;"></i>
                                                    </a>
                                                </p>
                                            </template>
                                            <template x-if="key !== 'contributor' && !(typeof value === 'string' && (value.startsWith('http://') || value.startsWith('https://')))">
                                                <p style="font-size: 0.95rem; font-weight: 600; margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" x-text="value" :title="value"></p>
                                            </template>
                                        </div>
                                    </template>
                                </template>
                            </div>
                        </div>
                    </template>
                    
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
                                    <a :href="'{{ route('cards.index') }}?tags[0]=' + encodeURIComponent(tag.tagName)" 
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
                    
                    @auth

                        @php
                            $userRoles = auth()->user()->roles;
                            if (!is_array($userRoles)) $userRoles = [];
                            $isCardEditor = count(array_intersect(array_map('strtolower', $userRoles), ['metamod', 'tagmod', 'admin'])) > 0;
                        @endphp
                        @if($isCardEditor)
                            <a :href="'/card-editor?cardId=' + selectedCard?.cardID" style="margin-top: 1rem; width: 100%; padding: 0.8rem; background: rgba(59, 130, 246, 0.1); border: 1px dashed #3b82f6; color: #3b82f6; border-radius: 8px; font-weight: bold; cursor: pointer; transition: background 0.2s; display: flex; align-items: center; justify-content: center; gap: 0.5rem; text-decoration: none;" onmouseover="this.style.background='rgba(59, 130, 246, 0.2)'" onmouseout="this.style.background='rgba(59, 130, 246, 0.1)'">
                                <i class="ph-bold ph-pencil-simple"></i> Edit Card Information
                            </a>
                        @endif
                    @endauth
                </div>
            </div>
        </div>
    </template>
</div>
