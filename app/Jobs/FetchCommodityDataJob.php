<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\PriceFetchAction;
use App\Models\CatalogItem;
use App\Models\IngestionMetadata;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class FetchCommodityDataJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    /**
     * Unique lock duration in seconds (59 minutes — releases before next hourly tick).
     */
    public int $uniqueFor = 3540;

    /**
     * Download commodity data, run gate checks, and dispatch batch processing.
     */
    public function handle(PriceFetchAction $fetchAction): void
    {
        $catalogItemIds = CatalogItem::pluck('blizzard_item_id');

        if ($catalogItemIds->isEmpty()) {
            Log::info('FetchCommodityDataJob: no catalog items, skipping.');

            return;
        }

        Log::info('FetchCommodityDataJob: fetching commodity data', [
            'item_count' => $catalogItemIds->count(),
        ]);

        try {
            $result = ($fetchAction)();
        } catch (\RuntimeException $e) {
            Log::error('FetchCommodityDataJob: fetch failed, skipping cycle', [
                'error' => $e->getMessage(),
            ]);
            $meta = IngestionMetadata::singleton();
            $meta->increment('consecutive_failures');

            return;
        }

        $meta = IngestionMetadata::singleton();

        // Primary gate: Last-Modified header comparison
        if ($result['lastModified'] !== null && $result['lastModified'] === $meta->last_modified_at) {
            Log::info('FetchCommodityDataJob: data unchanged (Last-Modified match), skipping.');
            @unlink($result['tempFilePath']);

            return;
        }

        // Fallback gate: response body hash (when Last-Modified absent)
        if ($result['lastModified'] === null && $result['responseHash'] === $meta->response_hash) {
            Log::info('FetchCommodityDataJob: data unchanged (hash match), skipping.');
            @unlink($result['tempFilePath']);

            return;
        }

        DispatchPriceBatchesJob::dispatch(
            $result['tempFilePath'],
            $result['lastModified'],
            $result['responseHash'],
            now(),
        );

        Log::info('FetchCommodityDataJob: dispatched batch processing.');
    }
}
