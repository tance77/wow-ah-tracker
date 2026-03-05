<?php

declare(strict_types=1);

use App\Actions\ExtractRealmListingsAction;

function writeRealmFixture(array $auctions): string
{
    $tempFile = tempnam(sys_get_temp_dir(), 'realm_test_');
    $json = json_encode(['auctions' => $auctions]);
    file_put_contents($tempFile, $json);

    return $tempFile;
}

it('extracts buyout prices for matching item IDs', function (): void {
    $filePath = writeRealmFixture([
        ['id' => 1, 'item' => ['id' => 12345], 'buyout' => 50000000, 'quantity' => 1, 'time_left' => 'LONG'],
        ['id' => 2, 'item' => ['id' => 67890], 'buyout' => 30000000, 'quantity' => 1, 'time_left' => 'SHORT'],
    ]);

    $action = new ExtractRealmListingsAction;
    $result = $action($filePath, [12345, 67890]);

    expect($result)->toHaveKey(12345);
    expect($result)->toHaveKey(67890);
    expect($result[12345])->toHaveCount(1);
    expect($result[12345][0])->toBe(['unit_price' => 50000000, 'quantity' => 1]);
    expect($result[67890][0])->toBe(['unit_price' => 30000000, 'quantity' => 1]);

    @unlink($filePath);
});

it('skips bid-only auctions where buyout is zero', function (): void {
    $filePath = writeRealmFixture([
        ['id' => 1, 'item' => ['id' => 12345], 'buyout' => 0, 'quantity' => 1, 'time_left' => 'LONG'],
        ['id' => 2, 'item' => ['id' => 12345], 'buyout' => 50000000, 'quantity' => 1, 'time_left' => 'LONG'],
    ]);

    $action = new ExtractRealmListingsAction;
    $result = $action($filePath, [12345]);

    expect($result)->toHaveKey(12345);
    expect($result[12345])->toHaveCount(1);
    expect($result[12345][0]['unit_price'])->toBe(50000000);

    @unlink($filePath);
});

it('skips items not in the catalog set', function (): void {
    $filePath = writeRealmFixture([
        ['id' => 1, 'item' => ['id' => 12345], 'buyout' => 50000000, 'quantity' => 1, 'time_left' => 'LONG'],
        ['id' => 2, 'item' => ['id' => 99999], 'buyout' => 30000000, 'quantity' => 1, 'time_left' => 'SHORT'],
    ]);

    $action = new ExtractRealmListingsAction;
    $result = $action($filePath, [12345]);

    expect($result)->toHaveKey(12345);
    expect($result)->not->toHaveKey(99999);

    @unlink($filePath);
});

it('handles items with bonus_list and modifiers in item object', function (): void {
    $filePath = writeRealmFixture([
        [
            'id' => 1,
            'item' => [
                'id' => 12345,
                'context' => 1,
                'bonus_list' => [9379, 1234],
                'modifiers' => [
                    ['type' => 28, 'value' => 2207],
                    ['type' => 29, 'value' => 55],
                ],
            ],
            'buyout' => 75000000,
            'quantity' => 1,
            'time_left' => 'VERY_LONG',
        ],
        [
            'id' => 2,
            'item' => [
                'id' => 67890,
                'context' => 5,
                'bonus_list' => [8836],
            ],
            'buyout' => 25000000,
            'quantity' => 1,
            'time_left' => 'LONG',
        ],
    ]);

    $action = new ExtractRealmListingsAction;
    $result = $action($filePath, [12345, 67890]);

    expect($result)->toHaveKey(12345);
    expect($result)->toHaveKey(67890);
    expect($result[12345][0])->toBe(['unit_price' => 75000000, 'quantity' => 1]);
    expect($result[67890][0])->toBe(['unit_price' => 25000000, 'quantity' => 1]);

    @unlink($filePath);
});

it('groups multiple auctions for the same item', function (): void {
    $filePath = writeRealmFixture([
        ['id' => 1, 'item' => ['id' => 12345], 'buyout' => 50000000, 'quantity' => 1, 'time_left' => 'LONG'],
        ['id' => 2, 'item' => ['id' => 12345], 'buyout' => 45000000, 'quantity' => 1, 'time_left' => 'SHORT'],
        ['id' => 3, 'item' => ['id' => 12345], 'buyout' => 60000000, 'quantity' => 1, 'time_left' => 'MEDIUM'],
    ]);

    $action = new ExtractRealmListingsAction;
    $result = $action($filePath, [12345]);

    expect($result[12345])->toHaveCount(3);
    expect($result[12345][0]['unit_price'])->toBe(50000000);
    expect($result[12345][1]['unit_price'])->toBe(45000000);
    expect($result[12345][2]['unit_price'])->toBe(60000000);

    @unlink($filePath);
});
