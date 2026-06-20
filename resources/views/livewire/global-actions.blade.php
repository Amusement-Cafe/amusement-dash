<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;

new class extends Component {
    #[On('set-profile-fav')]
    public function setFavorite($cardId)
    {
        if (auth()->check()) {
            $user = auth()->user();
            $prefs = $user->preferences ?? [];
            $prefs['profile']['card'] = (string)$cardId;
            $user->preferences = $prefs;
            $user->save();
            
            $this->dispatch('notify', message: 'Profile favorite card updated!');
        }
    }
}; ?>

<div></div>
