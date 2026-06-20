<?php

use Illuminate\Support\Facades\Route;

use Livewire\Volt\Volt;

Volt::route('/', 'home-page')->name('home');
Volt::route('/cards', 'all-cards')->name('cards.index');
Volt::route('/claims', 'claims-page')->name('claims.index');
Volt::route('/auctions', 'auctions-page')->name('auctions.index');
Volt::route('/profile', 'profile-page')->name('profile.show');

use App\Http\Controllers\AuthController;

Route::get('/auth/discord', [AuthController::class, 'redirect'])->name('login.discord');
Route::get('/auth/discord/callback', [AuthController::class, 'callback']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
