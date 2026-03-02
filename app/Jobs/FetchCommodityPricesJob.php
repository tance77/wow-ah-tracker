<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\PriceAggregateAction;
use App\Actions\PriceFetchAction;
use App\Models\CatalogItem;
use App\Models\IngestionMetadata;
use App\Models\PriceSnapshot;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class FetchCommodityPricesJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    /**
     * Unique lock duration in seconds (59 minutes — releases before next hourly tick).
     */
    public int $uniqueFor = 3540;

    /**
     * Orchestrate fetch, aggregate, and persist for all catalog items.
     */
    public function handle(
        PriceFetchAction $fetchAction,
        PriceAggregateAction $aggregateAction,
    ): void {
        $catalogItems = CatalogItem::all()->keyBy('blizzard_item_id');

        if ($catalogItems->isEmpty()) {
            Log::info('FetchCommodityPricesJob: no catalog items, skipping.');

            return;
        }

        $itemIds = $catalogItems->pluck('blizzard_item_id')->all();

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
        $hash = $result['responseHash'];
        if ($result['lastModified'] === null && $hash === $meta->response_hash) {
            Log::info('FetchCommodityPricesJob: data unchanged (hash match), skipping write');

            return;
        }

        $grouped = $result['groupedListings'];

        $polledAt = now();
        $rows = [];

        foreach ($catalogItems as $blizzardItemId => $catalogItem) {
            $itemListings = $grouped[$blizzardItemId] ?? [];
            $metrics = ($aggregateAction)($itemListings);
            $rows[] = [
                'catalog_item_id' => $catalogItem->id,
                'polled_at'       => $polledAt,
                'created_at'      => $polledAt,
                'updated_at'      => $polledAt,
                ...$metrics,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            PriceSnapshot::insert($chunk);
        }

        $meta->update([
            'last_modified_at'     => $result['lastModified'],
            'response_hash'        => $hash,
            'last_fetched_at'      => now(),
            'consecutive_failures' => 0,
        ]);

        Log::info('FetchCommodityPricesJob: snapshots written', [
            'snapshot_count' => count($rows),
        ]);
    }
}
