<?php

declare(strict_types=1);

use App\Actions\PriceAggregateAction;

it('returns all zeros for empty listings', function (): void {
    $action = new PriceAggregateAction;
    $result = $action([]);

    expect($result)->toBe([
        'min_price'    => 0,
        'avg_price'    => 0,
        'median_price' => 0,
        'total_volume' => 0,
    ]);
});

it('returns single listing price as min avg and median', function (): void {
    $action = new PriceAggregateAction;
    $result = $action([
        ['unit_price' => 5000, 'quantity' => 1],
    ]);

    expect($result['min_price'])->toBe(5000);
    expect($result['avg_price'])->toBe(5000);
    expect($result['median_price'])->toBe(5000);
    expect($result['total_volume'])->toBe(1);
});

it('computes min price as lowest unit_price across listings', function (): void {
    $action = new PriceAggregateAction;
    $result = $action([
        ['unit_price' => 300, 'quantity' => 5],
        ['unit_price' => 100, 'quantity' => 3],
        ['unit_price' => 200, 'quantity' => 2],
    ]);

    expect($result['min_price'])->toBe(100);
});

it('computes weighted average price rounded to int', function (): void {
    $action = new PriceAggregateAction;
    // (100 * 3 + 200 * 2) / 5 = (300 + 400) / 5 = 700 / 5 = 140
    $result = $action([
        ['unit_price' => 100, 'quantity' => 3],
        ['unit_price' => 200, 'quantity' => 2],
    ]);

    expect($result['avg_price'])->toBe(140);
});

it('rounds weighted average correctly', function (): void {
    $action = new PriceAggregateAction;
    // (100 * 1 + 200 * 1) / 2 = 300 / 2 = 150 (no rounding needed)
    $result = $action([
        ['unit_price' => 100, 'quantity' => 1],
        ['unit_price' => 200, 'quantity' => 1],
    ]);

    expect($result['avg_price'])->toBe(150);
});

it('computes total volume as sum of all quantities', function (): void {
    $action = new PriceAggregateAction;
    $result = $action([
        ['unit_price' => 100, 'quantity' => 10],
        ['unit_price' => 200, 'quantity' => 20],
        ['unit_price' => 300, 'quantity' => 5],
    ]);

    expect($result['total_volume'])->toBe(35);
});

it('large quantity bucket dominates median via frequency distribution', function (): void {
    $action = new PriceAggregateAction;
    // Total volume = 515, median position = ceil(515/2) = 258
    // Sorted: price=100 qty=500 (cumulative=500 >= 258) -> median=100
    $result = $action([
        ['unit_price' => 100, 'quantity' => 500],
        ['unit_price' => 200, 'quantity' => 10],
        ['unit_price' => 300, 'quantity' => 5],
    ]);

    expect($result['median_price'])->toBe(100);
});

it('selects correct median bucket when first bucket does not dominate', function (): void {
    $action = new PriceAggregateAction;
    // Total volume = 15, median position = ceil(15/2) = 8
    // Sorted: price=100 qty=5 (cumulative=5, < 8), price=200 qty=10 (cumulative=15 >= 8) -> median=200
    $result = $action([
        ['unit_price' => 200, 'quantity' => 10],
        ['unit_price' => 100, 'quantity' => 5],
    ]);

    expect($result['median_price'])->toBe(200);
});

it('returns integer types for all fields, never floats', function (): void {
    $action = new PriceAggregateAction;
    $result = $action([
        ['unit_price' => 333, 'quantity' => 2],
        ['unit_price' => 100, 'quantity' => 1],
    ]);

    expect($result['min_price'])->toBeInt();
    expect($result['avg_price'])->toBeInt();
    expect($result['median_price'])->toBeInt();
    expect($result['total_volume'])->toBeInt();
});
