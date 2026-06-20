<?php

use Livewire\Volt\Component;

new class extends Component {
    public $cardId;
    public $userCopies;

    public function setFavorite()
    {
        $user = auth()->user();
        if ($user && $this->userCopies > 0) {
            $prefs = $user->preferences ?? [];
            $prefs['profile']['card'] = (string)$this->cardId;
            $user->preferences = $prefs;
            $user->save();
            session()->flash('fav_success', 'Profile favorite card updated!');
            $this->dispatch('profile-fav-updated');
        }
    }
}; ?>

<div>
    @if(auth()->check() && $userCopies > 0)
        <button wire:click="setFavorite" style="margin-top: 1rem; width: 100%; padding: 0.8rem; background: var(--glass-bg); border: 1px solid #ec4899; color: #ec4899; border-radius: 8px; font-weight: bold; cursor: pointer; transition: background 0.2s; display: flex; align-items: center; justify-content: center; gap: 0.5rem;" onmouseover="this.style.background='rgba(236, 72, 153, 0.1)'" onmouseout="this.style.background='var(--glass-bg)'">
            <i class="ph-bold ph-heart"></i> Set as Profile Fav
        </button>
        
        @if (session()->has('fav_success'))
            <div style="margin-top: 0.5rem; color: #10b981; font-size: 0.8rem; text-align: center;">
                {{ session('fav_success') }}
            </div>
        @endif
    @endif
</div>
