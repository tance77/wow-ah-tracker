<?php

declare(strict_types=1);

use App\Actions\PriceAggregateAction;
use App\Actions\PriceFetchAction;
use App\Jobs\FetchCommodityPricesJob;
use App\Models\PriceSnapshot;
use App\Models\User;
use App\Models\WatchedItem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

function fakeBlizzardHttp(): void
{
    Http::fake([
        'oauth.battle.net/token' => Http::response([
            'access_token' => 'test-token',
            'token_type'   => 'bearer',
            'expires_in'   => 86399,
        ], 200),
        '*.api.blizzard.com/data/wow/auctions/commodities*' => Http::response(
            json_decode(file_get_contents(base_path('tests/Fixtures/blizzard_commodities.json')), true),
            200,
        ),
    ]);
    Cache::forget('blizzard_token');
}

it('writes one snapshot per watched item after handle()', function (): void {
    fakeBlizzardHttp();

    $user    = User::factory()->create();
    $watched = WatchedItem::factory()->create([
        'user_id'          => $user->id,
        'blizzard_item_id' => 224025,
    ]);

    (new FetchCommodityPricesJob)->handle(app(PriceFetchAction::class), app(PriceAggregateAction::class));

    expect(PriceSnapshot::where('watched_item_id', $watched->id)->count())->toBe(1);

    $snapshot = PriceSnapshot::where('watched_item_id', $watched->id)->first();

    expect($snapshot->min_price)->toBeInt()->toBeGreaterThan(0);
    expect($snapshot->avg_price)->toBeInt()->toBeGreaterThan(0);
    expect($snapshot->median_price)->toBeInt()->toBeGreaterThan(0);
    expect($snapshot->total_volume)->toBeInt()->toBeGreaterThan(0);
});

it('writes snapshots for multiple watched items', function (): void {
    fakeBlizzardHttp();

    $user     = User::factory()->create();
    $watched1 = WatchedItem::factory()->create(['user_id' => $user->id, 'blizzard_item_id' => 224025]);
    $watched2 = WatchedItem::factory()->create(['user_id' => $user->id, 'blizzard_item_id' => 210781]);

    (new FetchCommodityPricesJob)->handle(app(PriceFetchAction::class), app(PriceAggregateAction::class));

    expect(PriceSnapshot::count())->toBe(2);
    expect(PriceSnapshot::where('watched_item_id', $watched1->id)->count())->toBe(1);
    expect(PriceSnapshot::where('watched_item_id', $watched2->id)->count())->toBe(1);
});

it('writes one snapshot per watched item when multiple users watch the same item', function (): void {
    fakeBlizzardHttp();

    $user1    = User::factory()->create();
    $user2    = User::factory()->create();
    $watched1 = WatchedItem::factory()->create(['user_id' => $user1->id, 'blizzard_item_id' => 224025]);
    $watched2 = WatchedItem::factory()->create(['user_id' => $user2->id, 'blizzard_item_id' => 224025]);

    (new FetchCommodityPricesJob)->handle(app(PriceFetchAction::class), app(PriceAggregateAction::class));

    // One snapshot per WatchedItem row, not per unique blizzard_item_id
    expect(PriceSnapshot::count())->toBe(2);
    expect(PriceSnapshot::where('watched_item_id', $watched1->id)->count())->toBe(1);
    expect(PriceSnapshot::where('watched_item_id', $watched2->id)->count())->toBe(1);
});

it('skips gracefully and does not call Blizzard API when no watched items exist', function (): void {
    fakeBlizzardHttp();

    (new FetchCommodityPricesJob)->handle(app(PriceFetchAction::class), app(PriceAggregateAction::class));

    expect(PriceSnapshot::count())->toBe(0);
    Http::assertNothingSent();
});

it('writes zero-metric snapshot for a watched item with no Blizzard listings', function (): void {
    fakeBlizzardHttp();

    $user    = User::factory()->create();
    // Item ID 999888 is NOT present in the fixture — no listings will match
    $watched = WatchedItem::factory()->create([
        'user_id'          => $user->id,
        'blizzard_item_id' => 999888,
    ]);

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

    $user = User::factory()->create();
    WatchedItem::factory()->create(['user_id' => $user->id, 'blizzard_item_id' => 224025]);
    WatchedItem::factory()->create(['user_id' => $user->id, 'blizzard_item_id' => 210781]);

    (new FetchCommodityPricesJob)->handle(app(PriceFetchAction::class), app(PriceAggregateAction::class));

    $snapshots  = PriceSnapshot::all();
    $timestamps = $snapshots->pluck('polled_at')->map(fn ($t) => (string) $t)->unique();

    expect($timestamps->count())->toBe(1);
});
