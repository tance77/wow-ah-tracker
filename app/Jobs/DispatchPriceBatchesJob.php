<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\CatalogItem;
use App\Models\IngestionMetadata;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DispatchPriceBatchesJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $storageKey,
        public readonly ?string $lastModified,
        public readonly string $responseHash,
        public readonly CarbonInterface $polledAt,
    ) {}

    /**
     * Chunk catalog items into batches and dispatch as a Bus::batch.
     */
    public function handle(): void
    {
        $catalogItems = CatalogItem::all();

        if ($catalogItems->isEmpty()) {
            Log::info('DispatchPriceBatchesJob: no catalog items, cleaning up.');
            Storage::delete($this->storageKey);

            return;
        }

        // Build [catalog_item_id => blizzard_item_id] map
        $itemMap = $catalogItems->pluck('blizzard_item_id', 'id')->all();

        $batches = [];
        foreach (array_chunk($itemMap, 50, preserve_keys: true) as $chunk) {
            $batches[] = new AggregatePriceBatchJob(
                $this->storageKey,
                $chunk,
                $this->polledAt,
            );
        }

        $storageKey = $this->storageKey;
        $lastModified = $this->lastModified;
        $responseHash = $this->responseHash;

        Bus::batch($batches)
            ->then(function () use ($storageKey, $lastModified, $responseHash) {
                IngestionMetadata::singleton()->update([
                    'last_modified_at'     => $lastModified,
                    'response_hash'        => $responseHash,
                    'last_fetched_at'      => now(),
                    'consecutive_failures' => 0,
                ]);

                Storage::delete($storageKey);

                Log::info('DispatchPriceBatchesJob: all batches completed, metadata updated.');
            })
            ->catch(function (\Illuminate\Bus\Batch $batch, \Throwable $e) use ($storageKey) {
                Log::error('DispatchPriceBatchesJob: batch failed', [
                    'error' => $e->getMessage(),
                ]);

                Storage::delete($storageKey);
            })
            ->dispatch();

        Log::info('DispatchPriceBatchesJob: dispatched batch', [
            'batch_count' => count($batches),
            'item_count'  => count($itemMap),
        ]);
    }
}
