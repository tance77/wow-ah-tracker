<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\ExtractListingsAction;
use App\Actions\PriceAggregateAction;
use App\Models\PriceSnapshot;
use Carbon\CarbonInterface;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class AggregatePriceBatchJob implements ShouldQueue
{
    use Batchable, Queueable;

    /**
     * @param  string  $filePath  Path to the downloaded commodities JSON file
     * @param  array<int, int>  $itemMap  [catalog_item_id => blizzard_item_id]
     * @param  CarbonInterface  $polledAt
     */
    public function __construct(
        public readonly string $filePath,
        public readonly array $itemMap,
        public readonly CarbonInterface $polledAt,
    ) {}

    /**
     * Extract listings for this batch's items and write price snapshots.
     */
    public function handle(
        ExtractListingsAction $extractAction,
        PriceAggregateAction $aggregateAction,
    ): void {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $blizzardItemIds = array_values($this->itemMap);
        $grouped = ($extractAction)($this->filePath, $blizzardItemIds);

        $rows = [];
        foreach ($this->itemMap as $catalogItemId => $blizzardItemId) {
            $listings = $grouped[$blizzardItemId] ?? [];
            if (empty($listings)) {
                continue;
            }
            $metrics = ($aggregateAction)($listings);
            $rows[] = [
                'catalog_item_id' => $catalogItemId,
                'polled_at'       => $this->polledAt,
                'created_at'      => $this->polledAt,
                'updated_at'      => $this->polledAt,
                ...$metrics,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            PriceSnapshot::insert($chunk);
        }
    }
}
