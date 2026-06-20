@props(['collections' => [], 'tags' => [], 'sortDesc' => true, 'hidePromos' => false, 'activeFiltersCount' => 0])

<div x-data="{ open: false }" class="glass-panel" style="margin-bottom: 2rem; width: 100%;">
    <!-- Header / Toggle -->
    <div @click="open = !open" style="padding: 1.5rem 2rem; display: flex; justify-content: space-between; align-items: center; cursor: pointer; user-select: none; border-bottom: 1px solid transparent; transition: border-color 0.3s;" :style="{ borderBottomColor: open ? 'rgba(255,255,255,0.05)' : 'transparent' }">
        <h3 style="margin: 0; font-size: 1.2rem; display: flex; align-items: center; gap: 0.5rem; color: var(--text-primary);">
            <i class="ph-fill ph-faders"></i> Filter & Sort Cards
            @if($activeFiltersCount > 0)
                <span style="background: rgba(236, 72, 153, 0.2); color: #f472b6; font-size: 0.8rem; padding: 2px 8px; border-radius: 12px; margin-left: 0.5rem; border: 1px solid rgba(236, 72, 153, 0.3); font-weight: 600;">
                    {{ $activeFiltersCount }} Active
                </span>
            @endif
        </h3>
        <i class="ph-bold ph-caret-down" :style="{ transform: open ? 'rotate(180deg)' : 'none' }" style="transition: transform 0.3s; font-size: 1.2rem; color: var(--text-secondary);"></i>
    </div>

    <div x-show="open" x-transition.opacity.duration.300ms style="display: none;">
        <div style="padding: 1.5rem; display: flex; flex-direction: column; gap: 1.5rem;">
            <!-- Top Row: Main Filters -->
            <div style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end;">
                <div style="flex: 1; min-width: 200px;">
                    <label style="color: var(--text-secondary); font-size: 0.85rem; margin-bottom: 0.3rem; display: block;"><i class="ph-fill ph-magnifying-glass"></i> Search Name or ID</label>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Enter name or ID..." class="input-glass" style="width: 100%;">
                </div>
                
                <div style="min-width: 150px;">
                    <label style="color: var(--text-secondary); font-size: 0.85rem; margin-bottom: 0.3rem; display: block;"><i class="ph-fill ph-star"></i> Rarity</label>
                    <select wire:model.live="rarity" class="input-glass" style="width: 100%;">
                        <option value="">All Rarities</option>
                        <option value="1">1 Star</option>
                        <option value="2">2 Stars</option>
                        <option value="3">3 Stars</option>
                        <option value="4">4 Stars</option>
                        <option value="5">5 Stars</option>
                        <option value="6">6 Stars (Promo)</option>
                    </select>
                </div>

                <div style="min-width: 200px;">
                    <label style="color: var(--text-secondary); font-size: 0.85rem; margin-bottom: 0.3rem; display: block;"><i class="ph-fill ph-books"></i> Collection</label>
                    <select wire:model.live="collectionID" class="input-glass" style="width: 100%;">
                        <option value="">All Collections</option>
                        @foreach($collections as $col)
                            <option value="{{ $col['collectionID'] }}">{{ $col['name'] }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- Second Row: Sorting and Toggles -->
            <div style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end; padding-top: 1rem; border-top: 1px solid rgba(255,255,255,0.05);">
                <div style="min-width: 150px;">
                    <label style="color: var(--text-secondary); font-size: 0.85rem; margin-bottom: 0.3rem; display: block;"><i class="ph-fill ph-sort-ascending"></i> Sort By</label>
                    <select wire:model.live="sortBy" class="input-glass" style="width: 100%;">
                        <option value="cardID">ID</option>
                        <option value="rarity">Rarity</option>
                        <option value="added">Date Added</option>
                        <option value="eval">Eval</option>
                    </select>
                </div>

                <button wire:click="$toggle('sortDesc')" class="btn btn-secondary" style="height: 42px; display: flex; align-items: center; gap: 0.5rem; padding: 0 1rem; background: rgba(255,255,255,0.1); border: 1px solid var(--glass-border); color: var(--text-primary); border-radius: 8px; cursor: pointer; transition: background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.2)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'">
                    @if($sortDesc)
                        <i class="ph-bold ph-sort-descending"></i> Descending
                    @else
                        <i class="ph-bold ph-sort-ascending"></i> Ascending
                    @endif
                </button>

                <button wire:click="$toggle('hidePromos')" class="btn" style="height: 42px; display: flex; align-items: center; gap: 0.5rem; padding: 0 1rem; transition: background 0.2s; border-radius: 8px; border: 1px solid var(--glass-border); cursor: pointer; {{ $hidePromos ? 'background: var(--accent-solid); color: white;' : 'background: rgba(255,255,255,0.1); color: var(--text-primary);' }}" {!! !$hidePromos ? 'onmouseover="this.style.background=\'rgba(255,255,255,0.2)\'" onmouseout="this.style.background=\'rgba(255,255,255,0.1)\'"' : '' !!}>
                    @if($hidePromos)
                        <i class="ph-fill ph-eye-slash"></i> Promos Hidden
                    @else
                        <i class="ph-fill ph-eye"></i> Promos Shown
                    @endif
                </button>
            </div>

            @auth
                <!-- Third Row: User Filters -->
                <div style="display: flex; gap: 1.5rem; flex-wrap: wrap; align-items: flex-end; padding-top: 1rem; border-top: 1px solid rgba(255,255,255,0.05);">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <label style="color: var(--text-secondary); font-size: 0.9rem; margin: 0; display: flex; align-items: center; gap: 0.3rem;"><i class="ph-fill ph-check-circle" style="color: #34d399;"></i> Owned:</label>
                        <select wire:model.live="filterOwned" class="input-glass" style="padding: 0.3rem 1rem;">
                            <option value="">Any</option>
                            <option value="only">Only</option>
                            <option value="exclude">Exclude</option>
                        </select>
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <label style="color: var(--text-secondary); font-size: 0.9rem; margin: 0; display: flex; align-items: center; gap: 0.3rem;"><i class="ph-fill ph-heart" style="color: #f472b6;"></i> Favorited:</label>
                        <select wire:model.live="filterFav" class="input-glass" style="padding: 0.3rem 1rem;">
                            <option value="">Any</option>
                            <option value="only">Only</option>
                            <option value="exclude">Exclude</option>
                        </select>
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <label style="color: var(--text-secondary); font-size: 0.9rem; margin: 0; display: flex; align-items: center; gap: 0.3rem;"><i class="ph-fill ph-star" style="color: #fbbf24;"></i> Wishlisted:</label>
                        <select wire:model.live="filterWish" class="input-glass" style="padding: 0.3rem 1rem;">
                            <option value="">Any</option>
                            <option value="only">Only</option>
                            <option value="exclude">Exclude</option>
                        </select>
                    </div>
                </div>
            @endauth

            <!-- Fourth Row: Tags -->
            <div style="padding-top: 1rem; border-top: 1px solid rgba(255,255,255,0.05);">
                <label style="color: var(--text-secondary); font-size: 0.85rem; margin-bottom: 0.5rem; display: block;"><i class="ph-fill ph-tag"></i> Tags Filter</label>
                <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
                    <form wire:submit.prevent="addTag" style="display: flex; gap: 0.5rem; margin: 0;">
                        <input type="text" wire:model="tagInput" placeholder="Add a tag..." class="input-glass" style="width: 150px; padding: 0.4rem 1rem;">
                        <button type="submit" class="btn" style="padding: 0.4rem 1rem; background: rgba(255,255,255,0.1); border: 1px solid var(--glass-border); color: white; border-radius: 8px; cursor: pointer; transition: background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.2)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'"><i class="ph-bold ph-plus"></i> Add</button>
                    </form>
                    
                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                        @foreach($tags as $t)
                            <span style="background: rgba(59, 130, 246, 0.2); border: 1px solid rgba(59, 130, 246, 0.5); color: #60a5fa; padding: 4px 10px; border-radius: 12px; font-size: 0.85rem; display: flex; align-items: center; gap: 0.5rem;">
                                #{{ $t }}
                                <button type="button" wire:click="removeTag('{{ $t }}')" style="background: transparent; border: none; color: #60a5fa; cursor: pointer; padding: 0; display: flex; align-items: center;" title="Remove Tag">
                                    <i class="ph-bold ph-x"></i>
                                </button>
                            </span>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
