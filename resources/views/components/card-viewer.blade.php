@props(['card', 'collectionName' => null, 'owned' => false, 'fav' => false])

<div class="glass-panel" style="padding: 1rem; text-align: center; border: 1px solid var(--glass-border); position: relative; overflow: hidden; transition: transform 0.2s, box-shadow 0.2s; cursor: pointer;" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 10px 25px var(--accent-glow)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='var(--glass-shadow)';">
    
    @if($owned)
        <div style="position: absolute; top: 0; right: 0; background: #34d399; color: black; font-size: 0.7rem; font-weight: bold; padding: 2px 8px; border-bottom-left-radius: 8px; z-index: 10;">
            OWNED
        </div>
    @endif

    @if($fav)
        <div style="position: absolute; top: 0; left: 0; background: #f472b6; color: white; font-size: 0.7rem; font-weight: bold; padding: 2px 8px; border-bottom-right-radius: 8px; z-index: 10;">
            ❤️ FAV
        </div>
    @endif

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
