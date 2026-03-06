<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\ExtractRealmListingsAction;
use App\Models\CatalogItem;
use App\Models\IngestionMetadata;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DispatchRealmPriceBatchesJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $storageKey,
        public readonly ?string $lastModified,
        public readonly string $responseHash,
        public readonly CarbonInterface $polledAt,
    ) {}

    /**
     * Extract all listings in a single file pass, then chunk into batch jobs with pre-extracted data.
     */
    public function handle(ExtractRealmListingsAction $extractAction): void
    {
        $catalogItems = CatalogItem::all();

        if ($catalogItems->isEmpty()) {
            Log::info('DispatchRealmPriceBatchesJob: no catalog items, cleaning up.');
            Storage::delete($this->storageKey);

            return;
        }

        // Build [catalog_item_id => blizzard_item_id] map
        $itemMap = $catalogItems->pluck('blizzard_item_id', 'id')->all();

        // Single-pass extraction: read the auction file ONCE for all item IDs
        $allBlizzardItemIds = array_values(array_unique($itemMap));
        $allListings = ($extractAction)($this->storageKey, $allBlizzardItemIds);

        // Delete the storage file immediately after extraction
        Storage::delete($this->storageKey);

        $batches = [];
        foreach (array_chunk($itemMap, 50, preserve_keys: true) as $chunk) {
            // Filter pre-extracted listings to only this chunk's blizzard item IDs
            $chunkBlizzardIds = array_unique(array_values($chunk));
            $chunkListings = array_intersect_key($allListings, array_flip($chunkBlizzardIds));

            $batches[] = new AggregateRealmPriceBatchJob(
                $chunk,
                $chunkListings,
                $this->polledAt,
            );
        }

        $lastModified = $this->lastModified;
        $responseHash = $this->responseHash;

        Bus::batch($batches)
            ->then(function () use ($lastModified, $responseHash) {
                IngestionMetadata::singleton()->update([
                    'realm_last_modified_at'     => $lastModified,
                    'realm_response_hash'        => $responseHash,
                    'realm_last_fetched_at'      => now(),
                    'realm_consecutive_failures' => 0,
                ]);

                Log::info('DispatchRealmPriceBatchesJob: all batches completed, metadata updated.');
            })
            ->catch(function (\Illuminate\Bus\Batch $batch, \Throwable $e) {
                Log::error('DispatchRealmPriceBatchesJob: batch failed', [
                    'error' => $e->getMessage(),
                ]);
            })
            ->dispatch();

        Log::info('DispatchRealmPriceBatchesJob: dispatched batch', [
            'batch_count' => count($batches),
            'item_count'  => count($itemMap),
        ]);
    }
}
