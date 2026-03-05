<?php

declare(strict_types=1);

use App\Models\CatalogItem;
use App\Models\PriceSnapshot;
use App\Models\Shuffle;
use App\Models\ShuffleStep;
use App\Models\User;
use Livewire\Volt\Volt;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class)->group('shuffle-batch-calculator');

// ---------------------------------------------------------------------------
// Test Helper: Build a complete shuffle scenario
// ---------------------------------------------------------------------------

/**
 * Build a complete shuffle scenario with user, shuffle, steps, catalog items, and price snapshots.
 *
 * @param  array<array{input_id: int, output_id: int, input_qty: int, output_qty_min: int, output_qty_max: int}>  $stepDefs
 * @param  array<int, array{median_price: int, polled_at?: string}>  $prices
 * @return array{user: User, shuffle: Shuffle}
 */
function buildShuffleScenario(array $stepDefs, array $prices = []): array
{
    $user = User::factory()->create();
    $shuffle = Shuffle::factory()->create(['user_id' => $user->id]);

    // Ensure catalog items exist for all item IDs referenced
    $allItemIds = [];
    foreach ($stepDefs as $def) {
        $allItemIds[] = $def['input_id'];
        $allItemIds[] = $def['output_id'];
    }
    $allItemIds = array_unique($allItemIds);

    foreach ($allItemIds as $blizzardItemId) {
        $existing = CatalogItem::where('blizzard_item_id', $blizzardItemId)->first();
        if (! $existing) {
            CatalogItem::factory()->create([
                'blizzard_item_id' => $blizzardItemId,
                'name' => "Item #{$blizzardItemId}",
                'icon_url' => null,
            ]);
        }
    }

    // Create price snapshots for items that have prices
    foreach ($prices as $blizzardItemId => $priceData) {
        $catalogItem = CatalogItem::where('blizzard_item_id', $blizzardItemId)->firstOrFail();
        PriceSnapshot::factory()->create([
            'catalog_item_id' => $catalogItem->id,
            'median_price' => $priceData['median_price'],
            'polled_at' => $priceData['polled_at'] ?? now()->toDateTimeString(),
        ]);
    }

    // Create steps in order
    foreach ($stepDefs as $i => $def) {
        ShuffleStep::factory()->create([
            'shuffle_id' => $shuffle->id,
            'input_blizzard_item_id' => $def['input_id'],
            'output_blizzard_item_id' => $def['output_id'],
            'input_qty' => $def['input_qty'],
            'output_qty_min' => $def['output_qty_min'],
            'output_qty_max' => $def['output_qty_max'],
            'sort_order' => $i,
        ]);
    }

    return compact('user', 'shuffle');
}

// ---------------------------------------------------------------------------
// CALC-03: profitPerUnit() cascades yield ratios through all steps
// ---------------------------------------------------------------------------

test('profitPerUnit returns correct cascaded profit for 2-step shuffle', function () {
    // Step 1: 1 ore -> 4 bars (input_qty=1, output_qty_min=4)
    // Step 2: 1 bar -> 3 gems (input_qty=1, output_qty_min=3)
    // Cascade: 1 ore -> 4 bars -> floor(4 * 3/1) = 12 gems
    // Input price: 10000 copper (ore), Output price: 5000 copper (gem)
    // Gross output = 5000 * 12 = 60000
    // Net output = round(60000 * 0.95) = 57000
    // Profit = 57000 - 10000 = 47000 copper
    ['shuffle' => $shuffle] = buildShuffleScenario(
        stepDefs: [
            ['input_id' => 400001, 'output_id' => 400002, 'input_qty' => 1, 'output_qty_min' => 4, 'output_qty_max' => 6],
            ['input_id' => 400002, 'output_id' => 400003, 'input_qty' => 1, 'output_qty_min' => 3, 'output_qty_max' => 5],
        ],
        prices: [
            400001 => ['median_price' => 10000],
            400003 => ['median_price' => 5000],
        ]
    );

    expect($shuffle->profitPerUnit())->toBe(47000);
});

test('profitPerUnit cascades yield ratios through 3 steps', function () {
    // Step 1: 1 ore -> 5 bars (ratio 5/1 = 5)
    // Step 2: 2 bars -> 3 gems (per 2 bars: 3 gems; per 1 unit: floor(5 * 3/2) = floor(7.5) = 7)
    // Step 3: 1 gem -> 2 dust (per 1 gem: 2 dust; per 1 unit: floor(7 * 2/1) = 14)
    // Input price: 1000 copper (ore), Output price: 500 copper (dust)
    // Gross output = 500 * 14 = 7000
    // Net output = round(7000 * 0.95) = 6650
    // Profit = 6650 - 1000 = 5650 copper
    ['shuffle' => $shuffle] = buildShuffleScenario(
        stepDefs: [
            ['input_id' => 500001, 'output_id' => 500002, 'input_qty' => 1, 'output_qty_min' => 5, 'output_qty_max' => 7],
            ['input_id' => 500002, 'output_id' => 500003, 'input_qty' => 2, 'output_qty_min' => 3, 'output_qty_max' => 4],
            ['input_id' => 500003, 'output_id' => 500004, 'input_qty' => 1, 'output_qty_min' => 2, 'output_qty_max' => 3],
        ],
        prices: [
            500001 => ['median_price' => 1000],
            500004 => ['median_price' => 500],
        ]
    );

    expect($shuffle->profitPerUnit())->toBe(5650);
});

test('profitPerUnit returns null when shuffle has no steps', function () {
    $user = User::factory()->create();
    $shuffle = Shuffle::factory()->create(['user_id' => $user->id]);

    expect($shuffle->profitPerUnit())->toBeNull();
});

test('profitPerUnit returns null when first input item has no price snapshot', function () {
    ['shuffle' => $shuffle] = buildShuffleScenario(
        stepDefs: [
            ['input_id' => 600001, 'output_id' => 600002, 'input_qty' => 1, 'output_qty_min' => 2, 'output_qty_max' => 3],
        ],
        prices: [
            600002 => ['median_price' => 5000], // output has price, input does not
        ]
    );

    expect($shuffle->profitPerUnit())->toBeNull();
});

test('profitPerUnit returns null when last output item has no price snapshot', function () {
    ['shuffle' => $shuffle] = buildShuffleScenario(
        stepDefs: [
            ['input_id' => 700001, 'output_id' => 700002, 'input_qty' => 1, 'output_qty_min' => 2, 'output_qty_max' => 3],
        ],
        prices: [
            700001 => ['median_price' => 5000], // input has price, output does not
        ]
    );

    expect($shuffle->profitPerUnit())->toBeNull();
});

test('profitPerUnit uses conservative min yield not max', function () {
    // Step 1: 1 input -> min 2, max 10 output
    // Should use min=2 for conservative estimate
    // Input price: 1000, Output price: 1000
    // With min 2: gross = 1000 * 2 = 2000, net = round(2000 * 0.95) = 1900, profit = 1900 - 1000 = 900
    // With max 10: gross = 1000 * 10 = 10000, net = round(10000 * 0.95) = 9500, profit = 9500 - 1000 = 8500
    ['shuffle' => $shuffle] = buildShuffleScenario(
        stepDefs: [
            ['input_id' => 800001, 'output_id' => 800002, 'input_qty' => 1, 'output_qty_min' => 2, 'output_qty_max' => 10],
        ],
        prices: [
            800001 => ['median_price' => 1000],
            800002 => ['median_price' => 1000],
        ]
    );

    expect($shuffle->profitPerUnit())->toBe(900);
});

test('profitPerUnit applies 5 percent AH cut to gross output value', function () {
    // Step 1: 1 input -> 1 output (simple 1:1)
    // Input price: 1000, Output price: 10000
    // Gross output = 10000, Net = round(10000 * 0.95) = 9500
    // Profit = 9500 - 1000 = 8500
    ['shuffle' => $shuffle] = buildShuffleScenario(
        stepDefs: [
            ['input_id' => 900001, 'output_id' => 900002, 'input_qty' => 1, 'output_qty_min' => 1, 'output_qty_max' => 1],
        ],
        prices: [
            900001 => ['median_price' => 1000],
            900002 => ['median_price' => 10000],
        ]
    );

    expect($shuffle->profitPerUnit())->toBe(8500);
});

// ---------------------------------------------------------------------------
// INTG-02 & INTG-03: priceData() and calculatorSteps() computed properties
// ---------------------------------------------------------------------------

test('priceData returns median price keyed by blizzard item id for all items in shuffle steps', function () {
    ['user' => $user, 'shuffle' => $shuffle] = buildShuffleScenario(
        stepDefs: [
            ['input_id' => 110001, 'output_id' => 110002, 'input_qty' => 1, 'output_qty_min' => 2, 'output_qty_max' => 3],
        ],
        prices: [
            110001 => ['median_price' => 5000],
            110002 => ['median_price' => 8000],
        ]
    );

    $component = Volt::actingAs($user)->test('pages.shuffle-detail', ['shuffle' => $shuffle]);
    $priceData = $component->instance()->priceData();

    expect($priceData)->toHaveKey(110001)
        ->and($priceData)->toHaveKey(110002)
        ->and($priceData[110001]['price'])->toBe(5000)
        ->and($priceData[110002]['price'])->toBe(8000);
});

test('priceData sets stale true for snapshots older than 1 hour', function () {
    ['user' => $user, 'shuffle' => $shuffle] = buildShuffleScenario(
        stepDefs: [
            ['input_id' => 120001, 'output_id' => 120002, 'input_qty' => 1, 'output_qty_min' => 1, 'output_qty_max' => 1],
        ],
        prices: [
            120001 => ['median_price' => 5000, 'polled_at' => now()->subMinutes(90)->toDateTimeString()],
            120002 => ['median_price' => 5000, 'polled_at' => now()->subMinutes(30)->toDateTimeString()],
        ]
    );

    $component = Volt::actingAs($user)->test('pages.shuffle-detail', ['shuffle' => $shuffle]);
    $priceData = $component->instance()->priceData();

    expect($priceData[120001]['stale'])->toBeTrue()
        ->and($priceData[120002]['stale'])->toBeFalse();
});

test('priceData sets stale false for snapshots within 1 hour', function () {
    ['user' => $user, 'shuffle' => $shuffle] = buildShuffleScenario(
        stepDefs: [
            ['input_id' => 130001, 'output_id' => 130002, 'input_qty' => 1, 'output_qty_min' => 1, 'output_qty_max' => 1],
        ],
        prices: [
            130001 => ['median_price' => 5000, 'polled_at' => now()->subMinutes(59)->toDateTimeString()],
            130002 => ['median_price' => 3000, 'polled_at' => now()->subMinutes(5)->toDateTimeString()],
        ]
    );

    $component = Volt::actingAs($user)->test('pages.shuffle-detail', ['shuffle' => $shuffle]);
    $priceData = $component->instance()->priceData();

    expect($priceData[130001]['stale'])->toBeFalse()
        ->and($priceData[130002]['stale'])->toBeFalse();
});

test('priceData returns empty entry with null price for items with no snapshots', function () {
    ['user' => $user, 'shuffle' => $shuffle] = buildShuffleScenario(
        stepDefs: [
            ['input_id' => 140001, 'output_id' => 140002, 'input_qty' => 1, 'output_qty_min' => 1, 'output_qty_max' => 1],
        ],
        prices: [] // no prices for either item
    );

    $component = Volt::actingAs($user)->test('pages.shuffle-detail', ['shuffle' => $shuffle]);
    $priceData = $component->instance()->priceData();

    expect($priceData)->toHaveKey(140001)
        ->and($priceData[140001]['price'])->toBeNull()
        ->and($priceData)->toHaveKey(140002)
        ->and($priceData[140002]['price'])->toBeNull();
});

test('calculatorSteps returns array with correct shape', function () {
    ['user' => $user, 'shuffle' => $shuffle] = buildShuffleScenario(
        stepDefs: [
            ['input_id' => 150001, 'output_id' => 150002, 'input_qty' => 2, 'output_qty_min' => 3, 'output_qty_max' => 5],
        ],
        prices: []
    );

    $component = Volt::actingAs($user)->test('pages.shuffle-detail', ['shuffle' => $shuffle]);
    $steps = $component->instance()->calculatorSteps();

    expect($steps)->toHaveCount(1);
    $step = $steps[0];
    expect($step)->toHaveKeys(['id', 'input_id', 'output_id', 'input_qty', 'output_qty_min', 'output_qty_max', 'input_name', 'output_name', 'input_icon', 'output_icon'])
        ->and($step['input_id'])->toBe(150001)
        ->and($step['output_id'])->toBe(150002)
        ->and($step['input_qty'])->toBe(2)
        ->and($step['output_qty_min'])->toBe(3)
        ->and($step['output_qty_max'])->toBe(5)
        ->and($step['input_name'])->toBe('Item #150001')
        ->and($step['output_name'])->toBe('Item #150002');
});

test('calculator section is not rendered when shuffle has no steps', function () {
    $user = User::factory()->create();
    $shuffle = Shuffle::factory()->create(['user_id' => $user->id]);

    Volt::actingAs($user)->test('pages.shuffle-detail', ['shuffle' => $shuffle])
        ->assertDontSee('data-calculator-section');
});

test('calculator section is rendered when shuffle has steps', function () {
    ['user' => $user, 'shuffle' => $shuffle] = buildShuffleScenario(
        stepDefs: [
            ['input_id' => 160001, 'output_id' => 160002, 'input_qty' => 1, 'output_qty_min' => 2, 'output_qty_max' => 3],
        ],
        prices: []
    );

    Volt::actingAs($user)->test('pages.shuffle-detail', ['shuffle' => $shuffle])
        ->assertSee('data-calculator-section');
});
