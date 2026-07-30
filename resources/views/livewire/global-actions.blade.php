<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;

new class extends Component {
    #[On('set-profile-fav')]
    public function setFavorite($cardId)
    {
        if (auth()->check()) {
            $user = auth()->user();

            \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => env('AMUSE_API_KEY')
            ])->timeout(5)->patch(env('AMUSE_API_ROOT') . '/user/preferences?user=' . $user->userID, [
                'preferences' => [
                    'profile' => [
                        'card' => (string)$cardId
                    ]
                ]
            ]);

            $prefs = $user->preferences ?? [];
            $prefs['profile']['card'] = (string)$cardId;
            $user->preferences = $prefs;
            // $user->save(); // Deprecated DB write in favor of API route
            
            $this->dispatch('notify', message: 'Profile favorite card updated!');
        }
    }

    #[On('toggle-fav')]
    public function toggleFav($cardId)
    {
        if (auth()->check()) {
            $user = auth()->user();
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => env('AMUSE_API_KEY')
            ])->timeout(5)->patch(env('AMUSE_API_ROOT') . '/user/cards/fav?user=' . $user->userID, [
                'cardID' => (int)$cardId
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                $message = $data['fav'] ? 'Card added to favorites!' : 'Card removed from favorites.';
                $this->dispatch('notify', message: $message);
                $this->dispatch('card-updated', cardId: $cardId, type: 'fav', value: $data['fav']);
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
                \Illuminate\Support\Facades\Http::withHeaders([
                    'Authorization' => env('AMUSE_API_KEY')
                ])->timeout(5)->delete(env('AMUSE_API_ROOT') . '/user/wishlist?user=' . $user->userID, [
                    'cardID' => (string)$cardId
                ]);
                $this->dispatch('notify', message: 'Card removed from wishlist.');
                $this->dispatch('card-updated', cardId: $cardId, type: 'wishlist', value: false);
            } else {
                \Illuminate\Support\Facades\Http::withHeaders([
                    'Authorization' => env('AMUSE_API_KEY')
                ])->timeout(5)->post(env('AMUSE_API_ROOT') . '/user/wishlist?user=' . $user->userID, [
                    'cardID' => (string)$cardId
                ]);
                $this->dispatch('notify', message: 'Card added to wishlist!');
                $this->dispatch('card-updated', cardId: $cardId, type: 'wishlist', value: true);
            }
        }
    }
}; ?>

<div></div>
