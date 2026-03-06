<?php

declare(strict_types=1);

use App\Actions\ExtractListingsAction;
use App\Actions\PriceFetchAction;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

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
    $result = $action();

    Http::assertSent(function (Request $request): bool {
        return str_contains($request->url(), 'auctions/commodities')
            && $request->hasHeader('Authorization', 'Bearer test-token');
    });

    Storage::delete($result['storageKey']);
});

it('sends namespace=dynamic-us query parameter', function (): void {
    fakeBothEndpoints();

    $action = app(PriceFetchAction::class);
    $result = $action();

    Http::assertSent(function (Request $request): bool {
        return str_contains($request->url(), 'auctions/commodities')
            && str_contains($request->url(), 'namespace=dynamic-us');
    });

    Storage::delete($result['storageKey']);
});

it('returns storageKey, lastModified, and responseHash', function (): void {
    fakeBothEndpoints();

    $action = app(PriceFetchAction::class);
    $result = $action();

    expect($result)->toBeArray()
        ->toHaveKeys(['storageKey', 'lastModified', 'responseHash']);

    expect($result['storageKey'])->toBeString();
    expect(Storage::exists($result['storageKey']))->toBeTrue();
    expect($result['responseHash'])->toBeString()->toHaveLength(32);

    Storage::delete($result['storageKey']);
});

it('persists downloaded data to a file that ExtractListingsAction can read', function (): void {
    fakeBothEndpoints();

    $fetchAction = app(PriceFetchAction::class);
    $result = $fetchAction();

    $extractAction = app(ExtractListingsAction::class);
    $grouped = $extractAction($result['storageKey'], [224025, 210781]);

    // Fixture has entries for 224025 and 210781
    expect($grouped)->toHaveKeys([224025, 210781]);
    expect($grouped)->not->toHaveKey(999999);
    expect($grouped)->not->toHaveKey(210930);

    // Fixture has 2 entries for 224025, 2 for 210781
    expect(count($grouped[224025]))->toBe(2);
    expect(count($grouped[210781]))->toBe(2);

    Storage::delete($result['storageKey']);
});

it('ExtractListingsAction returns empty array when no items match', function (): void {
    fakeBothEndpoints();

    $fetchAction = app(PriceFetchAction::class);
    $result = $fetchAction();

    $extractAction = app(ExtractListingsAction::class);
    $grouped = $extractAction($result['storageKey'], [111111]);

    expect($grouped)->toBe([]);

    Storage::delete($result['storageKey']);
});

it('throws RuntimeException on 500 from commodities endpoint', function (): void {
    fakeBothEndpoints(500);

    $action = app(PriceFetchAction::class);

    expect(fn () => $action())->toThrow(RuntimeException::class);
});

it('ExtractListingsAction returns listings grouped by item ID with unit_price and quantity', function (): void {
    fakeBothEndpoints();

    $fetchAction = app(PriceFetchAction::class);
    $result = $fetchAction();

    $extractAction = app(ExtractListingsAction::class);
    $grouped = $extractAction($result['storageKey'], [210930]);

    // Fixture has exactly 1 entry for 210930
    expect($grouped)->toHaveKey(210930);
    expect(count($grouped[210930]))->toBe(1);

    $listing = $grouped[210930][0];
    expect($listing)->toHaveKeys(['unit_price', 'quantity']);
    expect($listing['unit_price'])->toBeInt();
    expect($listing['quantity'])->toBeInt();

    Storage::delete($result['storageKey']);
});
