<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;

new class extends Component {
    #[On('set-profile-fav')]
    public function setFavorite($cardId)
    {
        if (auth()->check()) {
            $user = auth()->user();

            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => config('services.amuse.api_key')
            ])->timeout(5)->patch(config('services.amuse.api_root') . '/user/preferences?user=' . $user->userID, [
                'preferences' => [
                    'profile' => [
                        'card' => (string)$cardId
                    ]
                ]
            ]);

            if (!$response->successful()) {
                $this->dispatch('notify', message: \App\Support\ApiError::message($response, 'Could not update your profile card.'), type: 'error');
                return;
            }

            $this->dispatch('notify', message: 'Profile favorite card updated!');
        }
    }

    #[On('toggle-fav')]
    public function toggleFav($cardId)
    {
        if (auth()->check()) {
            if (!auth()->user()->canWrite()) {
                $this->dispatch('notify', message: 'You need AmuPlus to perform this action.', type: 'error');
                return;
            }
            $user = auth()->user();
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => config('services.amuse.api_key')
            ])->timeout(5)->patch(config('services.amuse.api_root') . '/user/cards/fav?user=' . $user->userID, [
                'cardID' => (int)$cardId
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                $message = $data['fav'] ? 'Card added to favorites!' : 'Card removed from favorites.';
                $this->dispatch('notify', message: $message);
                $this->dispatch('card-updated', cardId: $cardId, type: 'fav', value: $data['fav']);
            } else {
                $this->dispatch('notify', message: \App\Support\ApiError::message($response, 'Could not update your favorites.'), type: 'error');
            }
        }
    }

    #[On('toggle-wishlist')]
    public function toggleWishlist($cardId)
    {
        if (auth()->check()) {
            if (!auth()->user()->canWrite()) {
                $this->dispatch('notify', message: 'You need AmuPlus to perform this action.', type: 'error');
                return;
            }
            $user = auth()->user();
            $wishlist = \App\Models\UserWishlist::where('userID', $user->userID)
                ->where('cardID', (string)$cardId)
                ->first();
            
            $request = \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => config('services.amuse.api_key')
            ])->timeout(5);
            $url = config('services.amuse.api_root') . '/user/wishlist?user=' . $user->userID;
            $body = ['cardID' => (string)$cardId];

            $response = $wishlist ? $request->delete($url, $body) : $request->post($url, $body);

            if (!$response->successful()) {
                $this->dispatch('notify', message: \App\Support\ApiError::message($response, 'Could not update your wishlist.'), type: 'error');
                return;
            }

            $this->dispatch('notify', message: $wishlist ? 'Card removed from wishlist.' : 'Card added to wishlist!');
            $this->dispatch('card-updated', cardId: $cardId, type: 'wishlist', value: !$wishlist);
        }
    }
}; ?>

<div></div>
