<?php

use Livewire\Volt\Component;
use App\Models\User;
use App\Models\UserStat;
use App\Models\Transaction;
use App\Models\Auction;
use App\Models\Claim;
use App\Models\UserCard;
use App\Models\UserInventory;
use App\Models\Card;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Admin Panel')] class extends Component
{
    use WithPagination;
    public string $searchUser = '';
    public string $targetUserId = '';
    public string $auditDateStart = '';
    public string $auditDateEnd = '';
    
    public string $tab = 'edit'; // 'edit' or 'audit'

    // Edit User Fields
    public int $editTomatoes = 0;
    public int $editLemons = 0;
    public int $editVials = 0;

    public string $giveCardId = '';
    
    public string $giveItemType = '';
    public string $giveItemId = '';

    public $successMessage = '';

    public function mount()
    {
        abort_unless(auth()->check() && in_array('admin', auth()->user()->roles ?? []), 403);
    }

    public function loadUser()
    {
        $this->successMessage = '';
        if (empty($this->searchUser)) return;

        // Find by userID or username
        $user = User::where('userID', $this->searchUser)->orWhere('username', new \MongoDB\BSON\Regex($this->searchUser, 'i'))->first();
        if ($user) {
            $this->targetUserId = $user->userID;
            $this->editTomatoes = $user->tomatoes ?? 0;
            $this->editLemons = $user->lemons ?? 0;
            $this->editVials = $user->vials ?? 0;
            $this->auditDateStart = \Carbon\Carbon::now()->subDays(30)->format('Y-m-d');
            $this->auditDateEnd = \Carbon\Carbon::now()->format('Y-m-d');
        } else {
            $this->targetUserId = '';
        }
    }

    public function saveBalances()
    {
        if (empty($this->targetUserId)) return;

        $response = \Illuminate\Support\Facades\Http::withHeaders([
            'Authorization' => env('AMUSE_API_KEY')
        ])->timeout(5)->post(env('AMUSE_API_ROOT') . '/user/admin/balances?user=' . auth()->user()->userID, [
            'targetUserID' => $this->targetUserId,
            'tomatoes' => $this->editTomatoes,
            'lemons' => $this->editLemons,
            'vials' => $this->editVials,
        ]);

        $this->successMessage = $response->successful() ? "Balances updated successfully!" : "Failed: " . $response->body();
    }

    public function giveCard()
    {
        if (empty($this->targetUserId) || empty($this->giveCardId)) return;
        
        $card = Card::where('cardID', (int) $this->giveCardId)->first();
        if (!$card) {
            $this->successMessage = "Card not found.";
            return;
        }

        $response = \Illuminate\Support\Facades\Http::withHeaders([
            'Authorization' => env('AMUSE_API_KEY')
        ])->timeout(5)->put(env('AMUSE_API_ROOT') . '/user/admin/card?user=' . auth()->user()->userID, [
            'targetUserID' => $this->targetUserId,
            'cardID' => (int) $this->giveCardId,
        ]);

        $this->giveCardId = '';
        $this->successMessage = $response->successful() ? "Card {$card->cardName} given to user!" : "Failed: " . $response->body();
    }

    public function giveItem()
    {
        if (empty($this->targetUserId) || empty($this->giveItemType) || empty($this->giveItemId)) return;

        $response = \Illuminate\Support\Facades\Http::withHeaders([
            'Authorization' => env('AMUSE_API_KEY')
        ])->timeout(5)->post(env('AMUSE_API_ROOT') . '/user/admin/item?user=' . auth()->user()->userID, [
            'targetUserID' => $this->targetUserId,
            'type' => $this->giveItemType,
            'itemID' => $this->giveItemId,
        ]);

        $this->giveItemType = '';
        $this->giveItemId = '';
        $this->successMessage = $response->successful() ? "Item given to user!" : "Failed: " . $response->body();
    }

    public function resetDaily()
    {
        if (empty($this->targetUserId)) return;

        $response = \Illuminate\Support\Facades\Http::withHeaders([
            'Authorization' => env('AMUSE_API_KEY')
        ])->timeout(5)->post(env('AMUSE_API_ROOT') . '/user/admin/resetdaily?user=' . auth()->user()->userID, [
            'targetUserID' => $this->targetUserId,
        ]);

        $this->successMessage = $response->successful() ? "Daily streak reset! User can claim daily again." : "Failed: " . $response->body();
    }

    public function with(): array
    {
        $targetUser = null;
        if (!empty($this->targetUserId)) {
            $targetUser = User::where('userID', $this->targetUserId)->first();
        }

        $stats = null;
        $transactions = [];
        $auctions = [];
        $claims = [];
        $userMap = [];
        $graphData = [];
        $minDate = \Carbon\Carbon::now()->subDays(30)->format('Y-m-d');
        $maxDate = \Carbon\Carbon::now()->format('Y-m-d');

        if ($targetUser && $this->tab === 'audit') {
            $stats = UserStat::where('userID', $targetUser->userID)->orderBy('daily', 'desc')->first();
            
            $oldestStat = UserStat::where('userID', $targetUser->userID)->orderBy('daily', 'asc')->first();
            if ($oldestStat && isset($oldestStat->daily)) {
                $minD = \Carbon\Carbon::parse($oldestStat->daily);
                $thirtyDaysAgo = \Carbon\Carbon::now()->subDays(30);
                $minDate = $minD->greaterThan($thirtyDaysAgo) ? $thirtyDaysAgo->format('Y-m-d') : $minD->format('Y-m-d');
            }

            if (empty($this->auditDateStart) || empty($this->auditDateEnd)) {
                $this->auditDateStart = \Carbon\Carbon::now()->subDays(30)->format('Y-m-d');
                $this->auditDateEnd = $maxDate;
            }
            
            $txQuery = Transaction::where(function($query) use ($targetUser) {
                $query->where('fromID', $targetUser->userID)
                      ->orWhere('toID', $targetUser->userID);
            });
            $aucQuery = Auction::where('userID', $targetUser->userID);
            $claimQuery = Claim::where('userID', $targetUser->userID);
            $statQuery = UserStat::where('userID', $targetUser->userID);

            if (!empty($this->auditDateStart) && !empty($this->auditDateEnd)) {
                $start = \Carbon\Carbon::parse($this->auditDateStart)->startOfDay();
                $end = \Carbon\Carbon::parse($this->auditDateEnd)->endOfDay();
                
                $txQuery->where('dateCreated', '>=', $start)->where('dateCreated', '<=', $end);
                $aucQuery->where('time', '>=', $start)->where('time', '<=', $end);
                $claimQuery->where('timeClaimed', '>=', $start)->where('timeClaimed', '<=', $end);
                $statQuery->where('daily', '>=', $start)->where('daily', '<=', $end);
            }

            $transactions = $txQuery->orderBy('dateCreated', 'desc')->paginate(20, ['*'], 'txPage');
            $auctions = $aucQuery->orderBy('time', 'desc')->paginate(20, ['*'], 'aucPage');
            $claims = $claimQuery->orderBy('timeClaimed', 'desc')->paginate(20, ['*'], 'claimPage');

            $txIds = collect($transactions->items())->map(function($tx) {
                return [$tx->fromID, $tx->toID];
            })->flatten()->filter()->unique()->toArray();
            
            if (count($txIds) > 0) {
                $users = User::whereIn('userID', $txIds)->get();
                foreach($users as $u) {
                    $userMap[$u->userID] = $u->username;
                }
            }

            $allStats = $statQuery->orderBy('daily', 'asc')->get();
            $chartLabels = [];
            $chartEconomy = ['tomatoIn' => [], 'tomatoOut' => [], 'lemonIn' => [], 'lemonOut' => []];
            $chartMarket = ['aucSell' => [], 'aucBid' => [], 'aucWin' => [], 'userSell' => [], 'userBuy' => []];
            $chartClaims = ['claims' => [], 'promoclaims' => []];

            foreach ($allStats as $s) {
                if (!isset($s->daily)) continue;
                $dateStr = \Carbon\Carbon::parse($s->daily)->format('Y-m-d');
                
                $chartLabels[] = $dateStr;
                $chartEconomy['tomatoIn'][] = $s->tomatoIn ?? 0;
                $chartEconomy['tomatoOut'][] = $s->tomatoOut ?? 0;
                $chartEconomy['lemonIn'][] = $s->lemonIn ?? 0;
                $chartEconomy['lemonOut'][] = $s->lemonOut ?? 0;

                $chartMarket['aucSell'][] = $s->aucSell ?? 0;
                $chartMarket['aucBid'][] = $s->aucBid ?? 0;
                $chartMarket['aucWin'][] = $s->aucWin ?? 0;
                $chartMarket['userSell'][] = $s->userSell ?? 0;
                $chartMarket['userBuy'][] = $s->userBuy ?? 0;

                $chartClaims['claims'][] = $s->claims ?? 0;
                $chartClaims['promoclaims'][] = $s->promoclaims ?? 0;
            }
            $graphData = [
                'labels' => $chartLabels,
                'economy' => $chartEconomy,
                'market' => $chartMarket,
                'claims' => $chartClaims
            ];
        }

        return [
            'targetUser' => $targetUser,
            'stats' => $stats,
            'transactions' => $transactions,
            'auctions' => $auctions,
            'claims' => $claims,
            'userMap' => $userMap,
            'graphData' => $graphData,
            'minDate' => $minDate,
            'maxDate' => $maxDate
        ];
    }
};
?>

<div>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <div class="glass-panel" style="padding: 2rem; margin-bottom: 2rem;">
        <h1 style="font-size: 2rem; margin-bottom: 1rem; color: #f87171; display: flex; align-items: center; gap: 0.5rem;">
            <i class="ph-fill ph-shield-check"></i> Admin Panel
        </h1>

        <div style="display: flex; gap: 1rem; margin-bottom: 1rem;">
            <input type="text" wire:model="searchUser" wire:keydown.enter="loadUser" placeholder="Enter User ID or Username" class="form-input" style="flex: 1; max-width: 400px; padding: 0.8rem; border-radius: 8px; background: rgba(0,0,0,0.2); border: 1px solid var(--glass-border); color: white;">
            <button wire:click="loadUser" class="btn btn-primary" style="background: var(--accent-solid); color: white; padding: 0.8rem 1.5rem; border-radius: 8px; font-weight: bold; border: none; cursor: pointer;">Search</button>
        </div>

        @if($successMessage)
            <div style="background: rgba(34, 197, 94, 0.2); border: 1px solid #22c55e; color: #4ade80; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                {{ $successMessage }}
            </div>
        @endif
    </div>

    @if($targetUser)
        <div style="display: flex; gap: 1rem; margin-bottom: 2rem;">
            <button wire:click="$set('tab', 'edit')" style="padding: 0.8rem 1.5rem; border-radius: 8px; font-weight: bold; cursor: pointer; border: 1px solid var(--glass-border); background: {{ $tab === 'edit' ? 'var(--accent-solid)' : 'rgba(0,0,0,0.2)' }}; color: white; transition: 0.2s;">
                <i class="ph ph-pencil-simple"></i> Edit User
            </button>
            <button wire:click="$set('tab', 'audit')" style="padding: 0.8rem 1.5rem; border-radius: 8px; font-weight: bold; cursor: pointer; border: 1px solid var(--glass-border); background: {{ $tab === 'audit' ? '#a855f7' : 'rgba(0,0,0,0.2)' }}; color: white; transition: 0.2s;">
                <i class="ph ph-magnifying-glass"></i> Audit User
            </button>
        </div>

        <div class="glass-panel" style="padding: 2rem; margin-bottom: 2rem; border-top: 4px solid {{ $tab === 'edit' ? 'var(--accent-solid)' : '#a855f7' }};">
            @php
                $avatarIndex = is_numeric($targetUser->userID) ? (substr($targetUser->userID, -1) % 6) : 0;
                $defaultAvatar = "https://cdn.discordapp.com/embed/avatars/{$avatarIndex}.png";
                $avatarUrl = \Illuminate\Support\Facades\Cache::remember('discord_avatar_' . $targetUser->userID, 86400, function() use ($targetUser, $defaultAvatar) {
                    $botToken = config('services.discord.bot_token');
                    if (!$botToken) return $defaultAvatar;
                    $response = \Illuminate\Support\Facades\Http::withToken($botToken, 'Bot')->timeout(3)->get("https://discord.com/api/v10/users/{$targetUser->userID}");
                    if ($response->successful() && !empty($response->json('avatar'))) {
                        $hash = $response->json('avatar');
                        $ext = str_starts_with($hash, 'a_') ? 'gif' : 'png';
                        return "https://cdn.discordapp.com/avatars/{$targetUser->userID}/{$hash}.{$ext}?size=256";
                    }
                    return $defaultAvatar;
                });
            @endphp
            <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 2rem; padding-bottom: 1rem; border-bottom: 1px solid rgba(255,255,255,0.05);">
                <img src="{{ $avatarUrl }}" alt="Avatar" style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover;">
                <div>
                    <h2 style="margin: 0; font-size: 1.5rem;">{{ $targetUser->username }}</h2>
                    <span style="color: var(--text-secondary); font-family: monospace;">{{ $targetUser->userID }}</span>
                </div>
                <div style="margin-left: auto;">
                    <a href="{{ route('profile.show') }}?id={{ $targetUser->userID }}" target="_blank" class="btn" style="background: rgba(255,255,255,0.1); padding: 0.5rem 1rem; border-radius: 8px; color: white; text-decoration: none; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="ph ph-arrow-square-out"></i> View Profile
                    </a>
                </div>
            </div>

            @if($tab === 'edit')
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
                    
                    <!-- Balances Edit -->
                    <div style="background: rgba(0,0,0,0.2); padding: 1.5rem; border-radius: 12px; border: 1px solid var(--glass-border);">
                        <h3 style="margin-top: 0; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;"><i class="ph-fill ph-coins" style="color: #fbbf24;"></i> Edit Balances</h3>
                        
                        <div style="display: flex; flex-direction: column; gap: 1rem;">
                            <div>
                                <label style="color: var(--text-secondary); display: block; margin-bottom: 0.5rem;">🍅 Tomatoes</label>
                                <input type="number" wire:model="editTomatoes" class="form-input" style="width: 100%; padding: 0.5rem; border-radius: 4px; background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); color: white;">
                            </div>
                            <div>
                                <label style="color: var(--text-secondary); display: block; margin-bottom: 0.5rem;">🍋 Lemons</label>
                                <input type="number" wire:model="editLemons" class="form-input" style="width: 100%; padding: 0.5rem; border-radius: 4px; background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); color: white;">
                            </div>
                            <div>
                                <label style="color: var(--text-secondary); display: block; margin-bottom: 0.5rem;">🧪 Vials</label>
                                <input type="number" wire:model="editVials" class="form-input" style="width: 100%; padding: 0.5rem; border-radius: 4px; background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); color: white;">
                            </div>
                            <button wire:click="saveBalances" class="btn" style="background: var(--accent-solid); color: white; padding: 0.8rem; border-radius: 8px; border: none; font-weight: bold; cursor: pointer; margin-top: 0.5rem;">Save Balances</button>
                        </div>
                    </div>

                    <!-- Give Items & Actions -->
                    <div style="display: flex; flex-direction: column; gap: 2rem;">
                        <div style="background: rgba(0,0,0,0.2); padding: 1.5rem; border-radius: 12px; border: 1px solid var(--glass-border);">
                            <h3 style="margin-top: 0; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;"><i class="ph-fill ph-cards" style="color: #ec4899;"></i> Give Card</h3>
                            <div style="display: flex; gap: 0.5rem;">
                                <input type="number" wire:model="giveCardId" placeholder="Card ID" class="form-input" style="flex: 1; padding: 0.5rem; border-radius: 4px; background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); color: white;">
                                <button wire:click="giveCard" class="btn" style="background: #ec4899; color: white; padding: 0.5rem 1rem; border-radius: 4px; border: none; font-weight: bold; cursor: pointer;">Give</button>
                            </div>
                        </div>

                        <div style="background: rgba(0,0,0,0.2); padding: 1.5rem; border-radius: 12px; border: 1px solid var(--glass-border);">
                            <h3 style="margin-top: 0; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;"><i class="ph-fill ph-package" style="color: #3b82f6;"></i> Give Inventory Item</h3>
                            <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                <input type="text" wire:model="giveItemType" placeholder="Type (e.g., ticket, recipe)" class="form-input" style="width: 100%; padding: 0.5rem; border-radius: 4px; background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); color: white;">
                                <input type="text" wire:model="giveItemId" placeholder="Item ID" class="form-input" style="width: 100%; padding: 0.5rem; border-radius: 4px; background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); color: white;">
                                <button wire:click="giveItem" class="btn" style="background: #3b82f6; color: white; padding: 0.5rem 1rem; border-radius: 4px; border: none; font-weight: bold; cursor: pointer;">Give Item</button>
                            </div>
                        </div>

                        <div style="background: rgba(0,0,0,0.2); padding: 1.5rem; border-radius: 12px; border: 1px solid var(--glass-border);">
                            <h3 style="margin-top: 0; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;"><i class="ph-fill ph-wrench" style="color: #9ca3af;"></i> Account Actions</h3>
                            <button wire:click="resetDaily" class="btn" style="width: 100%; background: rgba(255,255,255,0.1); color: white; padding: 0.8rem; border-radius: 8px; border: 1px solid var(--glass-border); font-weight: bold; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.5rem; transition: background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.2)';" onmouseout="this.style.background='rgba(255,255,255,0.1)';">
                                <i class="ph ph-arrow-counter-clockwise"></i> Reset Daily Claim Cooldown
                            </button>
                        </div>
                    </div>
                </div>

            @elseif($tab === 'audit')
                <div style="display: grid; grid-template-columns: 1fr; gap: 2rem;">
                    
                    @if($stats)
                        <div style="background: rgba(0,0,0,0.2); padding: 1.5rem; border-radius: 12px; border: 1px solid var(--glass-border);">
                            <h3 style="margin-top: 0; margin-bottom: 1rem; color: #a855f7; display: flex; align-items: center; gap: 0.5rem;"><i class="ph-fill ph-chart-bar"></i> User Statistics</h3>
                            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem;">
                                @php
                                    $statLabels = [
                                        'claims' => 'Claims', 'aucSell' => 'Auctions Sold', 'aucBid' => 'Auction Bids', 'aucWin' => 'Auctions Won',
                                        'userSell' => 'User Trades (Sell)', 'userBuy' => 'User Trades (Buy)',
                                        'tomatoIn' => 'Tomatoes In', 'tomatoOut' => 'Tomatoes Out',
                                        'lemonIn' => 'Lemons In', 'lemonOut' => 'Lemons Out'
                                    ];
                                @endphp
                                @foreach($statLabels as $key => $label)
                                    <div style="background: rgba(255,255,255,0.02); padding: 0.8rem; border-radius: 8px; border: 1px solid rgba(255,255,255,0.05);">
                                        <div style="color: var(--text-secondary); font-size: 0.8rem; text-transform: uppercase;">{{ $label }}</div>
                                        <div style="font-size: 1.2rem; font-weight: bold;">{{ number_format($stats->$key ?? 0) }}</div>
                                    </div>
                                @endforeach
                                <div style="background: rgba(255,255,255,0.02); padding: 0.8rem; border-radius: 8px; border: 1px solid rgba(255,255,255,0.05);">
                                    <div style="color: var(--text-secondary); font-size: 0.8rem; text-transform: uppercase;">Last Claim Date</div>
                                    <div style="font-size: 1.2rem; font-weight: bold;">{{ isset($stats->daily) ? \Carbon\Carbon::parse($stats->daily)->format('Y-m-d') : 'N/A' }}</div>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Date Filter -->
                    <div style="background: rgba(0,0,0,0.2); padding: 1.5rem; border-radius: 12px; border: 1px solid var(--glass-border); margin-bottom: 2rem;">
                        <h3 style="margin-top: 0; margin-bottom: 1rem; color: #a855f7; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="ph-fill ph-calendar-blank"></i> Timeline Filter
                        </h3>
                        <div style="display: flex; align-items: flex-end; gap: 1rem; flex-wrap: wrap;">
                            <div style="flex: 1; min-width: 200px;">
                                <label style="color: var(--text-secondary); display: block; font-size: 0.8rem; text-transform: uppercase; margin-bottom: 0.5rem;">Start Date</label>
                                <input type="date" wire:model="auditDateStart" min="{{ $minDate }}" max="{{ $maxDate }}" class="form-input" style="width: 100%; padding: 0.8rem; border-radius: 8px; background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); color: white;">
                            </div>
                            <div style="flex: 1; min-width: 200px;">
                                <label style="color: var(--text-secondary); display: block; font-size: 0.8rem; text-transform: uppercase; margin-bottom: 0.5rem;">End Date</label>
                                <input type="date" wire:model="auditDateEnd" min="{{ $minDate }}" max="{{ $maxDate }}" class="form-input" style="width: 100%; padding: 0.8rem; border-radius: 8px; background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); color: white;">
                            </div>
                            <div>
                                <button wire:click="$refresh" class="btn" style="background: var(--accent-solid); color: white; padding: 0.8rem 2rem; border-radius: 8px; font-weight: bold; border: none; cursor: pointer; transition: opacity 0.2s;" onmouseover="this.style.opacity=0.8" onmouseout="this.style.opacity=1">Apply Filter</button>
                            </div>
                        </div>
                    </div>

                    @if(!empty($graphData['labels']))
                    <div wire:key="charts-{{ $auditDateStart }}-{{ $auditDateEnd }}" style="background: rgba(0,0,0,0.2); padding: 1.5rem; border-radius: 12px; border: 1px solid var(--glass-border);" x-data="auditCharts({{ json_encode($graphData) }})">
                        <h3 style="margin-top: 0; margin-bottom: 1rem; color: #a855f7; display: flex; align-items: center; gap: 0.5rem;"><i class="ph-fill ph-trend-up"></i> Activity Over Time</h3>
                        
                        <div style="display: grid; grid-template-columns: 1fr; gap: 2rem;">
                            <div>
                                <h4 style="color: var(--text-secondary); margin-bottom: 0.5rem;">Economy Flow</h4>
                                <div style="position: relative; height: 300px; width: 100%;">
                                    <canvas x-ref="ecoChart"></canvas>
                                </div>
                            </div>
                            <div>
                                <h4 style="color: var(--text-secondary); margin-bottom: 0.5rem;">Market Activity</h4>
                                <div style="position: relative; height: 300px; width: 100%;">
                                    <canvas x-ref="marketChart"></canvas>
                                </div>
                            </div>
                            <div>
                                <h4 style="color: var(--text-secondary); margin-bottom: 0.5rem;">Card Claims</h4>
                                <div style="position: relative; height: 300px; width: 100%;">
                                    <canvas x-ref="claimChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif

                    <div style="background: rgba(0,0,0,0.2); padding: 1.5rem; border-radius: 12px; border: 1px solid var(--glass-border);">
                        <h3 style="margin-top: 0; margin-bottom: 1rem; color: #a855f7; display: flex; align-items: center; gap: 0.5rem;"><i class="ph-fill ph-arrows-left-right"></i> Transactions ({{ $auditDateStart }} &mdash; {{ $auditDateEnd }})</h3>
                        @if(count($transactions) > 0)
                            <div style="overflow-x: auto;">
                                <table style="width: 100%; border-collapse: collapse;">
                                    <thead>
                                        <tr style="border-bottom: 1px solid rgba(255,255,255,0.1); text-align: left;">
                                            <th style="padding: 0.5rem; color: var(--text-secondary);">Date</th>
                                            <th style="padding: 0.5rem; color: var(--text-secondary);">Type</th>
                                            <th style="padding: 0.5rem; color: var(--text-secondary);">From</th>
                                            <th style="padding: 0.5rem; color: var(--text-secondary);">To</th>
                                            <th style="padding: 0.5rem; color: var(--text-secondary);">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($transactions as $tx)
                                            <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                                <td style="padding: 0.5rem;">
                                                    @if(isset($tx->transactionID))
                                                        <a href="{{ route('cards.index') }}?transactionID={{ $tx->transactionID }}" target="_blank" style="color: #a855f7; text-decoration: none; display: flex; align-items: center; gap: 0.3rem;">
                                                            {{ $tx->dateCreated ? \Carbon\Carbon::parse($tx->dateCreated)->format('Y-m-d H:i') : 'N/A' }} <i class="ph ph-link"></i>
                                                        </a>
                                                    @else
                                                        {{ $tx->dateCreated ? \Carbon\Carbon::parse($tx->dateCreated)->format('Y-m-d H:i') : 'N/A' }}
                                                    @endif
                                                </td>
                                                <td style="padding: 0.5rem;">{{ $tx->status ?? 'Unknown' }}</td>
                                                <td style="padding: 0.5rem; font-family: monospace;">
                                                    @if($tx->fromID === 'bot')
                                                        <span style="color: #9ca3af;">bot</span>
                                                    @else
                                                        <a href="{{ route('profile.show') }}?id={{ $tx->fromID }}" style="color: {{ $tx->fromID === $targetUser->userID ? '#f87171' : '#60a5fa' }}; text-decoration: none;" target="_blank">
                                                            {{ $userMap[$tx->fromID] ?? $tx->fromID }}
                                                        </a>
                                                    @endif
                                                </td>
                                                <td style="padding: 0.5rem; font-family: monospace;">
                                                    @if($tx->toID === 'bot')
                                                        <span style="color: #9ca3af;">bot</span>
                                                    @else
                                                        <a href="{{ route('profile.show') }}?id={{ $tx->toID }}" style="color: {{ $tx->toID === $targetUser->userID ? '#4ade80' : '#60a5fa' }}; text-decoration: none;" target="_blank">
                                                            {{ $userMap[$tx->toID] ?? $tx->toID }}
                                                        </a>
                                                    @endif
                                                </td>
                                                <td style="padding: 0.5rem;">{{ number_format($tx->cost ?? 0) }} 🍅</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @if($transactions->hasPages())
                                <div style="margin-top: 1rem;">
                                    {{ $transactions->links('components.custom-pagination') }}
                                </div>
                            @endif
                        @else
                            <p style="color: var(--text-secondary);">No recent transactions found.</p>
                        @endif
                    </div>

                    <div style="background: rgba(0,0,0,0.2); padding: 1.5rem; border-radius: 12px; border: 1px solid var(--glass-border);">
                        <h3 style="margin-top: 0; margin-bottom: 1rem; color: #a855f7; display: flex; align-items: center; gap: 0.5rem;"><i class="ph-fill ph-gavel"></i> Auctions ({{ $auditDateStart }} &mdash; {{ $auditDateEnd }})</h3>
                        @if(count($auctions) > 0)
                            <div style="overflow-x: auto;">
                                <table style="width: 100%; border-collapse: collapse;">
                                    <thead>
                                        <tr style="border-bottom: 1px solid rgba(255,255,255,0.1); text-align: left;">
                                            <th style="padding: 0.5rem; color: var(--text-secondary);">Date</th>
                                            <th style="padding: 0.5rem; color: var(--text-secondary);">Card ID</th>
                                            <th style="padding: 0.5rem; color: var(--text-secondary);">Price</th>
                                            <th style="padding: 0.5rem; color: var(--text-secondary);">High Bid</th>
                                            <th style="padding: 0.5rem; color: var(--text-secondary);">Ended</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($auctions as $auc)
                                            <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                                <td style="padding: 0.5rem;">{{ $auc->time ? \Carbon\Carbon::parse($auc->time)->format('Y-m-d H:i') : 'N/A' }}</td>
                                                <td style="padding: 0.5rem;"><a href="{{ route('cards.index') }}?search={{ $auc->cardID }}" style="color: #60a5fa;" target="_blank">{{ $auc->cardID }}</a></td>
                                                <td style="padding: 0.5rem;">{{ number_format($auc->price ?? 0) }} 🍅</td>
                                                <td style="padding: 0.5rem; color: #4ade80;">{{ number_format($auc->highBid ?? 0) }} 🍅</td>
                                                <td style="padding: 0.5rem;">{{ $auc->ended ? 'Yes' : ($auc->cancelled ? 'Cancelled' : 'No') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @if($auctions->hasPages())
                                <div style="margin-top: 1rem;">
                                    {{ $auctions->links('components.custom-pagination') }}
                                </div>
                            @endif
                        @else
                            <p style="color: var(--text-secondary);">No recent auctions found.</p>
                        @endif
                    </div>

                    <div style="background: rgba(0,0,0,0.2); padding: 1.5rem; border-radius: 12px; border: 1px solid var(--glass-border);">
                        <h3 style="margin-top: 0; margin-bottom: 1rem; color: #a855f7; display: flex; align-items: center; gap: 0.5rem;"><i class="ph-fill ph-hand-coins"></i> Claims ({{ $auditDateStart }} &mdash; {{ $auditDateEnd }})</h3>
                        @if(count($claims) > 0)
                            <div style="overflow-x: auto;">
                                <table style="width: 100%; border-collapse: collapse;">
                                    <thead>
                                        <tr style="border-bottom: 1px solid rgba(255,255,255,0.1); text-align: left;">
                                            <th style="padding: 0.5rem; color: var(--text-secondary);">Date</th>
                                            <th style="padding: 0.5rem; color: var(--text-secondary);">Claim ID</th>
                                            <th style="padding: 0.5rem; color: var(--text-secondary);">Cost</th>
                                            <th style="padding: 0.5rem; color: var(--text-secondary);">Promo</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($claims as $claim)
                                            <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                                <td style="padding: 0.5rem;">{{ $claim->timeClaimed ? \Carbon\Carbon::parse($claim->timeClaimed)->format('Y-m-d H:i') : 'N/A' }}</td>
                                                <td style="padding: 0.5rem; font-family: monospace;">
                                                    <a href="{{ route('cards.index') }}?claimID={{ $claim->claimID }}" target="_blank" style="color: #60a5fa; text-decoration: none; display: flex; align-items: center; gap: 0.3rem;">
                                                        {{ $claim->claimID }} <i class="ph ph-link"></i>
                                                    </a>
                                                </td>
                                                <td style="padding: 0.5rem;">{{ number_format($claim->cost ?? 0) }} 🍅</td>
                                                <td style="padding: 0.5rem;">{{ $claim->promo ? 'Yes' : 'No' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @if($claims->hasPages())
                                <div style="margin-top: 1rem;">
                                    {{ $claims->links('components.custom-pagination') }}
                                </div>
                            @endif
                        @else
                            <p style="color: var(--text-secondary);">No recent claims found.</p>
                        @endif
                    </div>

                </div>
            @endif
        </div>
    @endif

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('auditCharts', (data) => ({
                ecoChart: null,
                marketChart: null,
                claimChart: null,
                init() {
                    if (typeof Chart === 'undefined') return;

                    const commonOptions = {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            x: { ticks: { color: '#9ca3af' }, grid: { color: 'rgba(255,255,255,0.05)' } },
                            y: { ticks: { color: '#9ca3af' }, grid: { color: 'rgba(255,255,255,0.05)' } }
                        },
                        plugins: {
                            legend: { labels: { color: '#e5e7eb' } }
                        },
                        elements: { line: { tension: 0.3 } }
                    };

                    this.ecoChart = new Chart(this.$refs.ecoChart, {
                        type: 'line',
                        data: {
                            labels: data.labels,
                            datasets: [
                                { label: 'Tomatoes In', data: data.economy.tomatoIn, borderColor: '#ef4444', backgroundColor: 'rgba(239,68,68,0.1)' },
                                { label: 'Tomatoes Out', data: data.economy.tomatoOut, borderColor: '#fca5a5', backgroundColor: 'rgba(252,165,165,0.1)' },
                                { label: 'Lemons In', data: data.economy.lemonIn, borderColor: '#eab308', backgroundColor: 'rgba(234,179,8,0.1)' },
                                { label: 'Lemons Out', data: data.economy.lemonOut, borderColor: '#fef08a', backgroundColor: 'rgba(254,240,138,0.1)' }
                            ]
                        },
                        options: commonOptions
                    });

                    this.marketChart = new Chart(this.$refs.marketChart, {
                        type: 'line',
                        data: {
                            labels: data.labels,
                            datasets: [
                                { label: 'Auctions Sold', data: data.market.aucSell, borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,0.1)' },
                                { label: 'Auction Bids', data: data.market.aucBid, borderColor: '#34d399', backgroundColor: 'rgba(52,211,153,0.1)' },
                                { label: 'Auctions Won', data: data.market.aucWin, borderColor: '#6ee7b7', backgroundColor: 'rgba(110,231,183,0.1)' },
                                { label: 'User Trades (Sell)', data: data.market.userSell, borderColor: '#3b82f6', backgroundColor: 'rgba(59,130,246,0.1)' },
                                { label: 'User Trades (Buy)', data: data.market.userBuy, borderColor: '#93c5fd', backgroundColor: 'rgba(147,197,253,0.1)' }
                            ]
                        },
                        options: commonOptions
                    });

                    this.claimChart = new Chart(this.$refs.claimChart, {
                        type: 'line',
                        data: {
                            labels: data.labels,
                            datasets: [
                                { label: 'Regular Claims', data: data.claims.claims, borderColor: '#8b5cf6', backgroundColor: 'rgba(139,92,246,0.1)' },
                                { label: 'Promo Claims', data: data.claims.promoclaims, borderColor: '#d946ef', backgroundColor: 'rgba(217,70,239,0.1)' }
                            ]
                        },
                        options: commonOptions
                    });
                }
            }));
        });

    </script>
</div>
