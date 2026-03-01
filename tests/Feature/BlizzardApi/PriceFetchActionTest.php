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

    // Fixture has 2 entries for 224025, 2 for 210781, 1 for 210930, 1 for 999999
    // Only 224025 and 210781 requested, so 4 entries expected
    expect(count($result))->toBe(4);

    foreach ($result as $entry) {
        expect(in_array($entry['item']['id'], [224025, 210781], strict: true))->toBeTrue();
    }

    // Unwatched item 999999 must not be present
    $returnedIds = array_column(array_column($result, 'item'), 'id');
    expect(in_array(999999, $returnedIds, strict: true))->toBeFalse();
});

it('returns empty array when no watched items match', function (): void {
    fakeBothEndpoints();

    $action = app(PriceFetchAction::class);
    $result = $action([111111]);

    expect($result)->toBe([]);
});

it('throws RuntimeException on 500 from commodities endpoint', function (): void {
    fakeBothEndpoints(500);

    $action = app(PriceFetchAction::class);

    expect(fn () => $action([224025]))->toThrow(RuntimeException::class);
});

it('returns re-indexed array after filtering', function (): void {
    fakeBothEndpoints();

    $action = app(PriceFetchAction::class);
    $result = $action([210930]);

    // Fixture has exactly 1 entry for 210930 — keys must be [0], not sparse
    expect(count($result))->toBe(1);
    expect(array_keys($result))->toBe([0]);
    expect($result[0]['item']['id'])->toBe(210930);
});
