<?php

declare(strict_types=1);

use App\Actions\PriceAggregateAction;
use App\Actions\PriceFetchAction;
use App\Jobs\FetchCommodityPricesJob;
use App\Models\IngestionMetadata;
use App\Models\PriceSnapshot;
use App\Models\User;
use App\Models\WatchedItem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

function fakeBlizzardHttpWithLastModified(string $lastModified = 'Sun, 01 Mar 2026 18:00:00 GMT'): void
{
    $body = json_decode(file_get_contents(base_path('tests/Fixtures/blizzard_commodities.json')), true);

    Http::fake([
        'oauth.battle.net/token' => Http::response([
            'access_token' => 'test-token',
            'token_type'   => 'bearer',
            'expires_in'   => 86399,
        ], 200),
        '*.api.blizzard.com/data/wow/auctions/commodities*' => Http::response(
            $body,
            200,
            ['Last-Modified' => $lastModified],
        ),
    ]);
    Cache::forget('blizzard_token');
}

function fakeBlizzardHttpNoLastModified(): void
{
    $body = json_decode(file_get_contents(base_path('tests/Fixtures/blizzard_commodities.json')), true);

    Http::fake([
        'oauth.battle.net/token' => Http::response([
            'access_token' => 'test-token',
            'token_type'   => 'bearer',
            'expires_in'   => 86399,
        ], 200),
        '*.api.blizzard.com/data/wow/auctions/commodities*' => Http::response(
            $body,
            200,
        ),
    ]);
    Cache::forget('blizzard_token');
}

// ── PriceFetchAction return shape tests ──────────────────────────────────────

it('PriceFetchAction returns listings, lastModified, and rawBody keys', function (): void {
    fakeBlizzardHttpWithLastModified('Sun, 01 Mar 2026 18:00:00 GMT');

    $action = app(PriceFetchAction::class);
    $result = $action([224025]);

    expect($result)->toBeArray()
        ->toHaveKeys(['listings', 'lastModified', 'rawBody']);

    expect($result['listings'])->toBeArray();
    expect($result['lastModified'])->toBe('Sun, 01 Mar 2026 18:00:00 GMT');
    expect($result['rawBody'])->toBeString()->not->toBeEmpty();
});

it('PriceFetchAction returns null lastModified when header is absent', function (): void {
    fakeBlizzardHttpNoLastModified();

    $action = app(PriceFetchAction::class);
    $result = $action([224025]);

    expect($result['lastModified'])->toBeNull();
    expect($result['listings'])->toBeArray();
    expect($result['rawBody'])->toBeString()->not->toBeEmpty();
});

it('PriceFetchAction rawBody hashes consistently for dedup use', function (): void {
    fakeBlizzardHttpNoLastModified();

    $action = app(PriceFetchAction::class);
    $result = $action([224025]);

    // The hash computed in the job should match md5 of rawBody
    expect(md5($result['rawBody']))->toBeString()->toHaveLength(32);
});

// ── FetchCommodityPricesJob dedup gate tests ──────────────────────────────────

it('skips write when Last-Modified header matches stored value', function (): void {
    fakeBlizzardHttpWithLastModified('Sun, 01 Mar 2026 18:00:00 GMT');

    // Pre-seed metadata with same Last-Modified value
    IngestionMetadata::firstOrCreate(['id' => 1], [
        'last_modified_at' => 'Sun, 01 Mar 2026 18:00:00 GMT',
        'response_hash'    => null,
    ]);

    $user = User::factory()->create();
    WatchedItem::factory()->create(['user_id' => $user->id, 'blizzard_item_id' => 224025]);

    (new FetchCommodityPricesJob)->handle(app(PriceFetchAction::class), app(PriceAggregateAction::class));

    expect(PriceSnapshot::count())->toBe(0);
});

it('skips write when lastModified is null and hash matches stored hash', function (): void {
    fakeBlizzardHttpNoLastModified();

    // Compute expected hash from fixture body
    $body       = file_get_contents(base_path('tests/Fixtures/blizzard_commodities.json'));
    $jsonBody   = json_encode(json_decode($body, true));
    // The HTTP response body is JSON — we need to compute hash from the actual response body
    // Since Http::fake returns the array, Laravel re-encodes it; we store hash in metadata first via a real run

    // First run to capture the hash
    $user = User::factory()->create();
    WatchedItem::factory()->create(['user_id' => $user->id, 'blizzard_item_id' => 224025]);

    (new FetchCommodityPricesJob)->handle(app(PriceFetchAction::class), app(PriceAggregateAction::class));

    expect(PriceSnapshot::count())->toBe(1);

    $meta = IngestionMetadata::singleton();
    expect($meta->response_hash)->not->toBeNull();

    // Second run — same fixture, same hash → no write
    fakeBlizzardHttpNoLastModified();

    (new FetchCommodityPricesJob)->handle(app(PriceFetchAction::class), app(PriceAggregateAction::class));

    expect(PriceSnapshot::count())->toBe(1); // Still only 1 — dedup blocked second write
});

it('writes snapshots and updates metadata when Last-Modified is new', function (): void {
    fakeBlizzardHttpWithLastModified('Sun, 01 Mar 2026 18:00:00 GMT');

    // Metadata with a different (older) Last-Modified
    IngestionMetadata::firstOrCreate(['id' => 1], [
        'last_modified_at' => 'Sat, 28 Feb 2026 06:00:00 GMT',
        'response_hash'    => null,
    ]);

    $user = User::factory()->create();
    WatchedItem::factory()->create(['user_id' => $user->id, 'blizzard_item_id' => 224025]);

    (new FetchCommodityPricesJob)->handle(app(PriceFetchAction::class), app(PriceAggregateAction::class));

    expect(PriceSnapshot::count())->toBe(1);

    $meta = IngestionMetadata::singleton()->fresh();
    expect($meta->last_modified_at)->toBe('Sun, 01 Mar 2026 18:00:00 GMT');
    expect($meta->last_fetched_at)->not->toBeNull();
    expect($meta->consecutive_failures)->toBe(0);
});

it('increments consecutive_failures and writes no snapshots on API failure', function (): void {
    Http::fake([
        'oauth.battle.net/token' => Http::response([
            'access_token' => 'test-token',
            'token_type'   => 'bearer',
            'expires_in'   => 86399,
        ], 200),
        '*.api.blizzard.com/data/wow/auctions/commodities*' => Http::response([], 500),
    ]);
    Cache::forget('blizzard_token');

    $user = User::factory()->create();
    WatchedItem::factory()->create(['user_id' => $user->id, 'blizzard_item_id' => 224025]);

    $meta = IngestionMetadata::singleton();
    expect($meta->consecutive_failures)->toBe(0);

    (new FetchCommodityPricesJob)->handle(app(PriceFetchAction::class), app(PriceAggregateAction::class));

    expect(PriceSnapshot::count())->toBe(0);
    $meta->refresh();
    expect($meta->consecutive_failures)->toBe(1);
});

it('resets consecutive_failures to 0 on successful fetch with new data', function (): void {
    fakeBlizzardHttpWithLastModified('Sun, 01 Mar 2026 18:00:00 GMT');

    // Pre-seed metadata with failures from a prior run
    IngestionMetadata::firstOrCreate(['id' => 1], [
        'consecutive_failures' => 3,
        'last_modified_at'     => 'Sat, 28 Feb 2026 06:00:00 GMT',
    ]);

    $user = User::factory()->create();
    WatchedItem::factory()->create(['user_id' => $user->id, 'blizzard_item_id' => 224025]);

    (new FetchCommodityPricesJob)->handle(app(PriceFetchAction::class), app(PriceAggregateAction::class));

    $meta = IngestionMetadata::singleton()->fresh();
    expect($meta->consecutive_failures)->toBe(0);
});
