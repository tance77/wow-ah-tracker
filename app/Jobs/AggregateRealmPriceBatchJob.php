<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\PriceAggregateAction;
use App\Models\PriceSnapshot;
use Carbon\CarbonInterface;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class AggregateRealmPriceBatchJob implements ShouldQueue
{
    use Batchable, Queueable;

    /**
     * @param  array<int, int>  $itemMap  [catalog_item_id => blizzard_item_id]
     * @param  array<int, array<array{unit_price: int, quantity: int}>>  $preExtractedListings  Listings grouped by blizzard_item_id
     * @param  CarbonInterface  $polledAt
     */
    public function __construct(
        public readonly array $itemMap,
        public readonly array $preExtractedListings,
        public readonly CarbonInterface $polledAt,
    ) {}

    /**
     * Aggregate pre-extracted listings and write price snapshots.
     */
    public function handle(
        PriceAggregateAction $aggregateAction,
    ): void {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $rows = [];
        foreach ($this->itemMap as $catalogItemId => $blizzardItemId) {
            $listings = $this->preExtractedListings[$blizzardItemId] ?? [];
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
