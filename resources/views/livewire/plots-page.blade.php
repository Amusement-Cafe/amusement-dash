<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Plot;
use Illuminate\Support\Facades\Auth;

new #[Layout('layouts.app')] class extends Component {
    public function collectAll($guildID) {
        $plots = Plot::where('userID', Auth::user()->userID)->where('guildID', $guildID)->get();
        $totalCollected = 0;
        
        foreach($plots as $plot) {
            if (isset($plot->building) && ($plot->building['storedLemons'] ?? 0) > 0) {
                $building = $plot->building;
                $lemonsCollected = $building['storedLemons'];
                $totalCollected += $lemonsCollected;
                
                $building['storedLemons'] = 0;
                $plot->building = $building;
                $plot->save();
            }
        }
        
        if ($totalCollected > 0) {
            $user = Auth::user();
            $user->lemons += $totalCollected;
            $user->save();
            $this->dispatch('notify', message: 'Successfully collected ' . number_format($totalCollected) . ' lemons!');
        } else {
            $this->dispatch('notify', message: 'No lemons to collect here.');
        }
    }

    public function with(): array {
        $user = Auth::user();
        $user->refresh();
        $plots = Plot::where('userID', $user->userID)->get();
        
        $guilds = [];
        foreach ($plots as $plot) {
            $gID = $plot->guildID ?? 'Unknown';
            if (!isset($guilds[$gID])) {
                $guildInfo = \Illuminate\Support\Facades\Cache::remember('discord_guild_' . $gID, 86400, function () use ($gID) {
                    try {
                        $botToken = config('services.discord.bot_token');
                        if (!$botToken) return null;
                        
                        $response = \Illuminate\Support\Facades\Http::withToken($botToken, 'Bot')
                            ->timeout(3)
                            ->get("https://discord.com/api/v10/guilds/{$gID}");
                        
                        if ($response->successful()) {
                            $data = $response->json();
                            return [
                                'name' => $data['name'] ?? null,
                                'icon' => $data['icon'] ?? null
                            ];
                        }
                        
                        // Fallback for public guilds the bot isn't in
                        $preview = \Illuminate\Support\Facades\Http::withToken($botToken, 'Bot')
                            ->timeout(3)
                            ->get("https://discord.com/api/v10/guilds/{$gID}/preview");
                            
                        if ($preview->successful()) {
                            $data = $preview->json();
                            return [
                                'name' => $data['name'] ?? null,
                                'icon' => $data['icon'] ?? null
                            ];
                        }
                    } catch (\Exception $e) {}
                    return null;
                });

                $guilds[$gID] = [
                    'guildID' => $gID,
                    'plots' => [],
                    'totalLemons' => 0,
                    'guildInfo' => $guildInfo
                ];
            }
            $guilds[$gID]['plots'][] = $plot;
            
            if (isset($plot->building)) {
                $guilds[$gID]['totalLemons'] += ($plot->building['storedLemons'] ?? 0);
            }
        }

        // Sort so guilds with most lemons are at the top
        usort($guilds, function($a, $b) {
            return $b['totalLemons'] <=> $a['totalLemons'];
        });

        return [
            'guildGroups' => $guilds
        ];
    }
};
?>

<div>
    <x-slot:title>Your Plots</x-slot:title>
    
    <div style="margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <h1 style="font-size: 2.5rem; margin: 0; display: flex; align-items: center; gap: 1rem;">
            <i class="ph-fill ph-house-line" style="color: #eab308;"></i> Your Plots
        </h1>
        
        <div class="glass-panel" style="padding: 1rem 1.5rem; display: flex; align-items: center; gap: 1rem; border: 1px solid rgba(234, 179, 8, 0.3); background: rgba(234, 179, 8, 0.05); min-width: 200px;">
            <div style="width: 48px; height: 48px; border-radius: 50%; background: rgba(234, 179, 8, 0.15); display: flex; align-items: center; justify-content: center; font-size: 1.8rem; box-shadow: 0 0 15px rgba(234, 179, 8, 0.2);">
                🍋
            </div>
            <div>
                <div style="font-size: 0.85rem; color: var(--text-secondary); font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px;">Your Balance</div>
                <div style="font-size: 1.6rem; font-weight: 800; color: #eab308; line-height: 1;">
                    {{ number_format(auth()->user()->lemons ?? 0) }}
                </div>
            </div>
        </div>
    </div>

    @if(empty($guildGroups))
        <div class="glass-panel" style="padding: 4rem; text-align: center; border: 2px dashed var(--glass-border);">
            <i class="ph-light ph-plant" style="font-size: 5rem; margin-bottom: 1rem; color: var(--text-secondary);"></i>
            <h2 style="margin: 0 0 1rem 0;">No Plots Yet</h2>
            <p style="color: var(--text-secondary); font-size: 1.1rem; max-width: 500px; margin: 0 auto;">
                You haven't acquired any plots. Participate in server activities, complete quests, or visit the store to get started!
            </p>
        </div>
    @else
        <div style="display: flex; flex-direction: column; gap: 2rem;">
            @foreach($guildGroups as $group)
                @php 
                    $guildID = $group['guildID']; 
                    $guildName = $group['guildInfo']['name'] ?? 'Server ' . $guildID;
                    $guildIcon = $group['guildInfo']['icon'] ?? null;
                    $iconUrl = $guildIcon ? "https://cdn.discordapp.com/icons/{$guildID}/{$guildIcon}.png?size=64" : null;
                @endphp
                <div class="glass-panel" style="padding: 1.5rem; border-left: 4px solid #3b82f6;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                        <div>
                            <h2 style="margin: 0 0 0.2rem 0; font-size: 1.5rem; display: flex; align-items: center; gap: 0.8rem;">
                                @if($iconUrl)
                                    <img src="{{ $iconUrl }}" alt="{{ $guildName }}" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover;">
                                @else
                                    <i class="ph-fill ph-discord-logo" style="color: #5865F2; font-size: 2rem;"></i>
                                @endif
                                {{ $guildName }}
                            </h2>
                            <p style="margin: 0; color: var(--text-secondary); font-size: 0.95rem;">
                                {{ count($group['plots']) }} Plot(s) Owned
                            </p>
                        </div>
                        
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <div style="background: rgba(234, 179, 8, 0.1); border: 1px solid rgba(234, 179, 8, 0.3); padding: 0.6rem 1.2rem; border-radius: 8px; font-weight: bold; color: #eab308; display: flex; align-items: center; gap: 0.5rem; font-size: 1.1rem;">
                                {{ number_format($group['totalLemons']) }} 🍋
                            </div>
                            
                            @if($group['totalLemons'] > 0)
                                <button wire:click="collectAll('{{ $guildID }}')" class="btn btn-primary" style="padding: 0.6rem 1.2rem; font-weight: bold; display: flex; align-items: center; gap: 0.5rem; border: none; cursor: pointer; box-shadow: 0 4px 15px rgba(234, 179, 8, 0.3);">
                                    <i class="ph-bold ph-hand-coins"></i> Collect All
                                </button>
                            @else
                                <button disabled class="btn" style="padding: 0.6rem 1.2rem; font-weight: bold; display: flex; align-items: center; gap: 0.5rem; border: 1px solid var(--glass-border); background: rgba(255,255,255,0.05); color: var(--text-secondary); cursor: not-allowed;">
                                    <i class="ph-bold ph-check"></i> Collected
                                </button>
                            @endif
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.5rem;">
                        @foreach($group['plots'] as $plot)
                            @php
                                $hasBuilding = isset($plot->building) && !empty($plot->building['buildingID']);
                            @endphp
                            
                            @if($hasBuilding)
                                @php
                                    $lemons = $plot->building['storedLemons'] ?? 0;
                                    $bID = strtolower($plot->building['buildingID'] ?? '');
                                    $level = $plot->building['level'] ?? 1;
                                    $bIcon = match(true) {
                                        str_contains($bID, 'farm') || str_contains($bID, 'lemon') => 'ph-plant',
                                        str_contains($bID, 'mine') => 'ph-pickaxe',
                                        str_contains($bID, 'bank') || str_contains($bID, 'vault') => 'ph-bank',
                                        str_contains($bID, 'factory') => 'ph-factory',
                                        str_contains($bID, 'shop') || str_contains($bID, 'store') => 'ph-storefront',
                                        default => 'ph-house'
                                    };
                                @endphp
                                <div style="background: rgba(0,0,0,0.25); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 1.5rem; position: relative; overflow: hidden; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 20px rgba(0,0,0,0.4)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                                    <div style="position: absolute; top: 0; left: 0; width: 100%; height: 4px; background: linear-gradient(90deg, #10b981, #3b82f6);"></div>
                                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
                                        <div style="width: 50px; height: 50px; border-radius: 12px; background: rgba(16,185,129,0.1); display: flex; align-items: center; justify-content: center; font-size: 2rem;">
                                            <i class="ph-fill {{ $bIcon }}" style="color: #10b981;"></i>
                                        </div>
                                        <div style="background: rgba(0,0,0,0.4); padding: 4px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: bold; color: var(--text-secondary);">
                                            LVL {{ $level }}
                                        </div>
                                    </div>
                                    
                                    <h4 style="margin: 0 0 0.5rem 0; font-size: 1.2rem; text-transform: capitalize;">{{ str_replace('_', ' ', $plot->building['buildingID']) }}</h4>
                                    
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid rgba(255,255,255,0.05);">
                                        <div style="font-size: 0.9rem; color: var(--text-secondary);">
                                            Ready to collect
                                        </div>
                                        <div style="font-weight: bold; font-size: 1.1rem; color: #eab308; display: flex; align-items: center; gap: 0.3rem;">
                                            {{ $lemons }} 🍋
                                        </div>
                                    </div>
                                </div>
                            @else
                                <div style="border: 2px dashed rgba(255,255,255,0.15); border-radius: 12px; padding: 1.5rem; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 160px; background: rgba(0,0,0,0.1); text-align: center; opacity: 0.7; transition: opacity 0.2s;" onmouseover="this.style.opacity='1';" onmouseout="this.style.opacity='0.7';">
                                    <i class="ph-light ph-ruler" style="font-size: 2.5rem; color: var(--text-secondary); margin-bottom: 0.5rem;"></i>
                                    <h4 style="margin: 0 0 0.2rem 0; font-size: 1.1rem; color: var(--text-secondary);">Empty Plot</h4>
                                    <p style="margin: 0; font-size: 0.85rem; color: rgba(255,255,255,0.4);">Ready for construction</p>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
