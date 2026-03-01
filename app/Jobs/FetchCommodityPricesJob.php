<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\PriceAggregateAction;
use App\Actions\PriceFetchAction;
use App\Models\IngestionMetadata;
use App\Models\PriceSnapshot;
use App\Models\WatchedItem;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class FetchCommodityPricesJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    /**
     * Unique lock duration in seconds (14 minutes — releases before next 15-minute tick).
     */
    public int $uniqueFor = 840;

    /**
     * Orchestrate fetch, aggregate, and persist for all watched items.
     */
    public function handle(
        PriceFetchAction $fetchAction,
        PriceAggregateAction $aggregateAction,
    ): void {
        $watchedItems = WatchedItem::all();

        if ($watchedItems->isEmpty()) {
            Log::info('FetchCommodityPricesJob: no watched items, skipping.');

            return;
        }

        $itemIds = $watchedItems->pluck('blizzard_item_id')->unique()->values()->all();

        Log::info('FetchCommodityPricesJob: fetching prices', [
            'item_count' => count($itemIds),
        ]);

        try {
            $result = ($fetchAction)($itemIds);
        } catch (\RuntimeException $e) {
            Log::error('FetchCommodityPricesJob: fetch failed, skipping cycle', [
                'error' => $e->getMessage(),
            ]);
            $meta = IngestionMetadata::singleton();
            $meta->increment('consecutive_failures');

            return;
        }

        $meta = IngestionMetadata::singleton();

        // Primary gate: Last-Modified header comparison
        if ($result['lastModified'] !== null && $result['lastModified'] === $meta->last_modified_at) {
            Log::info('FetchCommodityPricesJob: data unchanged (Last-Modified match), skipping write');

            return;
        }

        // Fallback gate: response body hash (when Last-Modified absent)
        $hash = md5($result['rawBody']);
        if ($result['lastModified'] === null && $hash === $meta->response_hash) {
            Log::info('FetchCommodityPricesJob: data unchanged (hash match), skipping write');

            return;
        }

        $listings = $result['listings'];

        $grouped = [];
        foreach ($listings as $listing) {
            $id = $listing['item']['id'];
            $grouped[$id][] = ['unit_price' => $listing['unit_price'], 'quantity' => $listing['quantity']];
        }

        $polledAt = now();

        foreach ($watchedItems as $watchedItem) {
            $itemListings = $grouped[$watchedItem->blizzard_item_id] ?? [];
            $metrics = ($aggregateAction)($itemListings);
            PriceSnapshot::create([
                'watched_item_id' => $watchedItem->id,
                'polled_at'       => $polledAt,
                ...$metrics,
            ]);
        }

        $meta->update([
            'last_modified_at'     => $result['lastModified'],
            'response_hash'        => $hash,
            'last_fetched_at'      => now(),
            'consecutive_failures' => 0,
        ]);

        Log::info('FetchCommodityPricesJob: snapshots written', [
            'snapshot_count' => $watchedItems->count(),
        ]);
    }
}
