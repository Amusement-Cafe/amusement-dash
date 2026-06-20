<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Hero;
use Illuminate\Support\Facades\Auth;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    public string $search = '';
    public ?string $selectedHeroId = null;

    public function updatedSearch() {
        $this->resetPage();
    }

    public function with(): array {
        $user = Auth::user();
        $myHero = null;
        
        $query = Hero::where('accepted', true);
        
        if (trim($this->search)) {
            $query->where('name', 'like', '%' . $this->search . '%');
        } else if ($user && $user->hero) {
            if ($this->getPage() == 1) {
                $myHero = Hero::where('heroID', $user->hero)->first();
            }
            $query->where('heroID', '!=', $user->hero);
        }
        
        $perPage = ($myHero) ? 23 : 24;
        $heroes = $query->orderBy('followers', 'desc')->paginate($perPage);

        return [
            'myHero' => $myHero,
            'heroes' => $heroes
        ];
    }

    public function setAsMyHero($id) {
        if (!$id) return;
        
        $user = Auth::user();
        if ($user) {
            $user->hero = $id;
            $user->heroChanged = now();
            $user->save();
            
            $this->dispatch('notify', message: 'Hero assigned successfully!');
        }
    }
};
?>
<div x-data="{ showModal: false, selectedHero: null, picIndex: 0 }">
    <div style="margin-bottom: 2rem; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-end; gap: 1.5rem;">
        <div>
            <h1 style="font-size: 2.5rem; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 1rem;">
                <i class="ph-fill ph-mask-happy" style="color: #ec4899;"></i> Heroes
            </h1>
            <p style="color: var(--text-secondary); margin: 0; font-size: 1.1rem;">Discover and align yourself with powerful heroes.</p>
        </div>
        <div style="flex: 1; max-width: 400px; position: relative;">
            <i class="ph-bold ph-magnifying-glass" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-secondary); font-size: 1.2rem;"></i>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search heroes by name..." style="width: 100%; background: var(--glass-bg); border: 1px solid var(--glass-border); padding: 0.8rem 1rem 0.8rem 2.8rem; border-radius: 8px; color: white; font-family: inherit; font-size: 1rem;">
        </div>
    </div>

    @if(count($heroes) > 0 || $myHero)
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1.5rem;">
            @if($myHero)
                @php
                    $heroLevel = floor(sqrt(($myHero->xp ?? 0) * 2));
                    $myHeroData = [
                        'heroID' => $myHero->heroID,
                        'name' => $myHero->name ?? 'Unknown',
                        'pictures' => $myHero->pictures ?? [],
                        'xp' => $myHero->xp ?? 0,
                        'followers' => $myHero->followers ?? 0,
                        'level' => $heroLevel,
                        'isMyHero' => true
                    ];
                @endphp
                <div wire:key="my-hero-{{ $myHero->heroID }}" x-data="{ pics: {{ json_encode($myHero->pictures ?? []) }}, pIdx: 0 }" @click="selectedHero = {{ json_encode($myHeroData) }}; picIndex = 0; showModal = true;" class="glass-panel" style="cursor: pointer; padding: 1.5rem; text-align: center; transition: transform 0.2s, box-shadow 0.2s; position: relative; overflow: hidden; border: 2px solid #ec4899; box-shadow: 0 0 15px rgba(236,72,153,0.3);" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 10px 25px rgba(236, 72, 153, 0.4)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 0 15px rgba(236,72,153,0.3)';">
                    <template x-if="pics.length > 0">
                        <div style="position: absolute; top: 0; left: 0; right: 0; height: 80px; background-size: cover; background-position: center; filter: blur(6px); opacity: 0.6; z-index: 1;" :style="'background-image: url(' + pics[pIdx] + ')'"></div>
                    </template>
                    <template x-if="pics.length === 0">
                        <div style="position: absolute; top: 0; left: 0; right: 0; height: 80px; background: linear-gradient(135deg, rgba(139,92,246,0.3), rgba(236,72,153,0.3)); z-index: 1;"></div>
                    </template>
                    <div style="position: relative; z-index: 2;">
                        <template x-if="pics.length > 0">
                            <img :src="pics[pIdx]" x-on:error="if(pIdx < pics.length - 1) pIdx++; else $el.style.display='none'" style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 3px solid #ec4899; box-shadow: 0 5px 15px rgba(0,0,0,0.5);">
                        </template>
                        <template x-if="pics.length === 0">
                            <div style="width: 100px; height: 100px; border-radius: 50%; background: var(--glass-border); display: inline-flex; align-items: center; justify-content: center; font-size: 3rem; margin: 0 auto; box-shadow: 0 5px 15px rgba(0,0,0,0.5); border: 3px solid #ec4899;">🦸</div>
                        </template>
                        <h3 style="margin: 1rem 0 0.5rem 0; font-size: 1.2rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: #ec4899;">{{ $myHero->name ?? 'Unknown' }} (Active)</h3>
                        <div style="display: inline-block; background: var(--accent-solid); color: white; padding: 2px 10px; border-radius: 12px; font-weight: bold; font-size: 0.8rem; margin-bottom: 0.5rem;">
                            LVL {{ $heroLevel }}
                        </div>
                        <div style="color: var(--text-secondary); font-size: 0.9rem; margin-top: 0.5rem; display: flex; align-items: center; justify-content: center; gap: 0.3rem;">
                            <i class="ph-fill ph-users"></i> {{ number_format($myHero->followers ?? 0) }} followers
                        </div>
                    </div>
                </div>
            @endif
            @foreach($heroes as $hero)
                @php
                    $heroLevel = floor(sqrt(($hero->xp ?? 0) * 2));
                    $heroData = [
                        'heroID' => $hero->heroID,
                        'name' => $hero->name ?? 'Unknown',
                        'pictures' => $hero->pictures ?? [],
                        'xp' => $hero->xp ?? 0,
                        'followers' => $hero->followers ?? 0,
                        'level' => $heroLevel,
                        'isMyHero' => auth()->check() && auth()->user()->hero === $hero->heroID
                    ];
                @endphp
                <div wire:key="hero-{{ $hero->heroID }}" x-data="{ pics: {{ json_encode($hero->pictures ?? []) }}, pIdx: 0 }" @click="selectedHero = {{ json_encode($heroData) }}; picIndex = 0; showModal = true;" class="glass-panel" style="cursor: pointer; padding: 1.5rem; text-align: center; transition: transform 0.2s, box-shadow 0.2s; position: relative; overflow: hidden;" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 10px 25px rgba(236, 72, 153, 0.2)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                    <template x-if="pics.length > 0">
                        <div style="position: absolute; top: 0; left: 0; right: 0; height: 80px; background-size: cover; background-position: center; filter: blur(6px); opacity: 0.4; z-index: 1;" :style="'background-image: url(' + pics[pIdx] + ')'"></div>
                    </template>
                    <template x-if="pics.length === 0">
                        <div style="position: absolute; top: 0; left: 0; right: 0; height: 80px; background: linear-gradient(135deg, rgba(139,92,246,0.3), rgba(236,72,153,0.3)); z-index: 1;"></div>
                    </template>
                    <div style="position: relative; z-index: 2;">
                        <template x-if="pics.length > 0">
                            <img :src="pics[pIdx]" x-on:error="if(pIdx < pics.length - 1) pIdx++; else $el.style.display='none'" style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 3px solid var(--glass-bg); box-shadow: 0 5px 15px rgba(0,0,0,0.5);">
                        </template>
                        <template x-if="pics.length === 0">
                            <div style="width: 100px; height: 100px; border-radius: 50%; background: var(--glass-border); display: inline-flex; align-items: center; justify-content: center; font-size: 3rem; margin: 0 auto; box-shadow: 0 5px 15px rgba(0,0,0,0.5); border: 3px solid var(--glass-bg);">🦸</div>
                        </template>
                        <h3 style="margin: 1rem 0 0.5rem 0; font-size: 1.2rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $hero->name ?? 'Unknown' }}</h3>
                        <div style="display: inline-block; background: var(--accent-solid); color: white; padding: 2px 10px; border-radius: 12px; font-weight: bold; font-size: 0.8rem; margin-bottom: 0.5rem;">
                            LVL {{ $heroLevel }}
                        </div>
                        <div style="color: var(--text-secondary); font-size: 0.9rem; margin-top: 0.5rem; display: flex; align-items: center; justify-content: center; gap: 0.3rem;">
                            <i class="ph-fill ph-users"></i> {{ number_format($hero->followers ?? 0) }} followers
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="glass-panel" style="padding: 1rem; display: flex; justify-content: center; margin-top: 2rem;">
            {{ $heroes->links('components.custom-pagination') }}
        </div>
    @else
        <div class="glass-panel" style="padding: 4rem; text-align: center;">
            <i class="ph-light ph-mask-sad" style="font-size: 4rem; color: var(--text-secondary); margin-bottom: 1rem;"></i>
            <h2 style="margin: 0 0 0.5rem 0;">No heroes found</h2>
            <p style="color: var(--text-secondary); margin: 0;">Try adjusting your search terms.</p>
        </div>
    @endif

    <style>
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
    </style>

    <!-- Alpine Hero Modal -->
    <template x-teleport="body">
        <div x-show="showModal" style="display: none;" x-transition.opacity.duration.200ms>
            <div style="position: fixed; inset: 0; background: rgba(0,0,0,0.85); backdrop-filter: blur(8px); z-index: 100000; display: flex; align-items: center; justify-content: center; padding: 1rem;" @click.self="showModal = false">
                
                <div class="glass-panel no-scrollbar" style="position: relative; width: 100%; max-width: 600px; padding: 0; overflow: hidden; border: 1px solid rgba(236, 72, 153, 0.4); box-shadow: 0 25px 50px -12px rgba(236, 72, 153, 0.3); border-radius: 12px; display: flex; flex-direction: column; max-height: 90vh;">
                    
                    <button @click="showModal = false" style="position: absolute; top: 1rem; right: 1rem; background: rgba(0,0,0,0.6); border: 1px solid rgba(255,255,255,0.2); color: white; width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: background 0.2s, transform 0.2s; z-index: 10;" onmouseover="this.style.background='rgba(0,0,0,0.9)'; this.style.transform='scale(1.1)';" onmouseout="this.style.background='rgba(0,0,0,0.6)'; this.style.transform='scale(1)'">
                        <i class="ph-bold ph-x"></i>
                    </button>

                    <!-- Full Image Area -->
                    <div style="flex-shrink: 0; background: #000; display: flex; align-items: center; justify-content: center; max-height: 400px; overflow: hidden; position: relative;">
                        <template x-if="selectedHero?.pictures && selectedHero.pictures.length > 0">
                            <!-- Blurred Background -->
                            <div style="position: absolute; inset: -20px; background-size: cover; background-position: center; filter: blur(15px); opacity: 0.5;" :style="'background-image: url(' + selectedHero.pictures[picIndex] + ')'"></div>
                        </template>
                        <template x-if="selectedHero?.pictures && selectedHero.pictures.length > 0">
                            <!-- Main Full Image -->
                            <img :src="selectedHero.pictures[picIndex]" x-on:error="if(picIndex < selectedHero.pictures.length - 1) picIndex++; else $el.style.display='none'" style="max-width: 100%; max-height: 400px; object-fit: contain; position: relative; z-index: 2; box-shadow: 0 10px 30px rgba(0,0,0,0.7);">
                        </template>
                        
                        <template x-if="!selectedHero?.pictures || selectedHero.pictures.length === 0">
                            <div style="width: 100%; height: 300px; background: var(--glass-border); display: flex; align-items: center; justify-content: center; font-size: 6rem;">🦸</div>
                        </template>
                    </div>
                    
                    <!-- Content -->
                    <div style="padding: 2rem; text-align: center; overflow-y: auto;">
                        <h2 style="margin: 0 0 0.5rem 0; font-size: 2.2rem; color: white; display: flex; align-items: center; justify-content: center; gap: 0.8rem;" x-text="selectedHero?.name"></h2>
                        
                        <div style="display: flex; align-items: center; justify-content: center; gap: 1rem; margin-bottom: 1.5rem;">
                            <span style="color: var(--text-secondary); font-size: 0.9rem;">ID: <span x-text="selectedHero?.heroID"></span></span>
                            <a :href="'/cards?tags[0]=' + encodeURIComponent((selectedHero?.name || '').toLowerCase().replace(/\s+/g, '_'))" style="background: rgba(96, 165, 250, 0.1); color: #60a5fa; padding: 4px 12px; border-radius: 12px; font-size: 0.85rem; text-decoration: none; border: 1px solid rgba(96, 165, 250, 0.3); transition: background 0.2s;" onmouseover="this.style.background='rgba(96, 165, 250, 0.2)'" onmouseout="this.style.background='rgba(96, 165, 250, 0.1)'">
                                <i class="ph-bold ph-cards"></i> View Hero Cards
                            </a>
                        </div>
                        
                        <div style="display: flex; justify-content: center; gap: 2rem; margin-bottom: 2rem; background: rgba(0,0,0,0.3); padding: 1.2rem; border-radius: 12px; border: 1px solid rgba(255,255,255,0.05);">
                            <div>
                                <div style="color: var(--text-secondary); font-size: 0.8rem; text-transform: uppercase;">Level</div>
                                <div style="font-weight: bold; font-size: 1.8rem; color: #a855f7;" x-text="selectedHero?.level"></div>
                            </div>
                            <div>
                                <div style="color: var(--text-secondary); font-size: 0.8rem; text-transform: uppercase;">Hero XP</div>
                                <div style="font-weight: bold; font-size: 1.8rem; color: #fbbf24;" x-text="new Intl.NumberFormat().format(selectedHero?.xp || 0)"></div>
                            </div>
                            <div>
                                <div style="color: var(--text-secondary); font-size: 0.8rem; text-transform: uppercase;">Followers</div>
                                <div style="font-weight: bold; font-size: 1.8rem; color: #3b82f6;" x-text="new Intl.NumberFormat().format(selectedHero?.followers || 0)"></div>
                            </div>
                        </div>
                        
                        <template x-if="selectedHero?.isMyHero">
                            <div style="background: rgba(16, 185, 129, 0.2); color: #10b981; padding: 1.2rem; border-radius: 8px; font-weight: bold; display: flex; align-items: center; justify-content: center; gap: 0.8rem; border: 1px solid rgba(16, 185, 129, 0.3); font-size: 1.1rem;">
                                <i class="ph-fill ph-check-circle" style="font-size: 1.5rem;"></i> This is your active hero
                            </div>
                        </template>
                        <template x-if="!selectedHero?.isMyHero">
                            <button @click="$wire.setAsMyHero(selectedHero.heroID); selectedHero.isMyHero = true; showModal = false;" style="width: 100%; background: linear-gradient(135deg, #ec4899, #8b5cf6); color: white; border: none; padding: 1.2rem; border-radius: 8px; font-weight: bold; font-size: 1.2rem; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s; display: flex; align-items: center; justify-content: center; gap: 0.8rem; font-family: inherit;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 10px 25px rgba(236,72,153,0.4)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                                <i class="ph-bold ph-star" style="font-size: 1.5rem;"></i> Set as my hero
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>
