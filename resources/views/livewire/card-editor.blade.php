<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Url;
use Livewire\Attributes\Layout;
use App\Models\Card;
use App\Models\Tag;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

use Livewire\Attributes\Title;

new #[Layout('layouts.app')] #[Title('Card Editor')] class extends Component
{
    #[Url]
    public $cardId = '';

    public $card;
    public $originalData = [];
    public $editedData = [];
    
    public $originalTags = [];
    public $editedTags = [];

    public $isDirty = false;
    public $fetchError = '';

    public function mount()
    {
        $userRoles = auth()->user()->roles;
        if (!is_array($userRoles)) $userRoles = [];
        $isCardEditor = count(array_intersect(array_map('strtolower', $userRoles), ['metamod', 'tagmod', 'admin'])) > 0;

        if (!$isCardEditor) {
            abort(403, 'Unauthorized access.');
        }

        if (empty($this->cardId)) {
            abort(404, 'Card ID missing.');
        }

        $this->card = Card::where('cardID', (int) $this->cardId)->first();
        if (!$this->card) {
            abort(404, 'Card not found.');
        }

        $this->originalData = [
            'displayName' => $this->card->displayName ?? $this->card->cardName,
            'meta' => (array) ($this->card->meta ?? [])
        ];
        
        // Ensure all meta keys exist for the editor
        $metaKeys = ['booruID', 'booruScore', 'booruRating', 'artist', 'pixivID', 'source', 'image', 'contributor'];
        foreach ($metaKeys as $key) {
            if (!isset($this->originalData['meta'][$key])) {
                $this->originalData['meta'][$key] = '';
            }
        }
        
        $this->editedData = $this->originalData;

        $dbTags = Tag::where('cardID', (int) $this->cardId)->where('status', 'clear')->get();
        $this->originalTags = $dbTags->pluck('tagName')->toArray();
        $this->editedTags = $this->originalTags;
    }

    public function updatedEditedData()
    {
        $this->checkDirty();
    }

    public function checkDirty()
    {
        $this->isDirty = false;
        if ($this->editedData['displayName'] !== $this->originalData['displayName']) {
            $this->isDirty = true;
        }
        foreach ($this->editedData['meta'] as $key => $val) {
            if ($val !== $this->originalData['meta'][$key]) {
                $this->isDirty = true;
            }
        }
        
        // compare arrays
        sort($this->originalTags);
        $currentTags = $this->editedTags;
        sort($currentTags);
        if ($this->originalTags !== $currentTags) {
            $this->isDirty = true;
        }
    }

    public function addTag($tag)
    {
        $tag = trim($tag);
        if (!empty($tag) && !in_array($tag, $this->editedTags)) {
            $this->editedTags[] = $tag;
            $this->checkDirty();
        }
    }

    public function removeTag($tag)
    {
        $this->editedTags = array_values(array_filter($this->editedTags, fn($t) => $t !== $tag));
        $this->checkDirty();
    }

    public function fetchDanbooru()
    {
        $this->fetchError = '';
        $booruID = $this->editedData['meta']['booruID'] ?? '';
        if (empty($booruID)) return;

        try {
            $response = Http::timeout(5)->get("https://danbooru.donmai.us/posts/{$booruID}.json");
            if ($response->successful()) {
                $data = $response->json();
                
                $this->editedData['meta']['booruScore'] = $data['score'] ?? $this->editedData['meta']['booruScore'];
                $this->editedData['meta']['booruRating'] = $data['rating'] ?? $this->editedData['meta']['booruRating'];
                $this->editedData['meta']['source'] = $data['source'] ?? $this->editedData['meta']['source'];
                
                // Add artist if any
                $artists = explode(' ', $data['tag_string_artist'] ?? '');
                if (!empty($artists[0])) {
                    $this->editedData['meta']['artist'] = $artists[0];
                }
                
                // Add danbooru tags
                $allTags = explode(' ', $data['tag_string'] ?? '');
                foreach ($allTags as $t) {
                    $t = trim($t);
                    if (!empty($t) && !in_array($t, $this->editedTags)) {
                        $this->editedTags[] = $t;
                    }
                }
                
                $this->checkDirty();
            } else {
                $this->fetchError = "Danbooru returned " . $response->status();
            }
        } catch (\Exception $e) {
            $this->fetchError = "Failed to fetch from Danbooru.";
        }
    }

    #[\Livewire\Attributes\Computed]
    public function allTagsList()
    {
        return Cache::remember('all_distinct_tags', 3600, function() {
            $rawTags = \App\Models\Tag::raw(function($collection) {
                return $collection->distinct('tagName', ['status' => 'clear']);
            });
            
            return array_values(array_filter((array)$rawTags, function($tag) {
                return is_string($tag) && strlen(trim($tag)) > 0;
            }));
        });
    }

    public function save()
    {
        if (!$this->isDirty) return;

        // Save card changes
        $this->card->displayName = $this->editedData['displayName'];
        
        $metaToSave = [];
        foreach ($this->editedData['meta'] as $k => $v) {
            if ($v !== '' && $v !== null) {
                // Parse booruScore and booruID to int if appropriate
                if ($k === 'booruID' || $k === 'booruScore' || $k === 'booruRating') {
                    // Keep rating as string, but ID and score as int
                    if ($k === 'booruID' || $k === 'booruScore') {
                        $metaToSave[$k] = (int) $v;
                    } else {
                        $metaToSave[$k] = $v;
                    }
                } else {
                    $metaToSave[$k] = $v;
                }
            }
        }
        $this->card->meta = empty($metaToSave) ? null : $metaToSave;
        $this->card->save();

        // Save tag changes
        $tagsToAdd = array_diff($this->editedTags, $this->originalTags);
        $tagsToRemove = array_diff($this->originalTags, $this->editedTags);

        if (!empty($tagsToRemove)) {
            Tag::where('cardID', (int) $this->cardId)
                ->whereIn('tagName', $tagsToRemove)
                ->delete();
        }

        foreach ($tagsToAdd as $t) {
            Tag::create([
                'cardID' => (int) $this->cardId,
                'tagName' => $t,
                'status' => 'clear',
                'userID' => auth()->user()->userID,
                'upvotes' => [],
                'downvotes' => []
            ]);
        }

        // Reset state
        $this->originalData = $this->editedData;
        $this->originalTags = $this->editedTags;
        $this->isDirty = false;
        
        session()->flash('message', 'Card updated successfully.');
    }
};
?>

<div class="glass-panel" style="max-width: 1200px; margin: 0 auto; padding: 2rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 1rem; margin-bottom: 2rem;">
        <h1 style="margin: 0; display: flex; align-items: center; gap: 0.5rem; color: var(--accent-solid);">
            <i class="ph-bold ph-pencil-simple"></i> Card Editor
        </h1>
        <a href="{{ route('cards.index') }}" style="color: var(--text-secondary); text-decoration: none; display: flex; align-items: center; gap: 0.3rem;">
            <i class="ph-bold ph-arrow-left"></i> Back to Cards
        </a>
    </div>

    @if (session()->has('message'))
        <div style="background: rgba(16, 185, 129, 0.2); border: 1px solid #10b981; color: #10b981; padding: 1rem; border-radius: 8px; margin-bottom: 2rem; display: flex; align-items: center; gap: 0.5rem;">
            <i class="ph-bold ph-check-circle"></i> {{ session('message') }}
        </div>
    @endif

    <div style="display: flex; flex-wrap: wrap; gap: 2rem;">
        <!-- Left: Card Image -->
        <div style="flex: 1; min-width: 300px; max-width: 400px;">
            <div style="position: sticky; top: 6rem;">
                <x-card-viewer :card="$card" collectionName="{{ $card->collectionID }}" />
                
                <div class="glass-panel" style="margin-top: 1.5rem; padding: 1rem; border: 1px dashed var(--glass-border);">
                    <p style="margin: 0 0 0.5rem 0; color: var(--text-secondary); font-size: 0.9rem;">Card ID</p>
                    <p style="margin: 0; font-size: 1.2rem; font-weight: bold;">#{{ $card->cardID }}</p>
                </div>
                
                <div class="glass-panel" style="margin-top: 1rem; padding: 1rem; border: 1px dashed var(--glass-border);">
                    <p style="margin: 0 0 0.5rem 0; color: var(--text-secondary); font-size: 0.9rem;">Collection</p>
                    <p style="margin: 0; font-size: 1.2rem; font-weight: bold;">{{ $card->collectionID }}</p>
                </div>
            </div>
        </div>

        <!-- Right: Editor Form -->
        <div style="flex: 2; min-width: 300px; display: flex; flex-direction: column; gap: 1.5rem;">
            
            <!-- Basic Info -->
            <div class="glass-panel" style="padding: 1.5rem;">
                <h3 style="margin: 0 0 1rem 0; display: flex; align-items: center; gap: 0.5rem; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 0.5rem;">
                    <i class="ph-fill ph-identification-card" style="color: #3b82f6;"></i> Basic Information
                </h3>
                
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; color: var(--text-secondary); margin-bottom: 0.5rem; font-size: 0.9rem;">Display Name</label>
                    <input type="text" wire:model.live="editedData.displayName" class="input-field" style="width: 100%; padding: 0.8rem; background: rgba(0,0,0,0.3); border: 1px solid {{ $editedData['displayName'] !== $originalData['displayName'] ? '#3b82f6' : 'rgba(255,255,255,0.1)' }}; color: white; border-radius: 8px;">
                    @if($editedData['displayName'] !== $originalData['displayName'])
                        <span style="color: #3b82f6; font-size: 0.8rem; margin-top: 0.3rem; display: block;">
                            <i class="ph-bold ph-pencil-simple"></i> Changed from "{{ $originalData['displayName'] }}"
                        </span>
                    @endif
                </div>
            </div>

            <!-- Metadata -->
            <div class="glass-panel" style="padding: 1.5rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 0.5rem; margin-bottom: 1rem;">
                    <h3 style="margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="ph-fill ph-database" style="color: #10b981;"></i> Metadata
                    </h3>
                    
                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                        @if($fetchError)
                            <span style="color: #ef4444; font-size: 0.8rem;">{{ $fetchError }}</span>
                        @endif
                        <button wire:click="fetchDanbooru" wire:loading.attr="disabled" class="btn btn-primary" style="background: {{ !empty($editedData['meta']['booruID']) ? 'rgba(16, 185, 129, 0.2)' : 'rgba(255,255,255,0.05)' }}; border: 1px solid {{ !empty($editedData['meta']['booruID']) ? '#10b981' : 'rgba(255,255,255,0.1)' }}; color: {{ !empty($editedData['meta']['booruID']) ? '#10b981' : 'var(--text-secondary)' }}; padding: 0.5rem 1rem; border-radius: 6px; cursor: {{ !empty($editedData['meta']['booruID']) ? 'pointer' : 'not-allowed' }}; transition: all 0.2s;" {{ empty($editedData['meta']['booruID']) ? 'disabled' : '' }}>
                            <span wire:loading.remove wire:target="fetchDanbooru"><i class="ph-bold ph-download-simple"></i> Fetch from Danbooru</span>
                            <span wire:loading wire:target="fetchDanbooru"><i class="ph-bold ph-spinner" style="animation: spin 1s linear infinite;"></i> Fetching...</span>
                        </button>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    @foreach(['booruID', 'booruScore', 'booruRating', 'artist', 'pixivID', 'source', 'image', 'contributor'] as $metaField)
                        @php
                            $isReadOnly = in_array($metaField, ['booruScore', 'booruRating']);
                            $isChanged = $editedData['meta'][$metaField] !== $originalData['meta'][$metaField];
                        @endphp
                        <div>
                            <label style="display: flex; justify-content: space-between; color: var(--text-secondary); margin-bottom: 0.3rem; font-size: 0.85rem;">
                                <span>{{ ucfirst(preg_replace('/([A-Z])/', ' $1', $metaField)) }}</span>
                                @if($isReadOnly)
                                    <span style="color: #fbbf24; font-size: 0.7rem;"><i class="ph-bold ph-lock-key"></i> Read-Only</span>
                                @endif
                            </label>
                            <input type="text" 
                                wire:model.live="editedData.meta.{{ $metaField }}" 
                                {{ $isReadOnly ? 'readonly' : '' }}
                                style="width: 100%; padding: 0.6rem; background: rgba(0,0,0,0.3); border: 1px solid {{ $isChanged ? '#10b981' : 'rgba(255,255,255,0.1)' }}; color: {{ $isReadOnly ? 'var(--text-secondary)' : 'white' }}; border-radius: 6px; outline: none; transition: border-color 0.2s;">
                            
                            @if($isChanged)
                                <span style="color: #10b981; font-size: 0.75rem; margin-top: 0.2rem; display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    <i class="ph-bold ph-arrow-u-down-left"></i> Was: {{ empty($originalData['meta'][$metaField]) ? '(empty)' : $originalData['meta'][$metaField] }}
                                </span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Tags -->
            <div class="glass-panel" style="padding: 1.5rem; position: relative; z-index: 50;" x-data="{ 
                newTag: '',
                showSuggestions: false,
                selectedIndex: -1,
                get suggestions() {
                    if (this.newTag.length < 3) return [];
                    let s = this.newTag.toLowerCase();
                    let matches = this.allTags.filter(t => t.toLowerCase().includes(s));
                    matches.sort((a, b) => {
                        let lowerA = a.toLowerCase();
                        let lowerB = b.toLowerCase();
                        let startsWithA = lowerA.startsWith(s);
                        let startsWithB = lowerB.startsWith(s);
                        if (startsWithA && !startsWithB) return -1;
                        if (!startsWithA && startsWithB) return 1;
                        if (lowerA.length !== lowerB.length) return lowerA.length - lowerB.length;
                        return lowerA.localeCompare(lowerB);
                    });
                    return matches.slice(0, 10);
                },
                handleKeydown(e) {
                    let max = this.suggestions.length - 1;
                    if (max < 0) return;
                    
                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        this.selectedIndex = this.selectedIndex < max ? this.selectedIndex + 1 : max;
                    } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        this.selectedIndex = this.selectedIndex > 0 ? this.selectedIndex - 1 : 0;
                    } else if (e.key === 'Enter') {
                        if (this.selectedIndex >= 0 && this.selectedIndex <= max) {
                            e.preventDefault();
                            let selected = this.suggestions[this.selectedIndex];
                            this.newTag = selected;
                            this.$wire.addTag(this.newTag);
                            this.newTag = '';
                            this.selectedIndex = -1;
                            this.showSuggestions = false;
                        }
                    } else if (e.key === 'Escape') {
                        this.showSuggestions = false;
                        this.selectedIndex = -1;
                    }
                },
                resetSelection() {
                    this.selectedIndex = -1;
                    this.showSuggestions = true;
                },
                allTags: {{ Illuminate\Support\Js::from($this->allTagsList) }}
            }" @click.away="showSuggestions = false; selectedIndex = -1;">
                <h3 style="margin: 0 0 1rem 0; display: flex; align-items: center; gap: 0.5rem; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 0.5rem;">
                    <i class="ph-fill ph-tag" style="color: #a855f7;"></i> Tags
                </h3>
                
                <div style="display: flex; gap: 0.5rem; margin-bottom: 1rem; position: relative;">
                    <input type="text" x-model="newTag" @focus="showSuggestions = true" @input="resetSelection" @keydown="handleKeydown($event)" @keydown.enter.prevent="if(selectedIndex === -1) { $wire.addTag(newTag); newTag = ''; }" placeholder="Add a new tag..." style="flex: 1; padding: 0.6rem; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1); color: white; border-radius: 6px; outline: none;">
                    
                    <div x-show="showSuggestions && suggestions.length > 0" x-transition.opacity.duration.200ms style="display: none; position: absolute; top: 100%; left: 0; background: rgba(15, 23, 42, 0.95); border: 1px solid var(--glass-border); border-radius: 8px; z-index: 1000; width: max-content; min-width: 100%; overflow: hidden; backdrop-filter: blur(10px); margin-top: 0.25rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);">
                        <template x-for="(suggestion, index) in suggestions" :key="suggestion">
                            <div @click="newTag = suggestion; $wire.addTag(newTag); newTag = ''; selectedIndex = -1; showSuggestions = false;" 
                                 :style="`padding: 0.5rem 1rem; color: var(--text-primary); cursor: pointer; transition: background 0.2s; background: ${selectedIndex === index ? 'rgba(255,255,255,0.2)' : 'transparent'};`" 
                                 @mouseover="selectedIndex = index" 
                                 @mouseout="selectedIndex = -1">
                                #<span x-text="suggestion"></span>
                            </div>
                        </template>
                    </div>

                    <button @click="$wire.addTag(newTag); newTag = '';" style="background: rgba(168, 85, 247, 0.2); border: 1px solid #a855f7; color: #a855f7; padding: 0 1rem; border-radius: 6px; cursor: pointer;">
                        <i class="ph-bold ph-plus"></i> Add
                    </button>
                </div>

                <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                    @php
                        $tagsToAdd = array_diff($editedTags, $originalTags);
                        $tagsToRemove = array_diff($originalTags, $editedTags);
                        $unchangedTags = array_intersect($originalTags, $editedTags);
                    @endphp

                    <!-- Added Tags -->
                    @foreach($tagsToAdd as $tag)
                        <div style="background: rgba(16, 185, 129, 0.2); border: 1px dashed #10b981; color: #10b981; padding: 0.3rem 0.8rem; border-radius: 9999px; font-size: 0.85rem; display: flex; align-items: center; gap: 0.3rem;">
                            <i class="ph-bold ph-plus"></i> {{ $tag }}
                            <button wire:click="removeTag('{{ $tag }}')" style="background: none; border: none; color: inherit; cursor: pointer; padding: 0; display: flex; align-items: center;"><i class="ph-bold ph-x"></i></button>
                        </div>
                    @endforeach

                    <!-- Unchanged Tags -->
                    @foreach($unchangedTags as $tag)
                        <div style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: var(--text-primary); padding: 0.3rem 0.8rem; border-radius: 9999px; font-size: 0.85rem; display: flex; align-items: center; gap: 0.3rem;">
                            {{ $tag }}
                            <button wire:click="removeTag('{{ $tag }}')" style="background: none; border: none; color: var(--text-secondary); cursor: pointer; padding: 0; display: flex; align-items: center;"><i class="ph-bold ph-x"></i></button>
                        </div>
                    @endforeach

                    <!-- Removed Tags -->
                    @foreach($tagsToRemove as $tag)
                        <div style="background: rgba(239, 68, 68, 0.1); border: 1px dashed #ef4444; color: #ef4444; padding: 0.3rem 0.8rem; border-radius: 9999px; font-size: 0.85rem; display: flex; align-items: center; gap: 0.3rem; opacity: 0.7;">
                            <i class="ph-bold ph-minus"></i> {{ $tag }}
                            <button wire:click="addTag('{{ $tag }}')" style="background: none; border: none; color: inherit; cursor: pointer; padding: 0; display: flex; align-items: center;" title="Undo Remove"><i class="ph-bold ph-arrow-counter-clockwise"></i></button>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Submit Panel -->
            <div class="glass-panel" style="padding: 1.5rem; background: rgba(10, 10, 10, 0.85); backdrop-filter: blur(12px); border: 1px solid {{ $isDirty ? 'var(--accent-solid)' : 'rgba(255,255,255,0.1)' }}; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    @if($isDirty)
                        <span style="color: var(--accent-solid); font-weight: bold; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="ph-fill ph-warning-circle"></i> Unsaved changes
                        </span>
                    @else
                        <span style="color: var(--text-secondary); display: flex; align-items: center; gap: 0.5rem;">
                            <i class="ph-fill ph-check-circle"></i> Up to date
                        </span>
                    @endif
                </div>
                <button wire:click="save" style="background: {{ $isDirty ? 'var(--accent-solid)' : 'rgba(255,255,255,0.05)' }}; color: {{ $isDirty ? 'white' : 'var(--text-secondary)' }}; padding: 0.8rem 2rem; border: none; border-radius: 8px; font-weight: bold; font-size: 1.1rem; cursor: {{ $isDirty ? 'pointer' : 'not-allowed' }}; transition: all 0.2s; display: flex; align-items: center; gap: 0.5rem;" {{ !$isDirty ? 'disabled' : '' }}>
                    <i class="ph-bold ph-floppy-disk"></i> Submit Edits
                </button>
            </div>
            
        </div>
    </div>
</div>
