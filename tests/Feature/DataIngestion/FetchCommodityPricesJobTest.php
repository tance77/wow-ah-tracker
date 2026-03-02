<?php

declare(strict_types=1);

use App\Actions\PriceAggregateAction;
use App\Actions\PriceFetchAction;
use App\Jobs\FetchCommodityPricesJob;
use App\Models\CatalogItem;
use App\Models\IngestionMetadata;
use App\Models\PriceSnapshot;
use App\Models\User;
use App\Models\WatchedItem;
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

it('writes one snapshot per catalog item after handle()', function (): void {
    fakeBlizzardHttp();

    $catalogItem = CatalogItem::factory()->create(['blizzard_item_id' => 224025]);

    (new FetchCommodityPricesJob)->handle(app(PriceFetchAction::class), app(PriceAggregateAction::class));

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

    (new FetchCommodityPricesJob)->handle(app(PriceFetchAction::class), app(PriceAggregateAction::class));

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

    (new FetchCommodityPricesJob)->handle(app(PriceFetchAction::class), app(PriceAggregateAction::class));

    // One snapshot per catalog item, not per watched item
    expect(PriceSnapshot::count())->toBe(1);
    expect(PriceSnapshot::where('catalog_item_id', $catalogItem->id)->count())->toBe(1);
});

it('skips gracefully and does not call Blizzard API when no catalog items exist', function (): void {
    fakeBlizzardHttp();

    (new FetchCommodityPricesJob)->handle(app(PriceFetchAction::class), app(PriceAggregateAction::class));

    expect(PriceSnapshot::count())->toBe(0);
    Http::assertNothingSent();
});

it('writes zero-metric snapshot for a catalog item with no Blizzard listings', function (): void {
    fakeBlizzardHttp();

    // Item ID 999888 is NOT present in the fixture — no listings will match
    $catalogItem = CatalogItem::factory()->create(['blizzard_item_id' => 999888]);

    (new FetchCommodityPricesJob)->handle(app(PriceFetchAction::class), app(PriceAggregateAction::class));

    expect(PriceSnapshot::count())->toBe(1);

    $snapshot = PriceSnapshot::first();
    expect($snapshot->min_price)->toBe(0);
    expect($snapshot->avg_price)->toBe(0);
    expect($snapshot->median_price)->toBe(0);
    expect($snapshot->total_volume)->toBe(0);
});

it('prevents duplicate dispatch via ShouldBeUnique', function (): void {
    Queue::fake();

    FetchCommodityPricesJob::dispatch();
    FetchCommodityPricesJob::dispatch();

    Queue::assertPushedTimes(FetchCommodityPricesJob::class, times: 1);
});

it('all snapshots in a single run share the same polled_at timestamp', function (): void {
    fakeBlizzardHttp();

    CatalogItem::factory()->create(['blizzard_item_id' => 224025]);
    CatalogItem::factory()->create(['blizzard_item_id' => 210781]);

    (new FetchCommodityPricesJob)->handle(app(PriceFetchAction::class), app(PriceAggregateAction::class));

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

    (new FetchCommodityPricesJob)->handle(app(PriceFetchAction::class), app(PriceAggregateAction::class));

    expect(PriceSnapshot::count())->toBe(0); // Gate blocked write
});

it('writes snapshots when Last-Modified header has changed', function (): void {
    fakeBlizzardHttp('Sat, 01 Mar 2026 06:00:00 GMT');

    // Pre-seed with a DIFFERENT Last-Modified value
    IngestionMetadata::create([
        'last_modified_at' => 'Fri, 28 Feb 2026 18:00:00 GMT',
        'last_fetched_at'  => now()->subMinutes(15),
    ]);

    CatalogItem::factory()->create(['blizzard_item_id' => 224025]);

    (new FetchCommodityPricesJob)->handle(app(PriceFetchAction::class), app(PriceAggregateAction::class));

    expect(PriceSnapshot::count())->toBe(1); // New data, write allowed
});

it('updates metadata after successful write', function (): void {
    $newLastModified = 'Sat, 01 Mar 2026 06:00:00 GMT';
    fakeBlizzardHttp($newLastModified);

    CatalogItem::factory()->create(['blizzard_item_id' => 224025]);

    (new FetchCommodityPricesJob)->handle(app(PriceFetchAction::class), app(PriceAggregateAction::class));

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

    (new FetchCommodityPricesJob)->handle(app(PriceFetchAction::class), app(PriceAggregateAction::class));

    expect(PriceSnapshot::count())->toBe(0); // Hash gate blocked write
});

it('writes snapshots when hash differs and Last-Modified is absent', function (): void {
    fakeBlizzardHttp(); // No Last-Modified header

    IngestionMetadata::create([
        'response_hash'   => md5('completely-different-response-body'),
        'last_fetched_at' => now()->subMinutes(15),
    ]);

    CatalogItem::factory()->create(['blizzard_item_id' => 224025]);

    (new FetchCommodityPricesJob)->handle(app(PriceFetchAction::class), app(PriceAggregateAction::class));

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

    (new FetchCommodityPricesJob)->handle(app(PriceFetchAction::class), app(PriceAggregateAction::class));

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

    (new FetchCommodityPricesJob)->handle(app(PriceFetchAction::class), app(PriceAggregateAction::class));

    $meta = IngestionMetadata::first();
    expect($meta->consecutive_failures)->toBe(0);
    expect($meta->last_fetched_at)->not->toBeNull();
});

it('writes snapshots and creates metadata on first run with empty table', function (): void {
    fakeBlizzardHttp('Sat, 01 Mar 2026 06:00:00 GMT');

    // No IngestionMetadata row exists (first run)
    expect(IngestionMetadata::count())->toBe(0);

    CatalogItem::factory()->create(['blizzard_item_id' => 224025]);

    (new FetchCommodityPricesJob)->handle(app(PriceFetchAction::class), app(PriceAggregateAction::class));

    expect(PriceSnapshot::count())->toBe(1);
    expect(IngestionMetadata::count())->toBe(1);

    $meta = IngestionMetadata::first();
    expect($meta->last_modified_at)->toBe('Sat, 01 Mar 2026 06:00:00 GMT');
    expect($meta->consecutive_failures)->toBe(0);
});
