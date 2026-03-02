<?php

declare(strict_types=1);

use App\Models\CatalogItem;
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
        $catalogItem = CatalogItem::factory()->create(['blizzard_item_id' => 100000 + $i]);
        $item = WatchedItem::factory()->create([
            'user_id' => $user->id,
            'name' => "Test Item $i",
            'blizzard_item_id' => $catalogItem->blizzard_item_id,
        ]);
        foreach (range(1, $snapshotCount) as $j) {
            PriceSnapshot::factory()->create([
                'catalog_item_id' => $catalogItem->id,
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
    $catalogItem = CatalogItem::factory()->create(['blizzard_item_id' => 200001, 'name' => 'Arcane Crystal']);
    $item = WatchedItem::factory()->create([
        'user_id' => $user->id,
        'name' => 'Arcane Crystal',
        'blizzard_item_id' => $catalogItem->blizzard_item_id,
    ]);
    PriceSnapshot::factory()->create([
        'catalog_item_id' => $catalogItem->id,
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
    $catalogItem = CatalogItem::factory()->create(['blizzard_item_id' => 200002, 'name' => 'Trend Item']);
    $item = WatchedItem::factory()->create([
        'user_id' => $user->id,
        'name' => 'Trend Item',
        'blizzard_item_id' => $catalogItem->blizzard_item_id,
    ]);

    // Latest snapshot (highest polled_at → priceSnapshots eager-load limits to 2, latest first)
    PriceSnapshot::factory()->create([
        'catalog_item_id' => $catalogItem->id,
        'median_price' => 200_000,
        'polled_at' => now()->subMinutes(5),
    ]);
    // Previous snapshot (older)
    PriceSnapshot::factory()->create([
        'catalog_item_id' => $catalogItem->id,
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
        ->set('viewMode', 'grid')
        ->assertSee('Awaiting first snapshot');
});

// Empty state

it('shows no items tracked yet message when user has no watched items', function () {
    $user = User::factory()->create();

    Volt::actingAs($user)->test('pages.dashboard')
        ->assertSee('No items tracked yet');
});

// DASH-02 — Chart data dispatch on item detail page

it('dispatches chart-data-updated event on item detail page mount', function () {
    [$user, $items] = createUserWithSnapshots(itemCount: 1, snapshotCount: 3);
    $item = $items->first();

    Volt::actingAs($user)->test('pages.item-detail', ['watchedItem' => $item])
        ->assertDispatched('chart-data-updated');
});

// DASH-03 — Timeframe toggle dispatches updated chart data

it('dispatches chart-data-updated event when timeframe is changed on item detail', function () {
    $user = User::factory()->create();
    $catalogItem = CatalogItem::factory()->create(['blizzard_item_id' => 200003]);
    $item = WatchedItem::factory()->create([
        'user_id' => $user->id,
        'blizzard_item_id' => $catalogItem->blizzard_item_id,
    ]);

    // Snapshot within 24h
    PriceSnapshot::factory()->create([
        'catalog_item_id' => $catalogItem->id,
        'polled_at' => now()->subHours(2),
    ]);
    // Snapshot older than 24h but within 7d
    PriceSnapshot::factory()->create([
        'catalog_item_id' => $catalogItem->id,
        'polled_at' => now()->subDays(3),
    ]);

    Volt::actingAs($user)->test('pages.item-detail', ['watchedItem' => $item])
        ->call('setTimeframe', '24h')
        ->assertDispatched('chart-data-updated');
});

// DASH-06 — Authentication required

it('redirects guests to login', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

// Helper: creates a user with one watched item having $count snapshots at $medianPrice over 7 days
function createUserWithSignalData(int $count, int $medianPrice, int $currentPrice, int $buyThreshold = 10, int $sellThreshold = 10): array
{
    $user = User::factory()->create();
    $catalogItem = CatalogItem::factory()->create(['blizzard_item_id' => 300000 + random_int(1, 99999)]);
    $item = WatchedItem::factory()->create([
        'user_id' => $user->id,
        'name' => 'Signal Item',
        'blizzard_item_id' => $catalogItem->blizzard_item_id,
        'buy_threshold' => $buyThreshold,
        'sell_threshold' => $sellThreshold,
    ]);

    // Historical snapshots spread over 7 days
    foreach (range(1, $count) as $i) {
        PriceSnapshot::factory()->create([
            'catalog_item_id' => $catalogItem->id,
            'median_price' => $medianPrice,
            'polled_at' => now()->subDays(7)->addMinutes(15 * $i),
        ]);
    }

    // Current (most recent) snapshot at the target price
    PriceSnapshot::factory()->create([
        'catalog_item_id' => $catalogItem->id,
        'median_price' => $currentPrice,
        'polled_at' => now()->subMinutes(5),
    ]);

    return [$user, $item];
}

// DASH-04 — Buy signal when price is below buy threshold

it('shows BUY badge when current price is below buy threshold relative to rolling average', function () {
    // 100 snapshots at 100,000 copper avg, current at 88,000 (12% below) — buy_threshold 10% triggers
    [$user, $item] = createUserWithSignalData(
        count: 100,
        medianPrice: 100_000,
        currentPrice: 88_000,
        buyThreshold: 10,
        sellThreshold: 10,
    );

    Volt::actingAs($user)->test('pages.dashboard')
        ->assertSee('BUY');
});

// DASH-05 — Sell signal when price is above sell threshold

it('shows SELL badge when current price is above sell threshold relative to rolling average', function () {
    [$user, $item] = createUserWithSignalData(
        count: 100,
        medianPrice: 100_000,
        currentPrice: 115_000,
        buyThreshold: 10,
        sellThreshold: 10,
    );

    Volt::actingAs($user)->test('pages.dashboard')
        ->assertSee('SELL');
});

// DASH-04/DASH-05 — No badge when price is within thresholds

it('shows no signal badge when price is within thresholds', function () {
    [$user, $item] = createUserWithSignalData(
        count: 100,
        medianPrice: 100_000,
        currentPrice: 100_000,
        buyThreshold: 10,
        sellThreshold: 10,
    );

    Volt::actingAs($user)->test('pages.dashboard')
        ->assertDontSee('BUY')
        ->assertDontSee('SELL')
        ->assertDontSee('Collecting data');
});

// DASH-04/DASH-05 — Insufficient data shows collecting data badge

it('shows collecting data badge when fewer than 24 snapshots exist', function () {
    // Only 20 snapshots — below the 24 minimum threshold
    [$user, $item] = createUserWithSignalData(
        count: 20,
        medianPrice: 100_000,
        currentPrice: 88_000,
        buyThreshold: 10,
        sellThreshold: 10,
    );

    Volt::actingAs($user)->test('pages.dashboard')
        ->assertSee('Collecting data')
        ->assertDontSee('BUY')
        ->assertDontSee('SELL');
});

// DASH-04/DASH-05 — Signal items sorted to top of card grid

it('sorts items with active signals before items without signals', function () {
    $user = User::factory()->create();

    // Non-signaled item (alphabetically first: "Alpha")
    $normalCatalog = CatalogItem::factory()->create(['blizzard_item_id' => 400001, 'name' => 'Alpha Item']);
    $normalItem = WatchedItem::factory()->create([
        'user_id' => $user->id,
        'name' => 'Alpha Item',
        'blizzard_item_id' => $normalCatalog->blizzard_item_id,
        'buy_threshold' => 10,
        'sell_threshold' => 10,
    ]);
    foreach (range(1, 100) as $i) {
        PriceSnapshot::factory()->create([
            'catalog_item_id' => $normalCatalog->id,
            'median_price' => 100_000,
            'polled_at' => now()->subDays(7)->addMinutes(15 * $i),
        ]);
    }
    PriceSnapshot::factory()->create([
        'catalog_item_id' => $normalCatalog->id,
        'median_price' => 100_000,
        'polled_at' => now()->subMinutes(5),
    ]);

    // Signaled item (alphabetically second: "Zeta" — but should appear FIRST because it has a signal)
    $signalCatalog = CatalogItem::factory()->create(['blizzard_item_id' => 400002, 'name' => 'Zeta Item']);
    $signalItem = WatchedItem::factory()->create([
        'user_id' => $user->id,
        'name' => 'Zeta Item',
        'blizzard_item_id' => $signalCatalog->blizzard_item_id,
        'buy_threshold' => 10,
        'sell_threshold' => 10,
    ]);
    foreach (range(1, 100) as $i) {
        PriceSnapshot::factory()->create([
            'catalog_item_id' => $signalCatalog->id,
            'median_price' => 100_000,
            'polled_at' => now()->subDays(7)->addMinutes(15 * $i),
        ]);
    }
    PriceSnapshot::factory()->create([
        'catalog_item_id' => $signalCatalog->id,
        'median_price' => 85_000, // 15% below avg — triggers buy
        'polled_at' => now()->subMinutes(5),
    ]);

    // Zeta (signaled) should appear before Alpha (normal) despite alphabetical ordering
    Volt::actingAs($user)->test('pages.dashboard')
        ->assertSeeInOrder(['Zeta Item', 'Alpha Item']);
});

// DASH-04/DASH-05 — Signal count summary in header

it('shows signal count summary in dashboard header', function () {
    $user = User::factory()->create();

    // Buy signal item
    $buyCatalog = CatalogItem::factory()->create(['blizzard_item_id' => 500001, 'name' => 'Buy Item']);
    $buyItem = WatchedItem::factory()->create([
        'user_id' => $user->id,
        'name' => 'Buy Item',
        'blizzard_item_id' => $buyCatalog->blizzard_item_id,
        'buy_threshold' => 10,
        'sell_threshold' => 10,
    ]);
    foreach (range(1, 100) as $i) {
        PriceSnapshot::factory()->create([
            'catalog_item_id' => $buyCatalog->id,
            'median_price' => 100_000,
            'polled_at' => now()->subDays(7)->addMinutes(15 * $i),
        ]);
    }
    PriceSnapshot::factory()->create([
        'catalog_item_id' => $buyCatalog->id,
        'median_price' => 85_000, // triggers buy
        'polled_at' => now()->subMinutes(5),
    ]);

    // Sell signal item
    $sellCatalog = CatalogItem::factory()->create(['blizzard_item_id' => 500002, 'name' => 'Sell Item']);
    $sellItem = WatchedItem::factory()->create([
        'user_id' => $user->id,
        'name' => 'Sell Item',
        'blizzard_item_id' => $sellCatalog->blizzard_item_id,
        'buy_threshold' => 10,
        'sell_threshold' => 10,
    ]);
    foreach (range(1, 100) as $i) {
        PriceSnapshot::factory()->create([
            'catalog_item_id' => $sellCatalog->id,
            'median_price' => 100_000,
            'polled_at' => now()->subDays(7)->addMinutes(15 * $i),
        ]);
    }
    PriceSnapshot::factory()->create([
        'catalog_item_id' => $sellCatalog->id,
        'median_price' => 115_000, // triggers sell
        'polled_at' => now()->subMinutes(5),
    ]);

    $component = Volt::actingAs($user)->test('pages.dashboard');

    // signalSummary() returns header text — access via component instance (header slot not in component html())
    expect($component->instance()->signalSummary())->toContain('1 buy signal');
    expect($component->instance()->signalSummary())->toContain('1 sell signal');
});

// DASH-04/DASH-05 — Chart event includes threshold annotations on item detail

it('dispatches chart-data-updated with annotations and rolling average on item detail page', function () {
    [$user, $item] = createUserWithSignalData(
        count: 100,
        medianPrice: 100_000,
        currentPrice: 88_000,
        buyThreshold: 10,
        sellThreshold: 15,
    );

    Volt::actingAs($user)->test('pages.item-detail', ['watchedItem' => $item])
        ->assertDispatched('chart-data-updated', function ($name, $data) {
            // Verify annotations array is present and contains buy + sell
            $annotations = $data['annotations'] ?? [];
            $types = array_column($annotations, 'type');
            return in_array('buy', $types, true)
                && in_array('sell', $types, true)
                && isset($data['rollingAvg'])
                && count($data['rollingAvg']) > 0;
        });
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
