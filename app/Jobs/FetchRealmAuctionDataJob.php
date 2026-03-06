<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\RealmPriceFetchAction;
use App\Models\CatalogItem;
use App\Models\IngestionMetadata;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FetchRealmAuctionDataJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    /**
     * Unique lock duration in seconds (59 minutes — releases before next hourly tick).
     */
    public int $uniqueFor = 3540;

    /**
     * Download realm auction data, run gate checks, and dispatch batch processing.
     */
    public function handle(RealmPriceFetchAction $fetchAction): void
    {
        $catalogItemIds = CatalogItem::pluck('blizzard_item_id');

        if ($catalogItemIds->isEmpty()) {
            Log::info('FetchRealmAuctionDataJob: no catalog items, skipping.');

            return;
        }

        Log::info('FetchRealmAuctionDataJob: fetching realm auction data', [
            'item_count' => $catalogItemIds->count(),
        ]);

        try {
            $result = ($fetchAction)();
        } catch (\RuntimeException $e) {
            Log::error('FetchRealmAuctionDataJob: fetch failed, skipping cycle', [
                'error' => $e->getMessage(),
            ]);
            $meta = IngestionMetadata::singleton();
            $meta->increment('realm_consecutive_failures');

            return;
        }

        $meta = IngestionMetadata::singleton();

        // Primary gate: Last-Modified header comparison
        if ($result['lastModified'] !== null && $result['lastModified'] === $meta->realm_last_modified_at) {
            Log::info('FetchRealmAuctionDataJob: data unchanged (Last-Modified match), skipping.');
            Storage::delete($result['storageKey']);

            return;
        }

        // Fallback gate: response body hash (when Last-Modified absent)
        if ($result['lastModified'] === null && $result['responseHash'] === $meta->realm_response_hash) {
            Log::info('FetchRealmAuctionDataJob: data unchanged (hash match), skipping.');
            Storage::delete($result['storageKey']);

            return;
        }

        DispatchRealmPriceBatchesJob::dispatch(
            $result['storageKey'],
            $result['lastModified'],
            $result['responseHash'],
            now(),
        );

        Log::info('FetchRealmAuctionDataJob: dispatched batch processing.');
    }
}
