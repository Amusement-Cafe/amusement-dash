<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\User;
use App\Models\BotCollection;

new #[Layout('layouts.app')] class extends Component
{
    public array $prefs = [];
    public array $achievements = [];
    public array $completedCollections = [];
    public array $cloutedCollections = [];
    public string $hexColor = '#000000';

    public function mount()
    {
        $user = auth()->user();
        $this->prefs = $user->preferences ?? [];
        $this->achievements = $user->achievements ?? [];
        
        $compIds = collect($user->completedCols ?? [])->pluck('id')->toArray();
        if (!empty($compIds)) {
            $this->completedCollections = BotCollection::whereIn('collectionID', $compIds)->get()->keyBy('collectionID')->toArray();
        }

        $cloutIds = collect($user->cloutedCols ?? [])->pluck('id')->toArray();
        if (!empty($cloutIds)) {
            $this->cloutedCollections = BotCollection::whereIn('collectionID', $cloutIds)->get()->keyBy('collectionID')->toArray();
        }

        // Initialize missing defaults
        $this->prefs['notify'] ??= [];
        $this->prefs['interact'] ??= [];
        $this->prefs['profile'] ??= [];
        
        $decimalColor = $this->prefs['profile']['color'] ?? 16756480;
        $this->hexColor = '#' . str_pad(dechex((int)$decimalColor), 6, '0', STR_PAD_LEFT);
    }

    public function savePreferences()
    {
        $user = auth()->user();
        
        // Convert hex back to decimal string for the bot
        $this->prefs['profile']['color'] = (string)hexdec(ltrim($this->hexColor, '#'));
        
        $user->preferences = collect($this->prefs)->toArray();
        $user->save();

        session()->flash('success', 'Preferences saved successfully.');
        $this->dispatch('save-success');
    }
};
?>

<div class="glass-panel" style="padding: 3rem; max-width: 800px; margin: 0 auto;" x-data @save-success.window="window.scrollTo({ top: 0, behavior: 'smooth' })">
    <h1 style="font-size: 2rem; margin-bottom: 2rem; display: flex; align-items: center; gap: 0.5rem;">
        <i class="ph-fill ph-gear" style="color: #a855f7;"></i> Preferences
    </h1>

    @if (session()->has('success'))
        <div style="background: rgba(16, 185, 129, 0.2); color: #10b981; padding: 1rem; border-radius: 8px; margin-bottom: 2rem; border: 1px solid rgba(16, 185, 129, 0.3);">
            {{ session('success') }}
        </div>
    @endif

    <form wire:submit="savePreferences" style="display: flex; flex-direction: column; gap: 2rem;">
        
        <!-- Notify Settings -->
        <div style="background: rgba(0,0,0,0.2); padding: 1.5rem; border-radius: 12px; border: 1px solid var(--glass-border);">
            <h2 style="font-size: 1.5rem; margin-bottom: 1rem; color: var(--accent-solid);">Notifications</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input type="checkbox" wire:model="prefs.notify.aucBidMe" style="width: 18px; height: 18px;">
                    Someone bid on my auction
                </label>
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input type="checkbox" wire:model="prefs.notify.aucOutbid" style="width: 18px; height: 18px;">
                    I was outbid
                </label>
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input type="checkbox" wire:model="prefs.notify.aucNewBid" style="width: 18px; height: 18px;">
                    New bid on watched auction
                </label>
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input type="checkbox" wire:model="prefs.notify.aucEnd" style="width: 18px; height: 18px;">
                    Auction ended
                </label>
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input type="checkbox" wire:model="prefs.notify.announce" style="width: 18px; height: 18px;">
                    Bot Announcements
                </label>
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input type="checkbox" wire:model="prefs.notify.daily" style="width: 18px; height: 18px;">
                    Daily Reminder
                </label>
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input type="checkbox" wire:model="prefs.notify.vote" style="width: 18px; height: 18px;">
                    Vote Reminder
                </label>
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input type="checkbox" wire:model="prefs.notify.completed" style="width: 18px; height: 18px;">
                    Collection Completed
                </label>
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input type="checkbox" wire:model="prefs.notify.effectEnd" style="width: 18px; height: 18px;">
                    Effect Expired
                </label>
            </div>
        </div>

        <!-- Interact Settings -->
        <div style="background: rgba(0,0,0,0.2); padding: 1.5rem; border-radius: 12px; border: 1px solid var(--glass-border);">
            <h2 style="font-size: 1.5rem; margin-bottom: 1rem; color: #3b82f6;">Interactions</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input type="checkbox" wire:model="prefs.interact.canHas" style="width: 18px; height: 18px;">
                    Allow others to /has
                </label>
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input type="checkbox" wire:model="prefs.interact.canDiff" style="width: 18px; height: 18px;">
                    Allow others to /diff
                </label>
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input type="checkbox" wire:model="prefs.interact.canSell" style="width: 18px; height: 18px;">
                    Allow trade requests
                </label>
            </div>
        </div>

        <!-- Profile Settings -->
        <div style="background: rgba(0,0,0,0.2); padding: 1.5rem; border-radius: 12px; border: 1px solid var(--glass-border);">
            <h2 style="font-size: 1.5rem; margin-bottom: 1rem; color: #ec4899;">Profile Display</h2>
            
            <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                <div>
                    <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary);">Bio</label>
                    <textarea wire:model="prefs.profile.bio" style="width: 100%; background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); color: white; padding: 0.8rem; border-radius: 8px; resize: vertical; min-height: 80px;"></textarea>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;">
                    <div>
                        <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary);">Title</label>
                        <select wire:model="prefs.profile.title" style="width: 100%; background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); color: white; padding: 0.8rem; border-radius: 8px;">
                            <option value="" style="color: black;">None</option>
                            @foreach($achievements as $ach)
                                <option value="{{ $ach }}" style="color: black;">{{ $ach }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary);">Profile Color</label>
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <input type="color" wire:model="hexColor" style="width: 50px; height: 50px; padding: 0; background: transparent; border: none; cursor: pointer; border-radius: 8px;">
                            <span style="font-family: monospace; color: var(--text-secondary); font-size: 1.1rem;" x-text="$wire.hexColor.toUpperCase()"></span>
                        </div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;">
                    <div>
                        <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary);">Favorite Complete Collection</label>
                        <select wire:model="prefs.profile.favComplete" style="width: 100%; background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); color: white; padding: 0.8rem; border-radius: 8px;">
                            <option value="" style="color: black;">None</option>
                            @foreach($completedCollections as $id => $col)
                                <option value="{{ $id }}" style="color: black;">{{ $col['name'] ?? $id }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary);">Favorite Clouted Collection</label>
                        <select wire:model="prefs.profile.favClout" style="width: 100%; background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); color: white; padding: 0.8rem; border-radius: 8px;">
                            <option value="" style="color: black;">None</option>
                            @foreach($cloutedCollections as $id => $col)
                                <option value="{{ $id }}" style="color: black;">{{ $col['name'] ?? $id }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                
                <div style="margin-top: 0.5rem; padding-top: 1.5rem; border-top: 1px solid rgba(255,255,255,0.05);">
                    <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary);">Favorite Card</label>
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <input type="text" wire:model="prefs.profile.card" readonly style="flex: 1; background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); color: white; padding: 0.8rem; border-radius: 8px; opacity: 0.7;" placeholder="No favorite card set">
                        <a href="{{ route('cards.index') }}?owner={{ auth()->user()->userID }}" class="btn btn-primary" style="text-decoration: none; padding: 0.8rem 1.5rem;">
                            Set From Inventory
                        </a>
                    </div>
                    <p style="font-size: 0.8rem; color: var(--text-secondary); margin-top: 0.5rem;">You can set your favorite card by viewing any card you own and clicking the "Set as Profile Fav" button.</p>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary" style="padding: 1rem; font-size: 1.1rem; display: flex; align-items: center; justify-content: center; gap: 0.5rem; margin-top: 1rem; width: 100%;" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="savePreferences"><i class="ph-bold ph-floppy-disk"></i> Save Preferences</span>
            <span wire:loading wire:target="savePreferences"><i class="ph-bold ph-spinner" style="animation: spin 1s linear infinite;"></i> Saving...</span>
        </button>

    </form>
</div>
