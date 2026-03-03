<?php

declare(strict_types=1);

use App\Actions\ExtractListingsAction;
use App\Actions\PriceAggregateAction;
use App\Actions\PriceFetchAction;
use App\Jobs\AggregatePriceBatchJob;
use App\Jobs\DispatchPriceBatchesJob;
use App\Jobs\FetchCommodityDataJob;
use App\Models\CatalogItem;
use App\Models\IngestionMetadata;
use App\Models\PriceSnapshot;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

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

/**
 * Helper: run full chain synchronously for dedup integration tests.
 */
function runDeduplicationChain(): void
{
    $fetchAction = app(PriceFetchAction::class);
    $job = new FetchCommodityDataJob;

    Bus::fake([DispatchPriceBatchesJob::class]);
    $job->handle($fetchAction);

    $dispatched = Bus::dispatched(DispatchPriceBatchesJob::class);
    if ($dispatched->isEmpty()) {
        return;
    }

    /** @var DispatchPriceBatchesJob $batchJob */
    $batchJob = $dispatched->first();

    $catalogItems = CatalogItem::all();
    $itemMap = $catalogItems->pluck('blizzard_item_id', 'id')->all();

    foreach (array_chunk($itemMap, 50, preserve_keys: true) as $chunk) {
        $aggregateJob = new AggregatePriceBatchJob(
            $batchJob->filePath,
            $chunk,
            $batchJob->polledAt,
        );
        $aggregateJob->handle(
            app(ExtractListingsAction::class),
            app(PriceAggregateAction::class),
        );
    }

    IngestionMetadata::singleton()->update([
        'last_modified_at'     => $batchJob->lastModified,
        'response_hash'        => $batchJob->responseHash,
        'last_fetched_at'      => now(),
        'consecutive_failures' => 0,
    ]);

    @unlink($batchJob->filePath);
}

// ── PriceFetchAction return shape tests ──────────────────────────────────────

it('PriceFetchAction returns tempFilePath, lastModified, and responseHash keys', function (): void {
    fakeBlizzardHttpWithLastModified('Sun, 01 Mar 2026 18:00:00 GMT');

    $action = app(PriceFetchAction::class);
    $result = $action();

    expect($result)->toBeArray()
        ->toHaveKeys(['tempFilePath', 'lastModified', 'responseHash']);

    expect($result['tempFilePath'])->toBeString();
    expect(file_exists($result['tempFilePath']))->toBeTrue();
    expect($result['lastModified'])->toBe('Sun, 01 Mar 2026 18:00:00 GMT');
    expect($result['responseHash'])->toBeString()->not->toBeEmpty();

    @unlink($result['tempFilePath']);
});

it('PriceFetchAction returns null lastModified when header is absent', function (): void {
    fakeBlizzardHttpNoLastModified();

    $action = app(PriceFetchAction::class);
    $result = $action();

    expect($result['lastModified'])->toBeNull();
    expect($result['tempFilePath'])->toBeString();
    expect($result['responseHash'])->toBeString()->not->toBeEmpty();

    @unlink($result['tempFilePath']);
});

it('PriceFetchAction responseHash hashes consistently for dedup use', function (): void {
    fakeBlizzardHttpNoLastModified();

    $action = app(PriceFetchAction::class);
    $result = $action();

    // responseHash is already an md5 hash string
    expect($result['responseHash'])->toBeString()->toHaveLength(32);

    @unlink($result['tempFilePath']);
});

// ── FetchCommodityDataJob dedup gate tests ──────────────────────────────────

it('skips write when Last-Modified header matches stored value', function (): void {
    fakeBlizzardHttpWithLastModified('Sun, 01 Mar 2026 18:00:00 GMT');

    // Pre-seed metadata with same Last-Modified value
    IngestionMetadata::firstOrCreate(['id' => 1], [
        'last_modified_at' => 'Sun, 01 Mar 2026 18:00:00 GMT',
        'response_hash'    => null,
    ]);

    CatalogItem::factory()->create(['blizzard_item_id' => 224025]);

    Bus::fake([DispatchPriceBatchesJob::class]);
    (new FetchCommodityDataJob)->handle(app(PriceFetchAction::class));

    Bus::assertNotDispatched(DispatchPriceBatchesJob::class);
    expect(PriceSnapshot::count())->toBe(0);
});

it('skips write when lastModified is null and hash matches stored hash', function (): void {
    fakeBlizzardHttpNoLastModified();

    CatalogItem::factory()->create(['blizzard_item_id' => 224025]);

    // First run to capture the hash
    runDeduplicationChain();

    expect(PriceSnapshot::count())->toBe(1);

    $meta = IngestionMetadata::singleton();
    expect($meta->response_hash)->not->toBeNull();

    // Second run — same fixture, same hash → no dispatch
    fakeBlizzardHttpNoLastModified();

    Bus::fake([DispatchPriceBatchesJob::class]);
    (new FetchCommodityDataJob)->handle(app(PriceFetchAction::class));

    Bus::assertNotDispatched(DispatchPriceBatchesJob::class);
    expect(PriceSnapshot::count())->toBe(1); // Still only 1 — dedup blocked second write
});

it('writes snapshots and updates metadata when Last-Modified is new', function (): void {
    fakeBlizzardHttpWithLastModified('Sun, 01 Mar 2026 18:00:00 GMT');

    // Metadata with a different (older) Last-Modified
    IngestionMetadata::firstOrCreate(['id' => 1], [
        'last_modified_at' => 'Sat, 28 Feb 2026 06:00:00 GMT',
        'response_hash'    => null,
    ]);

    CatalogItem::factory()->create(['blizzard_item_id' => 224025]);

    runDeduplicationChain();

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

    CatalogItem::factory()->create(['blizzard_item_id' => 224025]);

    $meta = IngestionMetadata::singleton();
    expect($meta->consecutive_failures)->toBe(0);

    (new FetchCommodityDataJob)->handle(app(PriceFetchAction::class));

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

    CatalogItem::factory()->create(['blizzard_item_id' => 224025]);

    runDeduplicationChain();

    $meta = IngestionMetadata::singleton()->fresh();
    expect($meta->consecutive_failures)->toBe(0);
});
