<?php

declare(strict_types=1);

use App\Models\PriceSnapshot;
use App\Models\User;
use App\Models\WatchedItem;
use Livewire\Volt\Volt;

uses()->group('dashboard');

// Helper: creates a user with watched items, each having $snapshotCount snapshots
function createUserWithSnapshots(int $itemCount = 1, int $snapshotCount = 2): array
{
    $user = User::factory()->create();
    $items = collect();
    foreach (range(1, $itemCount) as $i) {
        $item = WatchedItem::factory()->create(['user_id' => $user->id, 'name' => "Test Item $i"]);
        foreach (range(1, $snapshotCount) as $j) {
            PriceSnapshot::factory()->create([
                'watched_item_id' => $item->id,
                'polled_at' => now()->subMinutes(15 * $j),
            ]);
        }
        $items->push($item);
    }

    return [$user, $items];
}

// DASH-06 — User isolation (items)

it('shows only the logged-in users watched items', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    WatchedItem::factory()->create(['user_id' => $userA->id, 'name' => 'Bismuth']);
    WatchedItem::factory()->create(['user_id' => $userB->id, 'name' => 'Mycobloom']);

    Volt::actingAs($userA)->test('pages.dashboard')
        ->assertSee('Bismuth')
        ->assertDontSee('Mycobloom');

    Volt::actingAs($userB)->test('pages.dashboard')
        ->assertSee('Mycobloom')
        ->assertDontSee('Bismuth');
});

// DASH-01 — Summary card price display

it('displays the latest median price in gold format on each card', function () {
    $user = User::factory()->create();
    $item = WatchedItem::factory()->create(['user_id' => $user->id, 'name' => 'Arcane Crystal']);
    PriceSnapshot::factory()->create([
        'watched_item_id' => $item->id,
        'median_price' => 1_453_278, // 145g 32s 78c
        'polled_at' => now()->subMinutes(5),
    ]);

    Volt::actingAs($user)->test('pages.dashboard')
        ->assertSee('Arcane Crystal')
        ->assertSee('145')
        ->assertSee('32')
        ->assertSee('78');
});

// DASH-01 — Trend direction (up)

it('shows an upward trend indicator when current price is higher than previous', function () {
    $user = User::factory()->create();
    $item = WatchedItem::factory()->create(['user_id' => $user->id, 'name' => 'Trend Item']);

    // Latest snapshot (highest polled_at → priceSnapshots eager-load limits to 2, latest first)
    PriceSnapshot::factory()->create([
        'watched_item_id' => $item->id,
        'median_price' => 200_000,
        'polled_at' => now()->subMinutes(5),
    ]);
    // Previous snapshot (older)
    PriceSnapshot::factory()->create([
        'watched_item_id' => $item->id,
        'median_price' => 150_000,
        'polled_at' => now()->subMinutes(20),
    ]);

    // Up trend renders a +% string (positive percentage)
    Volt::actingAs($user)->test('pages.dashboard')
        ->assertSee('+');
});

// DASH-01 — No-snapshot state

it('shows awaiting first snapshot message when item has no price data', function () {
    $user = User::factory()->create();
    WatchedItem::factory()->create(['user_id' => $user->id, 'name' => 'New Item']);

    Volt::actingAs($user)->test('pages.dashboard')
        ->assertSee('Awaiting first snapshot');
});

// Empty state

it('shows no items tracked yet message when user has no watched items', function () {
    $user = User::factory()->create();

    Volt::actingAs($user)->test('pages.dashboard')
        ->assertSee('No items tracked yet');
});

// DASH-02 — Chart data dispatch on item selection

it('dispatches chart-data-updated event when an item is selected', function () {
    [$user, $items] = createUserWithSnapshots(itemCount: 1, snapshotCount: 3);
    $item = $items->first();

    Volt::actingAs($user)->test('pages.dashboard')
        ->call('selectItem', $item->id)
        ->assertDispatched('chart-data-updated');
});

// DASH-03 — Timeframe toggle dispatches updated chart data

it('dispatches chart-data-updated event when timeframe is changed', function () {
    $user = User::factory()->create();
    $item = WatchedItem::factory()->create(['user_id' => $user->id]);

    // Snapshot within 24h
    PriceSnapshot::factory()->create([
        'watched_item_id' => $item->id,
        'polled_at' => now()->subHours(2),
    ]);
    // Snapshot older than 24h but within 7d
    PriceSnapshot::factory()->create([
        'watched_item_id' => $item->id,
        'polled_at' => now()->subDays(3),
    ]);

    Volt::actingAs($user)->test('pages.dashboard')
        ->call('selectItem', $item->id) // select first so selectedItemId is set
        ->call('setTimeframe', '24h')
        ->assertDispatched('chart-data-updated');
});

// DASH-06 — Authentication required

it('redirects guests to login', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

// Gold formatter — unit-level assertions via component method

it('formats copper values to gold silver copper strings correctly', function () {
    $user = User::factory()->create();

    $component = Volt::actingAs($user)->test('pages.dashboard');

    // 1453278 copper = 145g 32s 78c
    expect($component->instance()->formatGold(1_453_278))->toBe('145g 32s 78c');

    // 50000 = 5g (no silver, no copper)
    expect($component->instance()->formatGold(50_000))->toBe('5g');

    // 99 = 99c (no gold, no silver)
    expect($component->instance()->formatGold(99))->toBe('99c');

    // 100 = 1s (exactly 1 silver)
    expect($component->instance()->formatGold(100))->toBe('1s');

    // 0 = 0c (zero edge case)
    expect($component->instance()->formatGold(0))->toBe('0c');
});
