<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Livewire\Attributes\Url;
use App\Models\Claim;
use App\Models\User;
use App\Models\Card;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    #[Url]
    public ?string $id = null;

    public function selectClaim($id)
    {
        $this->id = $id;
    }

    public function with(): array
    {
        $user = auth()->user();

        $claims = Claim::where('userID', $user->userID)
            ->orderBy('timeClaimed', 'desc')
            ->paginate(30);

        if (empty($this->id) && $claims->count() > 0) {
            $this->id = (string) $claims->first()->_id;
        }

        $selectedClaim = null;
        $claimCards = [];

        if ($this->id) {
            $selectedClaim = Claim::where('_id', $this->id)->orWhere('claimID', $this->id)->first();
            
            if ($selectedClaim) {
                if (!empty($selectedClaim->cardIDs)) {
                    $cardIDsToFetch = array_slice($selectedClaim->cardIDs, 0, 20);
                    $claimCards = Card::whereIn('cardID', $cardIDsToFetch)->get();
                }
            }
        }

        return [
            'claims' => $claims,
            'selectedClaim' => $selectedClaim,
            'claimCards' => $claimCards,
            'currentUser' => $user,
        ];
    }
};
?>

<div>
    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>

    <div style="display: flex; gap: 2rem; align-items: stretch; flex-wrap: wrap;">
        <!-- Left column: Claims List -->
        <div style="flex: 1; min-width: 350px;">
            <h1 style="font-size: 2rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="ph-fill ph-hand-coins" style="color: var(--accent-solid);"></i> My Claims
            </h1>

            <div class="glass-panel" style="padding: 1rem;">
                @if($claims->count() > 0)
                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        @foreach($claims as $tx)
                            @php
                                $isSelected = $id == $tx->_id || $id == $tx->claimID;
                            @endphp
                            <div wire:click="selectClaim('{{ $tx->_id }}')" style="background: {{ $isSelected ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.2)' }}; border: 1px solid {{ $isSelected ? 'var(--accent-solid)' : 'transparent' }}; padding: 0.8rem; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.05)'" onmouseout="this.style.background='{{ $isSelected ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.2)' }}'">
                                <div style="display: flex; align-items: center; gap: 0.8rem;">
                                    <div style="width: 32px; height: 32px; border-radius: 50%; background: rgba(16, 185, 129, 0.2); color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0;">
                                        <i class="ph-bold ph-hand-coins"></i>
                                    </div>
                                    <div>
                                        <div style="font-weight: bold; font-size: 0.9rem;">
                                            {{ $tx->promo ? 'Promo Claim' : 'Regular Claim' }}
                                        </div>
                                        <div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.2rem; display: flex; gap: 0.5rem;">
                                            <span>{{ $tx->timeClaimed ? \Carbon\Carbon::parse($tx->timeClaimed)->diffForHumans() : 'Unknown date' }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div style="text-align: right; font-weight: bold;">
                                    @if($tx->cost > 0)
                                        <div style="font-size: 0.9rem; display: flex; align-items: center; justify-content: flex-end; gap: 0.2rem;">
                                            {{ number_format($tx->cost) }} 🍅
                                        </div>
                                    @endif
                                    @if(!empty($tx->cardIDs))
                                        <div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.2rem;">
                                            <i class="ph-fill ph-cards"></i> {{ count($tx->cardIDs) }} Cards
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                    
                    <div style="margin-top: 1.5rem;">
                        {{ $claims->links('components.custom-pagination') }}
                    </div>
                @else
                    <div style="padding: 3rem; text-align: center;">
                        <i class="ph-light ph-hand-coins" style="font-size: 3rem; color: var(--text-secondary); margin-bottom: 1rem;"></i>
                        <p style="margin: 0; font-size: 1.1rem;">You have no claims yet.</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Right column: Claim Details -->
        <div style="flex: 2; min-width: 400px;">
            <div style="position: sticky; top: 6rem;">
                <div class="glass-panel" style="padding: 2rem; min-height: 400px;">
                    @if($selectedClaim)
                        <div style="animation: fadeIn 0.3s ease-out;">
                            <!-- Header -->
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem; border-bottom: 1px solid var(--glass-border); padding-bottom: 1rem;">
                                <div>
                                    <h2 style="margin: 0 0 0.5rem 0; font-size: 1.8rem; display: flex; align-items: center; gap: 0.5rem;">
                                        <i class="ph-fill ph-hand-coins" style="color: #10b981;"></i> Claim Details
                                    </h2>
                                    <div style="color: var(--text-secondary); font-size: 0.9rem;">
                                        {{ $selectedClaim->timeClaimed ? \Carbon\Carbon::parse($selectedClaim->timeClaimed)->format('M d, Y g:i A') : 'Unknown Date' }}
                                    </div>
                                </div>
                                <div style="text-align: right;">
                                    <div style="background: rgba(16, 185, 129, 0.2); color: #10b981; padding: 6px 12px; border-radius: 20px; font-weight: bold; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 0.5rem;">
                                        <i class="ph-bold ph-check-circle"></i> Completed
                                    </div>
                                    @if($selectedClaim->cost > 0)
                                        <div style="margin-top: 0.5rem; font-size: 1.2rem; font-weight: bold; color: white;">
                                            Cost: {{ number_format($selectedClaim->cost) }} 🍅
                                        </div>
                                    @endif
                                </div>
                            </div>
    
                            <!-- Cards Grid -->
                            <div style="margin-bottom: 2rem;">
                                <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 1rem;">
                                    <h3 style="font-size: 1.2rem; margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                                        <i class="ph-fill ph-cards" style="color: #ec4899;"></i> Cards Claimed
                                    </h3>
                                    <div style="font-size: 0.9rem; color: var(--text-secondary); display: flex; align-items: center; gap: 0.5rem;">
                                        <span>{{ count($selectedClaim->cardIDs) }} Total (Showing {{ min(count($selectedClaim->cardIDs), 20) }})</span>
                                        @if(count($selectedClaim->cardIDs) > 0)
                                            <span style="color: rgba(255,255,255,0.2);">|</span>
                                            @php $claimIdForLink = $selectedClaim->claimID ?? $selectedClaim->_id; @endphp
                                            <a href="/cards?claimID={{ $claimIdForLink }}" style="color: #ec4899; text-decoration: none; font-weight: bold; transition: color 0.2s;" onmouseover="this.style.color='#f472b6'" onmouseout="this.style.color='#ec4899'">View All <i class="ph-bold ph-arrow-square-out"></i></a>
                                        @endif
                                    </div>
                                </div>
                                
                                @if(count($claimCards) > 0)
                                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                        @foreach($claimCards as $card)
                                            <a href="/cards?search={{ $card->cardID }}" target="_blank" style="text-decoration: none; color: inherit; display: block;">
                                                <div style="background: rgba(0,0,0,0.3); border-radius: 8px; padding: 0.8rem 1rem; border: 1px solid var(--glass-border); transition: transform 0.2s, background 0.2s; display: flex; justify-content: space-between; align-items: center;" onmouseover="this.style.transform='translateY(-2px)'; this.style.background='rgba(255,255,255,0.05)';" onmouseout="this.style.transform='translateY(0)'; this.style.background='rgba(0,0,0,0.3)';">
                                                    <div style="font-size: 0.95rem; font-weight: bold;">
                                                        {{ $card->displayName ?? $card->cardName }}
                                                        <div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.2rem; font-family: monospace;">ID: {{ $card->cardID }}</div>
                                                    </div>
                                                    <div style="color: var(--text-secondary); font-size: 0.85rem; white-space: nowrap; margin-left: 1rem;">
                                                        {{ str_repeat('⭐', $card->rarity ?? 1) }}
                                                    </div>
                                                </div>
                                            </a>
                                        @endforeach
                                    </div>
                                @else
                                    <div style="padding: 2rem; text-align: center; background: rgba(0,0,0,0.2); border-radius: 8px; border: 1px dashed var(--glass-border);">
                                        <i class="ph-light ph-empty" style="font-size: 2rem; color: var(--text-secondary); margin-bottom: 0.5rem;"></i>
                                        <div style="color: var(--text-secondary);">No cards were found for this claim.</div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @else
                        <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: var(--text-secondary); padding: 4rem;">
                            <i class="ph-light ph-hand-coins" style="font-size: 4rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                            <p style="font-size: 1.1rem;">Select a claim from the left to view its details.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
