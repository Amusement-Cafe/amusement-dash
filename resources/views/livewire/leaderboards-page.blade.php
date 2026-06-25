<?php

use Livewire\Volt\Component;
use App\Models\User;
use App\Models\UserCard;
use App\Models\Leaderboard;
use Carbon\Carbon;

use Livewire\Attributes\Title;

new #[Title('Leaderboards')] class extends Component {
    public $activeTab = 'Cards';
    public $leaderboardData = [];
    public $currentUserRank = null;
    public $currentUserValue = null;

    public function mount()
    {
        $this->loadLeaderboard();
    }

    public function setTab($tab)
    {
        $this->activeTab = $tab;
        $this->loadLeaderboard();
    }

    public function loadLeaderboard()
    {
        $cacheType = strtolower($this->activeTab);
        $cached = Leaderboard::where('type', $cacheType)->first();

        if (!$cached || $cached->expires_at < now()) {
            $data = $this->calculateLeaderboard($cacheType);
            
            if ($cached) {
                $cached->update([
                    'data' => $data,
                    'expires_at' => now()->addHours(12)
                ]);
            } else {
                Leaderboard::create([
                    'type' => $cacheType,
                    'data' => $data,
                    'expires_at' => now()->addHours(12)
                ]);
            }
            $this->leaderboardData = $data;
        } else {
            $this->leaderboardData = $cached->data;
        }

        $this->findCurrentUser();
    }

    private function calculateLeaderboard($type)
    {
        $results = [];

        if ($type === 'cards') {
            $results = UserCard::raw(function($collection) {
                return $collection->aggregate([
                    ['$group' => ['_id' => '$userID', 'count' => ['$sum' => 1]]],
                    ['$sort' => ['count' => -1]],
                    ['$limit' => 100]
                ]);
            });
        } elseif ($type === 'clout') {
            $results = User::raw(function($collection) {
                return $collection->aggregate([
                    ['$unwind' => '$cloutedCols'],
                    ['$group' => ['_id' => '$userID', 'count' => ['$sum' => '$cloutedCols.amount']]],
                    ['$sort' => ['count' => -1]],
                    ['$limit' => 100]
                ]);
            });
        } elseif ($type === 'completed') {
            $results = User::raw(function($collection) {
                return $collection->aggregate([
                    ['$unwind' => '$completedCols'],
                    ['$group' => ['_id' => '$userID', 'count' => ['$sum' => '$completedCols.amount']]],
                    ['$sort' => ['count' => -1]],
                    ['$limit' => 100]
                ]);
            });
        } elseif (in_array($type, ['lemons', 'tomatoes', 'vials', 'level'])) {
            $field = $type === 'level' ? 'xp' : $type;
            
            $users = User::orderBy($field, 'desc')->limit(100)->get(['userID', 'username', $field, 'preferences']);
            
            foreach ($users as $user) {
                $rawColor = $user->preferences['profile']['color'] ?? null;
                $color = null;
                if (!empty($rawColor) && $rawColor !== '00000' && $rawColor !== '0') {
                    $color = is_numeric($rawColor) ? sprintf('#%06x', (int)$rawColor) : $rawColor;
                    if ($color === '#000000') $color = null;
                }
                
                $results[] = [
                    'id' => $user->userID,
                    'username' => $user->username,
                    'count' => $user->$field ?? 0,
                    'color' => $color,
                    'title' => $user->preferences['profile']['title'] ?? null,
                ];
            }
            return $results;
        }

        // For aggregations, we only get _id (userID) and count. We need to fetch usernames.
        if (in_array($type, ['cards', 'clout', 'completed'])) {
            $data = [];
            $userIds = [];
            foreach ($results as $row) {
                $uId = is_object($row) ? $row->_id : $row['_id'];
                $count = is_object($row) ? $row->count : $row['count'];
                $userIds[] = $uId;
                $data[] = [
                    'id' => $uId,
                    'count' => $count
                ];
            }
            
            $users = User::whereIn('userID', $userIds)->get(['userID', 'username', 'preferences'])->keyBy('userID');
            
            foreach ($data as &$row) {
                $row['username'] = isset($users[$row['id']]) ? $users[$row['id']]->username : 'Unknown User';
                $rawColor = isset($users[$row['id']]) ? ($users[$row['id']]->preferences['profile']['color'] ?? null) : null;
                $color = null;
                if (!empty($rawColor) && $rawColor !== '00000' && $rawColor !== '0') {
                    $color = is_numeric($rawColor) ? sprintf('#%06x', (int)$rawColor) : $rawColor;
                    if ($color === '#000000') $color = null;
                }
                $row['color'] = $color;
                $row['title'] = isset($users[$row['id']]) ? ($users[$row['id']]->preferences['profile']['title'] ?? null) : null;
            }
            return $data;
        }

        return $results;
    }

    private function findCurrentUser()
    {
        $this->currentUserRank = null;
        $this->currentUserValue = null;

        if (!auth()->check()) return;

        $myId = auth()->user()->userID;
        
        foreach ($this->leaderboardData as $index => $row) {
            $rowId = $row['id'] ?? $row['_id'] ?? null;
            if ($rowId == $myId) {
                $this->currentUserRank = $index + 1;
                $this->currentUserValue = $row['count'];
                return;
            }
        }
        
        // If not in top 100, try to get value
        if ($this->activeTab === 'Cards') {
            $this->currentUserValue = UserCard::where('userID', $myId)->count();
        } elseif ($this->activeTab === 'Clout') {
            $user = User::where('userID', $myId)->first(['cloutedCols']);
            $count = 0;
            if ($user && is_array($user->cloutedCols)) {
                foreach ($user->cloutedCols as $col) {
                    $count += $col['amount'] ?? 0;
                }
            }
            $this->currentUserValue = $count;
        } elseif ($this->activeTab === 'Completed') {
            $user = User::where('userID', $myId)->first(['completedCols']);
            $count = 0;
            if ($user && is_array($user->completedCols)) {
                foreach ($user->completedCols as $col) {
                    $count += $col['amount'] ?? 0;
                }
            }
            $this->currentUserValue = $count;
        } else {
            $field = $this->activeTab === 'Level' ? 'xp' : strtolower($this->activeTab);
            $this->currentUserValue = auth()->user()->$field ?? 0;
        }
    }
};
?>

<div class="container animate-fade-in" style="max-width: 900px; margin: 2rem auto; padding: 0 1rem;">
    
    <div style="text-align: center; margin-bottom: 2rem;">
        <h1 style="font-size: 2.5rem; font-weight: 800; background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin-bottom: 0.5rem; filter: drop-shadow(0 2px 4px rgba(245, 158, 11, 0.3));">
            Global Leaderboards
        </h1>
        <p style="color: var(--text-secondary); font-size: 1.1rem;">See who's at the top of Amusement Club. Refreshed every 12 hours.</p>
    </div>

    <!-- Tabs -->
    <div style="display: flex; flex-wrap: wrap; gap: 0.5rem; justify-content: center; margin-bottom: 2rem; background: rgba(0,0,0,0.2); padding: 0.5rem; border-radius: 16px; border: 1px solid var(--glass-border); backdrop-filter: blur(10px);">
        @foreach(['Cards', 'Clout', 'Completed', 'Lemons', 'Level', 'Tomatoes', 'Vials'] as $tab)
            <button wire:click="setTab('{{ $tab }}')" 
                    style="background: {{ $activeTab === $tab ? 'rgba(255,255,255,0.1)' : 'transparent' }}; 
                           border: 1px solid {{ $activeTab === $tab ? 'rgba(255,255,255,0.2)' : 'transparent' }}; 
                           color: {{ $activeTab === $tab ? 'white' : 'var(--text-secondary)' }}; 
                           padding: 0.5rem 1.2rem; 
                           border-radius: 12px; 
                           font-weight: 600; 
                           cursor: pointer; 
                           transition: all 0.2s ease;">
                {{ $tab }}
            </button>
        @endforeach
    </div>

    @auth
    <div style="margin-bottom: 2rem; background: linear-gradient(135deg, rgba(20, 20, 30, 0.8) 0%, rgba(30, 30, 45, 0.8) 100%); border: 1px solid var(--accent-solid); border-radius: 16px; padding: 1.5rem; display: flex; align-items: center; justify-content: space-between; backdrop-filter: blur(10px); box-shadow: 0 10px 30px rgba(0,0,0,0.3);">
        <div style="display: flex; align-items: center; gap: 1rem;">
            @php
                $myUser = auth()->user();
                $avatarIndex = is_numeric($myUser->userID) ? (substr($myUser->userID, -1) % 6) : 0;
                $defaultAvatar = "https://cdn.discordapp.com/embed/avatars/{$avatarIndex}.png";
            @endphp
            <img src="{{ \Illuminate\Support\Facades\Cache::get('discord_avatar_' . $myUser->userID, $defaultAvatar) }}" style="width: 50px; height: 50px; border-radius: 50%; border: 2px solid var(--accent-solid); object-fit: cover;">
            <div>
                <h3 style="margin: 0; color: white; font-size: 1.2rem;">{{ $myUser->username }}</h3>
                <p style="margin: 0; color: var(--text-secondary); font-size: 0.9rem;">Your position</p>
            </div>
        </div>
        <div style="text-align: right;">
            <div style="font-size: 1.5rem; font-weight: bold; color: var(--accent-solid);">
                {{ $currentUserRank ? "#" . $currentUserRank : ">100" }}
            </div>
            <div style="color: var(--text-secondary); font-size: 0.9rem;">
                {{ number_format($currentUserValue) }} 
                @if($activeTab === 'Lemons') 🍋
                @elseif($activeTab === 'Tomatoes') 🍅
                @elseif($activeTab === 'Vials') 🧪
                @elseif($activeTab === 'Level') XP
                @endif
            </div>
        </div>
    </div>
    @endauth

    <!-- Leaderboard Table -->
    <div style="background: var(--glass-bg); border: 1px solid var(--glass-border); border-radius: 16px; overflow: hidden; backdrop-filter: blur(10px);">
        <div style="display: grid; grid-template-columns: 80px 1fr 150px; padding: 1rem 1.5rem; border-bottom: 1px solid var(--glass-border); background: rgba(0,0,0,0.3); font-weight: bold; color: var(--text-secondary);">
            <div>Rank</div>
            <div>Player</div>
            <div style="text-align: right;">Score</div>
        </div>
        
        <div wire:loading.class="opacity-50" style="transition: opacity 0.3s; position: relative;">
            <div wire:loading style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 10;">
                <i class="ph-bold ph-spinner" style="font-size: 2rem; color: var(--accent-solid); animation: spin 1s linear infinite;"></i>
            </div>
            
            @forelse($leaderboardData as $index => $row)
                @php
                    $isTop3 = $index < 3;
                    $bgColor = 'transparent';
                    if ($index === 0) $bgColor = 'linear-gradient(90deg, rgba(251, 191, 36, 0.15) 0%, transparent 100%)'; // Gold
                    elseif ($index === 1) $bgColor = 'linear-gradient(90deg, rgba(156, 163, 175, 0.15) 0%, transparent 100%)'; // Silver
                    elseif ($index === 2) $bgColor = 'linear-gradient(90deg, rgba(180, 83, 9, 0.15) 0%, transparent 100%)'; // Bronze
                    
                    $rankColor = 'var(--text-secondary)';
                    if ($index === 0) $rankColor = '#fbbf24';
                    elseif ($index === 1) $rankColor = '#9ca3af';
                    elseif ($index === 2) $rankColor = '#b45309';

                    $rowId = $row['id'] ?? $row['_id'] ?? null;
                    $isCurrentUser = auth()->check() && auth()->user()->userID === $rowId;
                    if ($isCurrentUser) {
                        $bgColor = 'linear-gradient(90deg, rgba(168, 85, 247, 0.2) 0%, transparent 100%)';
                    }
                @endphp
                <a href="{{ route('profile.show') }}?id={{ $row['id'] ?? $row['_id'] ?? '' }}" style="display: grid; grid-template-columns: 80px 1fr 150px; padding: 1rem 1.5rem; border-bottom: 1px solid rgba(255,255,255,0.05); align-items: center; background: {{ $bgColor }}; transition: background 0.2s; text-decoration: none; color: inherit;" onmouseover="this.style.background='rgba(255,255,255,0.05)'" onmouseout="this.style.background='{{ $bgColor }}'">
                    
                    <div style="font-weight: 800; font-size: 1.2rem; color: {{ $rankColor }}; display: flex; align-items: center; gap: 0.5rem;">
                        #{{ $index + 1 }}
                        @if($index === 0) <i class="ph-fill ph-crown" style="font-size: 1.2rem; color: #fbbf24; filter: drop-shadow(0 0 5px rgba(251, 191, 36, 0.5));"></i>
                        @elseif($index === 1) <i class="ph-fill ph-medal" style="font-size: 1.2rem; color: #9ca3af;"></i>
                        @elseif($index === 2) <i class="ph-fill ph-medal" style="font-size: 1.2rem; color: #b45309;"></i>
                        @endif
                    </div>
                    
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        @php
                            $uId = $row['id'] ?? $row['_id'] ?? null;
                            $avatarIndex = is_numeric($uId) ? (substr($uId, -1) % 6) : 0;
                            $defaultAvatar = "https://cdn.discordapp.com/embed/avatars/{$avatarIndex}.png";
                        @endphp
                        <img src="{{ \Illuminate\Support\Facades\Cache::get('discord_avatar_' . $uId, $defaultAvatar) }}" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid {{ $isTop3 ? $rankColor : 'transparent' }};">
                        
                        <div style="display: flex; flex-direction: column;">
                            <span style="font-weight: 600; font-size: 1.1rem; color: {{ !empty($row['color']) ? $row['color'] : ($isCurrentUser ? 'var(--accent-solid)' : 'white') }};">
                                {{ $row['username'] ?? 'Unknown User' }}
                                @if($isCurrentUser) <span style="font-size: 0.8rem; background: var(--accent-solid); color: white; padding: 2px 6px; border-radius: 12px; margin-left: 0.5rem; text-decoration: none;">You</span> @endif
                            </span>
                            @if(!empty($row['title']))
                                <div style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 0.1rem; font-weight: normal;">{{ $row['title'] }}</div>
                            @endif
                        </div>
                    </div>
                    
                    <div style="text-align: right; font-weight: bold; font-size: 1.1rem; color: white;">
                        @if($activeTab === 'Level')
                            @php
                                $lvl = floor(sqrt($row['count'] * 2));
                            @endphp
                            Lvl {{ $lvl }} <span style="font-size: 0.8rem; color: var(--text-secondary); font-weight: normal;">({{ number_format($row['count']) }} XP)</span>
                        @else
                            {{ number_format($row['count']) }}
                            @if($activeTab === 'Lemons') 🍋
                            @elseif($activeTab === 'Tomatoes') 🍅
                            @elseif($activeTab === 'Vials') 🧪
                            @endif
                        @endif
                    </div>

                </a>
            @empty
                <div style="padding: 2rem; text-align: center; color: var(--text-secondary);">
                    No data available.
                </div>
            @endforelse
        </div>
    </div>
</div>

<style>
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
</style>
