@props(['card', 'collectionName' => null, 'owned' => false, 'fav' => false, 'wishlisted' => false])

<div class="glass-panel" 
     x-data="{ 
        isHovered: false,
        get isFav() { return typeof cardItemData !== 'undefined' ? cardItemData.fav : {{ $fav ? 'true' : 'false' }}; },
        get isWished() { return typeof cardItemData !== 'undefined' ? cardItemData.wishlisted : {{ $wishlisted ? 'true' : 'false' }}; }
     }"
     @mouseenter="isHovered = true"
     @mouseleave="isHovered = false"
     :style="`
        padding: 1rem; text-align: center; position: relative; overflow: hidden; transition: transform 0.2s, box-shadow 0.2s; cursor: pointer;
        transform: ${isHovered ? 'translateY(-5px)' : 'translateY(0)'};
        border: 1px solid ${isWished && isFav ? '#fbbf24' : (isWished ? '#fbbf24' : (isFav ? '#f472b6' : 'var(--glass-border)'))};
        box-shadow: ${
            isWished && isFav 
                ? (isHovered ? '-10px 10px 25px rgba(244, 114, 182, 0.6), 10px 10px 25px rgba(245, 158, 11, 0.6)' : '-8px 0 15px rgba(244, 114, 182, 0.3), 8px 0 15px rgba(245, 158, 11, 0.3)')
                : isWished
                    ? (isHovered ? '0 10px 25px rgba(245, 158, 11, 0.6)' : '0 0 15px rgba(245, 158, 11, 0.3)')
                    : isFav
                        ? (isHovered ? '0 10px 25px rgba(244, 114, 182, 0.6)' : '0 0 15px rgba(244, 114, 182, 0.3)')
                        : (isHovered ? '0 10px 25px var(--accent-glow)' : 'var(--glass-shadow)')
        };
     `"
>
    
    @if($owned)
        <div style="position: absolute; top: 0; right: 0; background: #34d399; color: black; font-size: 0.7rem; font-weight: bold; padding: 2px 8px; border-bottom-left-radius: 8px; z-index: 10; display: flex; flex-direction: column; align-items: center;">
            <span>OWNED</span>
            @if(is_numeric($owned) && $owned > 1)
                <span style="font-size: 0.65rem; background: rgba(0,0,0,0.15); border-radius: 4px; padding: 0 4px; margin-top: 1px;">{{ $owned }}x</span>
            @endif
        </div>
    @endif

    <template x-if="isWished">
        <div :style="`position: absolute; top: 0; right: {{ $owned ? '60px' : '0' }}; background: #fbbf24; color: black; font-size: 0.7rem; font-weight: bold; padding: 2px 8px; ${ {{ $owned ? 'true' : 'false' }} ? 'border-bottom-left-radius: 8px; border-bottom-right-radius: 8px;' : 'border-bottom-left-radius: 8px;' } z-index: 10;`">
            🌟 WISHED
        </div>
    </template>

    <template x-if="isFav">
        <div style="position: absolute; top: 0; left: 0; background: #f472b6; color: white; font-size: 0.7rem; font-weight: bold; padding: 2px 8px; border-bottom-right-radius: 8px; z-index: 10;">
            ❤️ FAV
        </div>
    </template>

    <div style="height: 250px; background: transparent; border-radius: 8px; margin-bottom: 1rem; display: flex; align-items: center; justify-content: center; overflow: hidden; position: relative;">
        @if(!empty($card->cardURL))
            <img src="{{ $card->cardURL }}" alt="{{ $card->displayName ?? $card->cardName }}" style="max-width: 100%; max-height: 100%; object-fit: contain;">
        @else
            <span style="color: var(--text-secondary);">No Image</span>
        @endif
    </div>
    
    <h3 style="font-size: 1.1rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--text-primary);">{{ $card->displayName ?? $card->cardName ?? 'Unknown Card' }}</h3>
    
    @if($collectionName)
        <p style="font-size: 0.8rem; color: var(--text-secondary); margin-top: 0.2rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
            {{ $collectionName }}
        </p>
    @endif

    <div style="color: var(--text-secondary); font-size: 0.9rem; margin-top: 0.5rem; display: flex; justify-content: space-between; align-items: center;">
        <span>{{ str_repeat('⭐', $card->rarity ?? 1) }}</span>
        <span style="background: var(--accent-solid); color: white; padding: 2px 6px; border-radius: 4px; font-size: 0.8rem; font-weight: bold;">#{{ $card->cardID ?? '?' }}</span>
    </div>
</div>
