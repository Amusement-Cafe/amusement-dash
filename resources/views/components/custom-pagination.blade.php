@if ($paginator->hasPages())
    <nav style="display: flex; justify-content: center; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
        {{-- Previous Page Link --}}
        @if ($paginator->onFirstPage())
            <span style="padding: 0.5rem 1rem; background: var(--glass-bg); border-radius: 8px; border: 1px solid var(--glass-border); opacity: 0.5; cursor: not-allowed; color: var(--text-secondary);">
                <i class="ph-bold ph-caret-left"></i>
            </span>
        @else
            <button wire:click="previousPage" wire:loading.attr="disabled" rel="prev" style="padding: 0.5rem 1rem; background: var(--glass-bg); border-radius: 8px; border: 1px solid var(--glass-border); cursor: pointer; color: white; transition: all 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.1)'" onmouseout="this.style.background='var(--glass-bg)'">
                <i class="ph-bold ph-caret-left"></i>
            </button>
        @endif

        {{-- Pagination Elements --}}
        @foreach ($elements as $element)
            {{-- "Three Dots" Separator --}}
            @if (is_string($element))
                <span style="padding: 0.5rem; color: var(--text-secondary); font-weight: bold;">{{ $element }}</span>
            @endif

            {{-- Array Of Links --}}
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span style="padding: 0.5rem 1rem; background: var(--accent-solid); border-radius: 8px; font-weight: bold; color: white; box-shadow: 0 0 10px var(--accent-glow);">
                            {{ $page }}
                        </span>
                    @else
                        <button wire:click="gotoPage({{ $page }})" style="padding: 0.5rem 1rem; background: var(--glass-bg); border-radius: 8px; border: 1px solid var(--glass-border); cursor: pointer; color: white; transition: all 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.1)'" onmouseout="this.style.background='var(--glass-bg)'">
                            {{ $page }}
                        </button>
                    @endif
                @endforeach
            @endif
        @endforeach

        {{-- Next Page Link --}}
        @if ($paginator->hasMorePages())
            <button wire:click="nextPage" wire:loading.attr="disabled" rel="next" style="padding: 0.5rem 1rem; background: var(--glass-bg); border-radius: 8px; border: 1px solid var(--glass-border); cursor: pointer; color: white; transition: all 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.1)'" onmouseout="this.style.background='var(--glass-bg)'">
                <i class="ph-bold ph-caret-right"></i>
            </button>
        @else
            <span style="padding: 0.5rem 1rem; background: var(--glass-bg); border-radius: 8px; border: 1px solid var(--glass-border); opacity: 0.5; cursor: not-allowed; color: var(--text-secondary);">
                <i class="ph-bold ph-caret-right"></i>
            </span>
        @endif
    </nav>
@endif
