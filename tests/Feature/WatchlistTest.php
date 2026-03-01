<?php

declare(strict_types=1);

use App\Models\CatalogItem;
use App\Models\User;
use App\Models\WatchedItem;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Volt\Volt;

uses()->group('watchlist');

// Route Protection

test('watchlist redirects unauthenticated users to login', function () {
    $this->get('/watchlist')->assertRedirect('/login');
});

test('authenticated user can view watchlist', function () {
    $this->actingAs(User::factory()->create())->get('/watchlist')->assertOk();
});

// ITEM-01 — Add from catalog

test('user can add item from catalog', function () {
    $user = User::factory()->create();
    $catalog = CatalogItem::factory()->create(['name' => 'Bismuth', 'blizzard_item_id' => 224025]);

    Volt::actingAs($user)->test('pages.watchlist')
        ->call('addFromCatalog', $catalog->id)
        ->assertHasNoErrors();

    $item = $user->watchedItems()->first();
    expect($item)->not->toBeNull()
        ->and($item->name)->toBe('Bismuth')
        ->and($item->blizzard_item_id)->toBe(224025)
        ->and($item->buy_threshold)->toBe(10)
        ->and($item->sell_threshold)->toBe(10);
});

// ITEM-01 — Add manual

test('user can add item by manual blizzard id', function () {
    $user = User::factory()->create();

    Volt::actingAs($user)->test('pages.watchlist')
        ->set('manualItemId', '99999')
        ->call('addManual')
        ->assertHasNoErrors();

    $item = $user->watchedItems()->first();
    expect($item)->not->toBeNull()
        ->and($item->blizzard_item_id)->toBe(99999)
        ->and($item->name)->toBe('Item #99999');
});

// Duplicate prevention

test('adding duplicate catalog item is a no-op', function () {
    $user = User::factory()->create();
    $catalog = CatalogItem::factory()->create();

    $component = Volt::actingAs($user)->test('pages.watchlist');
    $component->call('addFromCatalog', $catalog->id);
    $component->call('addFromCatalog', $catalog->id);

    expect($user->watchedItems()->count())->toBe(1);
});

// ITEM-02 — Remove

test('user can remove watched item', function () {
    $user = User::factory()->create();
    $item = WatchedItem::factory()->create(['user_id' => $user->id]);

    Volt::actingAs($user)->test('pages.watchlist')
        ->call('removeItem', $item->id)
        ->assertHasNoErrors();

    expect(WatchedItem::find($item->id))->toBeNull();
});

// ITEM-03 — Update buy threshold

test('user can update buy threshold', function () {
    $user = User::factory()->create();
    $item = WatchedItem::factory()->create(['user_id' => $user->id, 'buy_threshold' => 10]);

    Volt::actingAs($user)->test('pages.watchlist')
        ->call('updateThreshold', $item->id, 'buy_threshold', 25);

    expect($item->fresh()->buy_threshold)->toBe(25);
});

// ITEM-04 — Update sell threshold

test('user can update sell threshold', function () {
    $user = User::factory()->create();
    $item = WatchedItem::factory()->create(['user_id' => $user->id, 'sell_threshold' => 10]);

    Volt::actingAs($user)->test('pages.watchlist')
        ->call('updateThreshold', $item->id, 'sell_threshold', 30);

    expect($item->fresh()->sell_threshold)->toBe(30);
});

// ITEM-03/04 edge — Threshold clamping

test('threshold is clamped to max 100', function () {
    $user = User::factory()->create();
    $item = WatchedItem::factory()->create(['user_id' => $user->id, 'buy_threshold' => 10]);

    Volt::actingAs($user)->test('pages.watchlist')
        ->call('updateThreshold', $item->id, 'buy_threshold', 150);

    expect($item->fresh()->buy_threshold)->toBe(100);
});

test('threshold is clamped to min 1', function () {
    $user = User::factory()->create();
    $item = WatchedItem::factory()->create(['user_id' => $user->id, 'sell_threshold' => 10]);

    Volt::actingAs($user)->test('pages.watchlist')
        ->call('updateThreshold', $item->id, 'sell_threshold', 0);

    expect($item->fresh()->sell_threshold)->toBe(1);
});

// Invalid field rejected

test('updateThreshold rejects invalid field name', function () {
    $user = User::factory()->create();
    $item = WatchedItem::factory()->create(['user_id' => $user->id, 'name' => 'Original']);

    Volt::actingAs($user)->test('pages.watchlist')
        ->call('updateThreshold', $item->id, 'name', 99);

    expect($item->fresh()->name)->toBe('Original');
});

// ITEM-05 — User isolation

test('user cannot see another users watchlist items', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    WatchedItem::factory()->create(['user_id' => $userB->id, 'name' => 'Secret Item']);

    $this->actingAs($userA)->get('/watchlist')->assertDontSee('Secret Item');
});

test('user cannot remove another users watched item', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $item = WatchedItem::factory()->create(['user_id' => $userB->id]);

    expect(fn () => Volt::actingAs($userA)->test('pages.watchlist')
        ->call('removeItem', $item->id)
    )->toThrow(ModelNotFoundException::class);

    expect(WatchedItem::find($item->id))->not->toBeNull();
});

test('user cannot update another users threshold', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $item = WatchedItem::factory()->create(['user_id' => $userB->id, 'buy_threshold' => 10]);

    expect(fn () => Volt::actingAs($userA)->test('pages.watchlist')
        ->call('updateThreshold', $item->id, 'buy_threshold', 50)
    )->toThrow(ModelNotFoundException::class);

    expect($item->fresh()->buy_threshold)->toBe(10);
});
