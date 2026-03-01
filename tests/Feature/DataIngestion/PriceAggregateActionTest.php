<?php

declare(strict_types=1);

use App\Actions\PriceAggregateAction;

it('returns zero metrics for empty listings', function (): void {
    $action = new PriceAggregateAction;
    $result = $action([]);

    expect($result)->toBe([
        'min_price'    => 0,
        'avg_price'    => 0,
        'median_price' => 0,
        'total_volume' => 0,
    ]);
});

it('returns single listing values as all metrics', function (): void {
    $action = new PriceAggregateAction;
    $result = $action([
        ['unit_price' => 150000, 'quantity' => 200],
    ]);

    expect($result['min_price'])->toBe(150000);
    expect($result['avg_price'])->toBe(150000);
    expect($result['median_price'])->toBe(150000);
    expect($result['total_volume'])->toBe(200);
});

it('computes correct min, avg, and total_volume for multiple listings', function (): void {
    $action = new PriceAggregateAction;
    // Item 224025 fixture data: {150000, 200}, {175000, 50}
    // min = 150000
    // avg = round((150000*200 + 175000*50) / 250) = round(38750000/250) = 155000
    // total_volume = 250
    // median: cumulative at 150000 = 200 >= ceil(250/2)=125 -> median=150000
    $result = $action([
        ['unit_price' => 150000, 'quantity' => 200],
        ['unit_price' => 175000, 'quantity' => 50],
    ]);

    expect($result['min_price'])->toBe(150000);
    expect($result['avg_price'])->toBe(155000);
    expect($result['median_price'])->toBe(150000);
    expect($result['total_volume'])->toBe(250);
});

it('uses frequency-distribution median so large-quantity bucket dominates over naive sort', function (): void {
    $action = new PriceAggregateAction;
    // Total volume: 515, medianPosition: ceil(515/2) = 258
    // Sorted: price=100000 qty=500 (cumulative=500 >= 258) -> median = 100000
    // Naive sort of 3 unique prices would pick 200000 (middle price) — WRONG
    $result = $action([
        ['unit_price' => 100000, 'quantity' => 500],
        ['unit_price' => 200000, 'quantity' => 10],
        ['unit_price' => 300000, 'quantity' => 5],
    ]);

    expect($result['median_price'])->toBe(100000);
});

it('selects correct median when total volume is even', function (): void {
    $action = new PriceAggregateAction;
    // Total=4, medianPosition=ceil(4/2)=2, cumulative at 100000=2 (>=2) -> median=100000
    $result = $action([
        ['unit_price' => 100000, 'quantity' => 2],
        ['unit_price' => 200000, 'quantity' => 2],
    ]);

    expect($result['median_price'])->toBe(100000);
});

it('returns integer types for all values, never floats', function (): void {
    $action = new PriceAggregateAction;
    // Same input as multiple-listing test (Test 3)
    $result = $action([
        ['unit_price' => 150000, 'quantity' => 200],
        ['unit_price' => 175000, 'quantity' => 50],
    ]);

    expect($result['min_price'])->toBeInt();
    expect($result['avg_price'])->toBeInt();
    expect($result['median_price'])->toBeInt();
    expect($result['total_volume'])->toBeInt();
});

it('rounds avg_price correctly and does not truncate', function (): void {
    $action = new PriceAggregateAction;
    // totalValue = 100*1 + 200*2 = 100 + 400 = 500, totalVolume = 3
    // avg = round(500/3) = round(166.667) = 167 (truncation would give 166)
    $result = $action([
        ['unit_price' => 100, 'quantity' => 1],
        ['unit_price' => 200, 'quantity' => 2],
    ]);

    expect($result['avg_price'])->toBe(167);
});
