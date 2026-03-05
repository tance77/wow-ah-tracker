<?php

declare(strict_types=1);

use App\Actions\PriceFetchAction;
use App\Jobs\AggregatePriceBatchJob;
use App\Jobs\DispatchPriceBatchesJob;
use App\Jobs\FetchCommodityDataJob;
use App\Models\CatalogItem;
use App\Models\IngestionMetadata;
use App\Models\PriceSnapshot;
use App\Models\User;
use App\Models\WatchedItem;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

function fakeBlizzardHttp(?string $lastModified = null): void
{
    $headers = $lastModified !== null ? ['Last-Modified' => $lastModified] : [];

    Http::fake([
        'oauth.battle.net/token' => Http::response([
            'access_token' => 'test-token',
            'token_type'   => 'bearer',
            'expires_in'   => 86399,
        ], 200),
        '*.api.blizzard.com/data/wow/auctions/commodities*' => Http::response(
            json_decode(file_get_contents(base_path('tests/Fixtures/blizzard_commodities.json')), true),
            200,
            $headers,
        ),
    ]);
    Cache::forget('blizzard_token');
}

/**
 * Helper: run the full 3-job chain synchronously for integration tests.
 * FetchCommodityDataJob → DispatchPriceBatchesJob → AggregatePriceBatchJob(s)
 */
function runFullChain(): void
{
    $fetchAction = app(PriceFetchAction::class);
    $job = new FetchCommodityDataJob;

    // Capture what FetchCommodityDataJob would dispatch
    Bus::fake([DispatchPriceBatchesJob::class]);
    $job->handle($fetchAction);

    $dispatched = Bus::dispatched(DispatchPriceBatchesJob::class);
    if ($dispatched->isEmpty()) {
        return; // Gate blocked or no catalog items
    }

    /** @var DispatchPriceBatchesJob $batchJob */
    $batchJob = $dispatched->first();

    // Now run DispatchPriceBatchesJob synchronously by calling handle
    // but we need to intercept Bus::batch to run jobs inline
    runDispatchAndAggregate($batchJob);
}

/**
 * Run DispatchPriceBatchesJob and its AggregatePriceBatchJob children synchronously.
 */
function runDispatchAndAggregate(DispatchPriceBatchesJob $batchJob): void
{
    $catalogItems = CatalogItem::all();
    $itemMap = $catalogItems->pluck('blizzard_item_id', 'id')->all();

    foreach (array_chunk($itemMap, 50, preserve_keys: true) as $chunk) {
        $aggregateJob = new AggregatePriceBatchJob(
            $batchJob->filePath,
            $chunk,
            $batchJob->polledAt,
        );
        $aggregateJob->handle(
            app(\App\Actions\ExtractListingsAction::class),
            app(\App\Actions\PriceAggregateAction::class),
        );
    }

    // Simulate the batch then() callback — update metadata
    IngestionMetadata::singleton()->update([
        'last_modified_at'     => $batchJob->lastModified,
        'response_hash'        => $batchJob->responseHash,
        'last_fetched_at'      => now(),
        'consecutive_failures' => 0,
    ]);

    @unlink($batchJob->filePath);
}

it('writes one snapshot per catalog item through the full chain', function (): void {
    fakeBlizzardHttp();

    $catalogItem = CatalogItem::factory()->create(['blizzard_item_id' => 224025]);

    runFullChain();

    expect(PriceSnapshot::where('catalog_item_id', $catalogItem->id)->count())->toBe(1);

    $snapshot = PriceSnapshot::where('catalog_item_id', $catalogItem->id)->first();

    expect($snapshot->min_price)->toBeInt()->toBeGreaterThan(0);
    expect($snapshot->avg_price)->toBeInt()->toBeGreaterThan(0);
    expect($snapshot->median_price)->toBeInt()->toBeGreaterThan(0);
    expect($snapshot->total_volume)->toBeInt()->toBeGreaterThan(0);
});

it('writes snapshots for multiple catalog items', function (): void {
    fakeBlizzardHttp();

    $catalog1 = CatalogItem::factory()->create(['blizzard_item_id' => 224025]);
    $catalog2 = CatalogItem::factory()->create(['blizzard_item_id' => 210781]);

    runFullChain();

    expect(PriceSnapshot::count())->toBe(2);
    expect(PriceSnapshot::where('catalog_item_id', $catalog1->id)->count())->toBe(1);
    expect(PriceSnapshot::where('catalog_item_id', $catalog2->id)->count())->toBe(1);
});

it('writes one snapshot when multiple users watch the same item', function (): void {
    fakeBlizzardHttp();

    $catalogItem = CatalogItem::factory()->create(['blizzard_item_id' => 224025]);

    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    WatchedItem::factory()->create(['user_id' => $user1->id, 'blizzard_item_id' => 224025]);
    WatchedItem::factory()->create(['user_id' => $user2->id, 'blizzard_item_id' => 224025]);

    runFullChain();

    // One snapshot per catalog item, not per watched item
    expect(PriceSnapshot::count())->toBe(1);
    expect(PriceSnapshot::where('catalog_item_id', $catalogItem->id)->count())->toBe(1);
});

it('skips gracefully and does not call Blizzard API when no catalog items exist', function (): void {
    fakeBlizzardHttp();

    $job = new FetchCommodityDataJob;
    $job->handle(app(PriceFetchAction::class));

    expect(PriceSnapshot::count())->toBe(0);
    Http::assertNothingSent();
});

it('skips snapshot for a catalog item with no Blizzard listings', function (): void {
    fakeBlizzardHttp();

    // Item ID 999888 is NOT present in the fixture — no listings will match
    $catalogItem = CatalogItem::factory()->create(['blizzard_item_id' => 999888]);

    runFullChain();

    expect(PriceSnapshot::count())->toBe(0);
});

it('prevents duplicate dispatch via ShouldBeUnique', function (): void {
    Queue::fake();

    FetchCommodityDataJob::dispatch();
    FetchCommodityDataJob::dispatch();

    Queue::assertPushedTimes(FetchCommodityDataJob::class, times: 1);
});

it('all snapshots in a single run share the same polled_at timestamp', function (): void {
    fakeBlizzardHttp();

    CatalogItem::factory()->create(['blizzard_item_id' => 224025]);
    CatalogItem::factory()->create(['blizzard_item_id' => 210781]);

    runFullChain();

    $snapshots  = PriceSnapshot::all();
    $timestamps = $snapshots->pluck('polled_at')->map(fn ($t) => (string) $t)->unique();

    expect($timestamps->count())->toBe(1);
});

it('skips snapshot write when Last-Modified header is unchanged', function (): void {
    $lastModified = 'Sat, 28 Feb 2026 18:00:00 GMT';
    fakeBlizzardHttp($lastModified);

    // Pre-seed metadata with same Last-Modified value the stub returns
    IngestionMetadata::create([
        'last_modified_at' => $lastModified,
        'last_fetched_at'  => now()->subMinutes(15),
    ]);

    CatalogItem::factory()->create(['blizzard_item_id' => 224025]);

    Bus::fake([DispatchPriceBatchesJob::class]);
    (new FetchCommodityDataJob)->handle(app(PriceFetchAction::class));

    Bus::assertNotDispatched(DispatchPriceBatchesJob::class);
    expect(PriceSnapshot::count())->toBe(0);
});

it('writes snapshots when Last-Modified header has changed', function (): void {
    fakeBlizzardHttp('Sat, 01 Mar 2026 06:00:00 GMT');

    // Pre-seed with a DIFFERENT Last-Modified value
    IngestionMetadata::create([
        'last_modified_at' => 'Fri, 28 Feb 2026 18:00:00 GMT',
        'last_fetched_at'  => now()->subMinutes(15),
    ]);

    CatalogItem::factory()->create(['blizzard_item_id' => 224025]);

    runFullChain();

    expect(PriceSnapshot::count())->toBe(1); // New data, write allowed
});

it('updates metadata after successful write', function (): void {
    $newLastModified = 'Sat, 01 Mar 2026 06:00:00 GMT';
    fakeBlizzardHttp($newLastModified);

    CatalogItem::factory()->create(['blizzard_item_id' => 224025]);

    runFullChain();

    $meta = IngestionMetadata::first();
    expect($meta->last_modified_at)->toBe($newLastModified);
    expect($meta->response_hash)->not->toBeNull();
    expect($meta->last_fetched_at)->not->toBeNull();
    expect($meta->consecutive_failures)->toBe(0);
});

it('skips snapshot write via hash fallback when Last-Modified is absent', function (): void {
    fakeBlizzardHttp(); // No Last-Modified header (default)

    // Compute the hash the same way PriceFetchAction will compute it:
    // Http::fake() with an array re-encodes it as JSON for ->body()
    $fixtureData = json_decode(file_get_contents(base_path('tests/Fixtures/blizzard_commodities.json')), true);
    $expectedHash = md5(json_encode($fixtureData));

    IngestionMetadata::create([
        'response_hash'   => $expectedHash,
        'last_fetched_at' => now()->subMinutes(15),
    ]);

    CatalogItem::factory()->create(['blizzard_item_id' => 224025]);

    Bus::fake([DispatchPriceBatchesJob::class]);
    (new FetchCommodityDataJob)->handle(app(PriceFetchAction::class));

    Bus::assertNotDispatched(DispatchPriceBatchesJob::class);
    expect(PriceSnapshot::count())->toBe(0); // Hash gate blocked write
});

it('writes snapshots when hash differs and Last-Modified is absent', function (): void {
    fakeBlizzardHttp(); // No Last-Modified header

    IngestionMetadata::create([
        'response_hash'   => md5('completely-different-response-body'),
        'last_fetched_at' => now()->subMinutes(15),
    ]);

    CatalogItem::factory()->create(['blizzard_item_id' => 224025]);

    runFullChain();

    expect(PriceSnapshot::count())->toBe(1); // Different hash, write allowed
});

it('increments consecutive_failures on API failure and writes no snapshots', function (): void {
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

    (new FetchCommodityDataJob)->handle(app(PriceFetchAction::class));

    expect(PriceSnapshot::count())->toBe(0);

    $meta = IngestionMetadata::first();
    expect($meta->consecutive_failures)->toBe(1);
});

it('resets consecutive_failures to 0 on successful fetch', function (): void {
    fakeBlizzardHttp();

    // Pre-seed with prior failures
    IngestionMetadata::create([
        'consecutive_failures' => 3,
        'last_fetched_at'      => now()->subHour(),
    ]);

    CatalogItem::factory()->create(['blizzard_item_id' => 224025]);

    runFullChain();

    $meta = IngestionMetadata::first();
    expect($meta->consecutive_failures)->toBe(0);
    expect($meta->last_fetched_at)->not->toBeNull();
});

it('writes snapshots and creates metadata on first run with empty table', function (): void {
    fakeBlizzardHttp('Sat, 01 Mar 2026 06:00:00 GMT');

    // No IngestionMetadata row exists (first run)
    expect(IngestionMetadata::count())->toBe(0);

    CatalogItem::factory()->create(['blizzard_item_id' => 224025]);

    runFullChain();

    expect(PriceSnapshot::count())->toBe(1);
    expect(IngestionMetadata::count())->toBe(1);

    $meta = IngestionMetadata::first();
    expect($meta->last_modified_at)->toBe('Sat, 01 Mar 2026 06:00:00 GMT');
    expect($meta->consecutive_failures)->toBe(0);
});

it('FetchCommodityDataJob dispatches DispatchPriceBatchesJob with correct data', function (): void {
    fakeBlizzardHttp('Sat, 01 Mar 2026 06:00:00 GMT');

    CatalogItem::factory()->create(['blizzard_item_id' => 224025]);

    Bus::fake([DispatchPriceBatchesJob::class]);
    (new FetchCommodityDataJob)->handle(app(PriceFetchAction::class));

    Bus::assertDispatched(DispatchPriceBatchesJob::class, function (DispatchPriceBatchesJob $job) {
        return $job->lastModified === 'Sat, 01 Mar 2026 06:00:00 GMT'
            && $job->responseHash !== ''
            && file_exists($job->filePath);
    });
});

it('DispatchPriceBatchesJob creates correct number of batch jobs', function (): void {
    fakeBlizzardHttp();

    // Create 75 catalog items to get 2 batches (50 + 25)
    for ($i = 1; $i <= 75; $i++) {
        CatalogItem::factory()->create(['blizzard_item_id' => 100000 + $i]);
    }

    $fixturePath = base_path('tests/Fixtures/blizzard_commodities.json');

    // Create a temp copy as the "downloaded" file
    $tempPath = storage_path('app/private/temp/test_commodities.json');
    if (! is_dir(dirname($tempPath))) {
        mkdir(dirname($tempPath), 0755, true);
    }
    copy($fixturePath, $tempPath);

    $batched = [];
    Bus::fake();

    $job = new DispatchPriceBatchesJob($tempPath, null, md5_file($fixturePath), now());
    $job->handle();

    Bus::assertBatched(function (\Illuminate\Bus\PendingBatch $batch) {
        return $batch->jobs->count() === 2; // ceil(75/50) = 2 batches
    });

    @unlink($tempPath);
});
