<?php

use Illuminate\Support\Facades\Route;

use Livewire\Volt\Volt;

Volt::route('/', 'home-page')->name('home');
Volt::route('/cards', 'all-cards')->name('cards.index');
Volt::route('/claims', 'claims-page')->name('claims.index');
Volt::route('/auctions', 'auctions-page')->name('auctions.index');
Volt::route('/profile', 'profile-page')->name('profile.show');
Volt::route('/inventory', 'inventory-page')->name('inventory.index')->middleware('auth');
Volt::route('/collections', 'collections-page')->name('collections.index');
Volt::route('/preferences', 'preferences-page')->name('preferences.index')->middleware('auth');

use App\Http\Controllers\AuthController;

Route::get('/auth/discord', [AuthController::class, 'redirect'])->name('login.discord');
Route::get('/auth/discord/callback', [AuthController::class, 'callback']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::get('/api/tags/{cardID}', function($cardID) {
    return \App\Models\Tag::where('cardID', (int)$cardID)
        ->where('status', 'clear')
        ->get()
        ->sortByDesc(function($t) {
            return is_array($t->upvotes) ? count($t->upvotes) : 0;
        })
        ->values();
});
