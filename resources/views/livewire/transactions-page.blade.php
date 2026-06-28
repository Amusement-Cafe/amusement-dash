<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Livewire\Attributes\Url;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Card;

use Livewire\Attributes\Title;

new #[Layout('layouts.app')] #[Title('Transactions')] class extends Component
{
    use WithPagination;

    #[Url]
    public ?string $id = null;

    public function selectTransaction($id)
    {
        $this->id = $id;
    }



    public function with(): array
    {
        $user = auth()->user();

        $transactions = Transaction::where('toID', $user->userID)
            ->orWhere('fromID', $user->userID)
            ->orderBy('dateCreated', 'desc')
            ->paginate(30);

        // Fetch users for the current page of transactions
        $otherUserIDs = collect($transactions->items())->map(function($tx) use ($user) {
            return $tx->toID === $user->userID ? $tx->fromID : $tx->toID;
        })->filter()->unique()->toArray();

        $transactionUsers = [];
        if (!empty($otherUserIDs)) {
            $transactionUsers = User::whereIn('userID', $otherUserIDs)->get()->keyBy('userID');
        }

        if (empty($this->id) && $transactions->count() > 0) {
            $this->id = (string) $transactions->first()->_id;
        }

        // Details for selected transaction
        $selectedTransaction = null;
        $selectedOtherUser = null;
        $transactionCards = [];

        if ($this->id) {
            $selectedTransaction = Transaction::where('_id', $this->id)->orWhere('transactionID', $this->id)->first();
            
            if ($selectedTransaction) {
                $otherID = $selectedTransaction->toID === $user->userID ? $selectedTransaction->fromID : $selectedTransaction->toID;
                if ($otherID) {
                    $selectedOtherUser = User::where('userID', $otherID)->first();
                }

                if (!empty($selectedTransaction->cardIDs)) {
                    $cardIDsToFetch = array_slice($selectedTransaction->cardIDs, 0, 20);
                    $transactionCards = Card::whereIn('cardID', $cardIDsToFetch)->get();
                }
            }
        }

        return [
            'transactions' => $transactions,
            'transactionUsers' => $transactionUsers,
            'selectedTransaction' => $selectedTransaction,
            'selectedOtherUser' => $selectedOtherUser,
            'transactionCards' => $transactionCards,
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
        <!-- Left column: Transactions List -->
        <div style="flex: 1; min-width: 350px;">
            <h1 style="font-size: 2rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="ph-fill ph-receipt" style="color: var(--accent-solid);"></i> My Transactions
            </h1>

            <div class="glass-panel" style="padding: 1rem;">
                @if($transactions->count() > 0)
                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        @foreach($transactions as $tx)
                            @php
                                $isIncoming = $tx->toID === $currentUser->userID;
                                $color = $isIncoming ? '#10b981' : '#ef4444';
                                $icon = $isIncoming ? 'ph-arrow-down-left' : 'ph-arrow-up-right';
                                $statusColor = match($tx->status ?? '') {
                                    'completed' => '#10b981',
                                    'pending' => '#f59e0b',
                                    'cancelled' => '#ef4444',
                                    default => 'var(--text-secondary)'
                                };
                                $otherID = $isIncoming ? $tx->fromID : $tx->toID;
                                $otherUser = $transactionUsers[$otherID] ?? null;
                                $isSelected = $id == $tx->_id || $id == $tx->transactionID;
                            @endphp
                            <div wire:click="selectTransaction('{{ $tx->_id }}')" style="background: {{ $isSelected ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.2)' }}; border: 1px solid {{ $isSelected ? 'var(--accent-solid)' : 'transparent' }}; padding: 0.8rem; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.05)'" onmouseout="this.style.background='{{ $isSelected ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.2)' }}'">
                                <div style="display: flex; align-items: center; gap: 0.8rem;">
                                    <div style="width: 32px; height: 32px; border-radius: 50%; background: {{ $color }}20; color: {{ $color }}; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0;">
                                        <i class="ph-bold {{ $icon }}"></i>
                                    </div>
                                    <div>
                                        <div style="font-weight: bold; font-size: 0.9rem;">
                                            @if($otherUser && $otherUser->username)
                                                {{ $isIncoming ? 'From' : 'To' }} {{ $otherUser->username }}
                                            @else
                                                {{ $isIncoming ? 'From' : 'To' }} <span style="color: var(--text-secondary);">{{ $otherID }}</span>
                                            @endif
                                        </div>
                                        <div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.2rem; display: flex; gap: 0.5rem;">
                                            <span style="color: {{ $statusColor }}; text-transform: capitalize;">{{ $tx->status ?? 'Completed' }}</span>
                                            <span>•</span>
                                            <span>{{ $tx->dateCreated ? \Carbon\Carbon::parse($tx->dateCreated)->diffForHumans() : 'Unknown date' }}</span>
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
                        {{ $transactions->links('components.custom-pagination') }}
                    </div>
                @else
                    <div style="text-align: center; padding: 4rem 0; opacity: 0.6;">
                        <i class="ph-light ph-receipt" style="font-size: 4rem; margin-bottom: 1rem;"></i>
                        <p style="margin: 0; font-size: 1.1rem;">You have no transactions yet.</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Right column: Transaction Details -->
        <div style="flex: 1; min-width: 350px;">
            <div style="position: sticky; top: 6rem;">
                @if($selectedTransaction)
                    @php
                        $isIncoming = $selectedTransaction->toID === $currentUser->userID;
                        $statusColor = match($selectedTransaction->status ?? '') {
                            'completed' => '#10b981',
                            'pending' => '#f59e0b',
                            'cancelled' => '#ef4444',
                            default => 'var(--text-secondary)'
                        };
                    @endphp
                    <div class="glass-panel" style="padding: 2rem; animation: fadeIn 0.3s ease-out;">
                        <h2 style="font-size: 1.5rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem; padding-bottom: 1rem; border-bottom: 1px solid var(--glass-border);">
                            <i class="ph-fill ph-file-text" style="color: var(--accent-solid);"></i> Transaction Details
                        </h2>
    
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
                            <div>
                                <div style="font-size: 0.8rem; color: var(--text-secondary); text-transform: uppercase; margin-bottom: 0.3rem;">Type</div>
                                <div style="font-weight: bold; font-size: 1.1rem; color: {{ $isIncoming ? '#10b981' : '#ef4444' }};">
                                    <i class="ph-bold {{ $isIncoming ? 'ph-arrow-down-left' : 'ph-arrow-up-right' }}"></i>
                                    {{ $isIncoming ? 'Incoming' : 'Outgoing' }}
                                </div>
                            </div>
                            
                            <div>
                                <div style="font-size: 0.8rem; color: var(--text-secondary); text-transform: uppercase; margin-bottom: 0.3rem;">Status</div>
                                <div style="font-weight: bold; font-size: 1.1rem; color: {{ $statusColor }}; text-transform: capitalize;">
                                    {{ $selectedTransaction->status ?? 'Completed' }}
                                </div>
                            </div>
                            
                            <div>
                                <div style="font-size: 0.8rem; color: var(--text-secondary); text-transform: uppercase; margin-bottom: 0.3rem;">Date</div>
                                <div style="font-weight: bold; font-size: 1.1rem;">
                                    {{ $selectedTransaction->dateCreated ? \Carbon\Carbon::parse($selectedTransaction->dateCreated)->format('M d, Y') : 'Unknown' }}
                                </div>
                            </div>
                            
                            <div>
                                <div style="font-size: 0.8rem; color: var(--text-secondary); text-transform: uppercase; margin-bottom: 0.3rem;">Cost</div>
                                <div style="font-weight: bold; font-size: 1.1rem; color: #fbbf24;">
                                    {{ number_format($selectedTransaction->cost ?? 0) }} 🍅
                                </div>
                            </div>
                        </div>
    
                        <div style="margin-bottom: 2rem;">
                            <div style="font-size: 0.8rem; color: var(--text-secondary); text-transform: uppercase; margin-bottom: 0.8rem;">Participant</div>
                            <div style="display: flex; align-items: center; gap: 1rem; background: rgba(0,0,0,0.3); padding: 1rem; border-radius: 8px; border: 1px solid var(--glass-border);">
                                @if($selectedOtherUser)
                                    @php
                                        $color = $selectedOtherUser->preferences['profile']['color'] ?? '16756480';
                                        $hexColor = '#' . str_pad(dechex($color), 6, "0", STR_PAD_LEFT);
                                        $avatarIndex = is_numeric($selectedOtherUser->userID) ? (substr($selectedOtherUser->userID, -1) % 6) : 0;
                                        $defaultAvatar = "https://cdn.discordapp.com/embed/avatars/{$avatarIndex}.png";
                                        
                                        $avatarUrl = \Illuminate\Support\Facades\Cache::remember('discord_avatar_' . $selectedOtherUser->userID, 86400, function() use ($selectedOtherUser, $defaultAvatar) {
                                            $botToken = config('services.discord.bot_token');
                                            if (!$botToken) return $defaultAvatar;
                                            
                                            $response = \Illuminate\Support\Facades\Http::withToken($botToken, 'Bot')
                                                ->timeout(3)
                                                ->get("https://discord.com/api/v10/users/{$selectedOtherUser->userID}");
                                                
                                            if ($response->successful() && !empty($response->json('avatar'))) {
                                                $hash = $response->json('avatar');
                                                $ext = str_starts_with($hash, 'a_') ? 'gif' : 'png';
                                                return "https://cdn.discordapp.com/avatars/{$selectedOtherUser->userID}/{$hash}.{$ext}?size=256";
                                            }
                                            return $defaultAvatar;
                                        });
                                    @endphp
                                    <div style="width: 48px; height: 48px; border-radius: 50%; border: 2px solid {{ $hexColor }}; overflow: hidden; background: var(--glass-border); flex-shrink: 0;">
                                        <img src="{{ $avatarUrl }}" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover;">
                                    </div>
                                    <div>
                                        <a href="/profile?id={{ $selectedOtherUser->userID }}" style="color: white; text-decoration: none; font-weight: bold; font-size: 1.2rem; transition: color 0.2s;" onmouseover="this.style.color='var(--accent-solid)'" onmouseout="this.style.color='white'">
                                            {{ $selectedOtherUser->username }}
                                        </a>
                                        <div style="color: var(--text-secondary); font-size: 0.85rem; cursor: pointer; display: inline-flex; align-items: center; gap: 0.2rem;" onclick="navigator.clipboard.writeText('{{ $selectedOtherUser->userID }}'); Livewire.dispatch('notify', { message: 'Copied ID to clipboard!' });" title="Click to copy">ID: {{ $selectedOtherUser->userID }} <i class="ph ph-copy"></i></div>
                                    </div>
                                @else
                                    <div style="width: 48px; height: 48px; border-radius: 50%; background: var(--glass-border); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0;">
                                        <i class="ph-light ph-user"></i>
                                    </div>
                                    <div>
                                        <div style="font-weight: bold; font-size: 1.2rem;">Unknown User</div>
                                        @php
                                            $otherID = $isIncoming ? $selectedTransaction->fromID : $selectedTransaction->toID;
                                        @endphp
                                        <div style="color: var(--text-secondary); font-size: 0.85rem; cursor: pointer; display: inline-flex; align-items: center; gap: 0.2rem;" onclick="navigator.clipboard.writeText('{{ $otherID }}'); Livewire.dispatch('notify', { message: 'Copied ID to clipboard!' });" title="Click to copy">ID: {{ $otherID }} <i class="ph ph-copy"></i></div>
                                    </div>
                                @endif
                            </div>
                        </div>
                        
                        <div>
                            <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 1rem;">
                                <h3 style="font-size: 1.2rem; margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                                    <i class="ph-fill ph-cards" style="color: #ec4899;"></i> Cards Involved
                                </h3>
                                <div style="font-size: 0.9rem; color: var(--text-secondary); display: flex; align-items: center; gap: 0.5rem;">
                                    <span>{{ count($selectedTransaction->cardIDs) }} Total (Showing {{ min(count($selectedTransaction->cardIDs), 20) }})</span>
                                    @if(count($selectedTransaction->cardIDs) > 0)
                                        <span style="color: rgba(255,255,255,0.2);">|</span>
                                        @php $txIdForLink = $selectedTransaction->transactionID ?? $selectedTransaction->_id; @endphp
                                        <a href="/cards?transactionID={{ $txIdForLink }}" style="color: #ec4899; text-decoration: none; font-weight: bold; transition: color 0.2s;" onmouseover="this.style.color='#f472b6'" onmouseout="this.style.color='#ec4899'">View All <i class="ph-bold ph-arrow-square-out"></i></a>
                                    @endif
                                </div>
                            </div>
                            
                            @if(count($transactionCards) > 0)
                                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                    @foreach($transactionCards as $card)
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
                                    <div style="color: var(--text-secondary);">No cards were attached to this transaction.</div>
                                </div>
                            @endif
                        </div>



                        @if(isset($selectedTransaction->transactionID))
                            <div style="margin-top: 2rem; padding-top: 1rem; border-top: 1px solid var(--glass-border); text-align: center;">
                                <span style="font-size: 0.8rem; color: var(--text-secondary); font-family: monospace;">TX ID: {{ $selectedTransaction->transactionID }}</span>
                            </div>
                        @endif
                    </div>
                </div>
            @else
                <div class="glass-panel" style="padding: 4rem 2rem; text-align: center; display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; border: 2px dashed var(--glass-border);">
                    <i class="ph-light ph-hand-pointing" style="font-size: 4rem; color: var(--text-secondary); margin-bottom: 1rem;"></i>
                    <h3 style="margin: 0 0 0.5rem 0;">Select a Transaction</h3>
                    <p style="margin: 0; color: var(--text-secondary); max-width: 250px;">Click on any transaction from the list to view its full details, including the cards that were exchanged.</p>
                </div>
            @endif
        </div>
    </div>
</div>
