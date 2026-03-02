<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Volt::route('/dashboard', 'pages.dashboard')
    ->middleware(['auth'])
    ->name('dashboard');

Volt::route('/watchlist', 'pages.watchlist')
    ->middleware(['auth'])
    ->name('watchlist');

Volt::route('/item/{watchedItem}', 'pages.item-detail')
    ->middleware(['auth'])
    ->name('item.detail');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
