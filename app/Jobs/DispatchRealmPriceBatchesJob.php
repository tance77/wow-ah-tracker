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

class DispatchRealmPriceBatchesJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $filePath,
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
            Log::info('DispatchRealmPriceBatchesJob: no catalog items, cleaning up.');
            @unlink($this->filePath);

            return;
        }

        // Build [catalog_item_id => blizzard_item_id] map
        $itemMap = $catalogItems->pluck('blizzard_item_id', 'id')->all();

        $batches = [];
        foreach (array_chunk($itemMap, 50, preserve_keys: true) as $chunk) {
            $batches[] = new AggregateRealmPriceBatchJob(
                $this->filePath,
                $chunk,
                $this->polledAt,
            );
        }

        $filePath = $this->filePath;
        $lastModified = $this->lastModified;
        $responseHash = $this->responseHash;

        Bus::batch($batches)
            ->then(function () use ($filePath, $lastModified, $responseHash) {
                IngestionMetadata::singleton()->update([
                    'realm_last_modified_at'     => $lastModified,
                    'realm_response_hash'        => $responseHash,
                    'realm_last_fetched_at'      => now(),
                    'realm_consecutive_failures' => 0,
                ]);

                @unlink($filePath);

                Log::info('DispatchRealmPriceBatchesJob: all batches completed, metadata updated.');
            })
            ->catch(function (\Illuminate\Bus\Batch $batch, \Throwable $e) use ($filePath) {
                Log::error('DispatchRealmPriceBatchesJob: batch failed', [
                    'error' => $e->getMessage(),
                ]);

                @unlink($filePath);
            })
            ->dispatch();

        Log::info('DispatchRealmPriceBatchesJob: dispatched batch', [
            'batch_count' => count($batches),
            'item_count'  => count($itemMap),
        ]);
    }
}
