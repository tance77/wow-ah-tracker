<?php

declare(strict_types=1);

use App\Actions\PriceFetchAction;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Shared helper: set up Http::fake() with both token and commodities endpoints.
 */
function fakeBothEndpoints(int $commoditiesStatus = 200): void
{
    $commoditiesResponse = $commoditiesStatus === 200
        ? json_decode(file_get_contents(base_path('tests/Fixtures/blizzard_commodities.json')), true)
        : [];

    Http::fake([
        'oauth.battle.net/token' => Http::response(
            ['access_token' => 'test-token', 'token_type' => 'bearer', 'expires_in' => 86400],
            200
        ),
        '*.api.blizzard.com/data/wow/auctions/commodities*' => Http::response(
            $commoditiesResponse,
            $commoditiesStatus
        ),
    ]);
}

beforeEach(function (): void {
    Cache::forget('blizzard_token');
});

it('sends Authorization Bearer header on commodity fetch', function (): void {
    fakeBothEndpoints();

    $action = app(PriceFetchAction::class);
    $action([224025]);

    Http::assertSent(function (Request $request): bool {
        return str_contains($request->url(), 'auctions/commodities')
            && $request->hasHeader('Authorization', 'Bearer test-token');
    });
});

it('sends namespace=dynamic-us query parameter', function (): void {
    fakeBothEndpoints();

    $action = app(PriceFetchAction::class);
    $action([224025]);

    Http::assertSent(function (Request $request): bool {
        return str_contains($request->url(), 'auctions/commodities')
            && str_contains($request->url(), 'namespace=dynamic-us');
    });
});

it('filters results to only requested item IDs', function (): void {
    fakeBothEndpoints();

    $action = app(PriceFetchAction::class);
    $result = $action([224025, 210781]);

    // Fixture has entries for 224025, 210781, 210930, 999999
    // Only 224025 and 210781 requested
    expect($result['groupedListings'])->toHaveKeys([224025, 210781]);
    expect($result['groupedListings'])->not->toHaveKey(999999);
    expect($result['groupedListings'])->not->toHaveKey(210930);

    // Fixture has 2 entries for 224025, 2 for 210781
    expect(count($result['groupedListings'][224025]))->toBe(2);
    expect(count($result['groupedListings'][210781]))->toBe(2);
});

it('returns empty array when no items match', function (): void {
    fakeBothEndpoints();

    $action = app(PriceFetchAction::class);
    $result = $action([111111]);

    expect($result['groupedListings'])->toBe([]);
});

it('throws RuntimeException on 500 from commodities endpoint', function (): void {
    fakeBothEndpoints(500);

    $action = app(PriceFetchAction::class);

    expect(fn () => $action([224025]))->toThrow(RuntimeException::class);
});

it('returns listings grouped by item ID with unit_price and quantity', function (): void {
    fakeBothEndpoints();

    $action = app(PriceFetchAction::class);
    $result = $action([210930]);

    // Fixture has exactly 1 entry for 210930
    expect($result['groupedListings'])->toHaveKey(210930);
    expect(count($result['groupedListings'][210930]))->toBe(1);

    $listing = $result['groupedListings'][210930][0];
    expect($listing)->toHaveKeys(['unit_price', 'quantity']);
    expect($listing['unit_price'])->toBeInt();
    expect($listing['quantity'])->toBeInt();
});
