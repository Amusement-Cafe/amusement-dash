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

    #[On('toggle-fav')]
    public function toggleFav($cardId)
    {
        if (auth()->check()) {
            $user = auth()->user();
            $userCard = \App\Models\UserCard::where('userID', $user->userID)
                ->where('cardID', (int)$cardId)
                ->first();
            
            if ($userCard) {
                $userCard->fav = !$userCard->fav;
                $userCard->save();
                
                $message = $userCard->fav ? 'Card added to favorites!' : 'Card removed from favorites.';
                $this->dispatch('notify', message: $message);
                $this->dispatch('card-updated', cardId: $cardId, type: 'fav', value: $userCard->fav);
            }
        }
    }

    #[On('toggle-wishlist')]
    public function toggleWishlist($cardId)
    {
        if (auth()->check()) {
            $user = auth()->user();
            $wishlist = \App\Models\UserWishlist::where('userID', $user->userID)
                ->where('cardID', (string)$cardId)
                ->first();
            
            if ($wishlist) {
                $wishlist->delete();
                $this->dispatch('notify', message: 'Card removed from wishlist.');
                $this->dispatch('card-updated', cardId: $cardId, type: 'wishlist', value: false);
            } else {
                $newWishlist = new \App\Models\UserWishlist();
                $newWishlist->userID = $user->userID;
                $newWishlist->cardID = (string)$cardId;
                $newWishlist->added = new \MongoDB\BSON\UTCDateTime();
                $newWishlist->save();
                
                $this->dispatch('notify', message: 'Card added to wishlist!');
                $this->dispatch('card-updated', cardId: $cardId, type: 'wishlist', value: true);
            }
        }
    }
}; ?>

<div></div>
