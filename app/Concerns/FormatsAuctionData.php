<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Models\WatchedItem;

trait FormatsAuctionData
{
    public function formatGold(int $copper): string
    {
        $negative = $copper < 0;
        $copper = abs($copper);

        $g = intdiv($copper, 10000);
        $s = intdiv($copper % 10000, 100);
        $c = $copper % 100;

        $parts = [];
        if ($g > 0) {
            $parts[] = number_format($g).'g';
        }
        if ($s > 0) {
            $parts[] = $s.'s';
        }
        if ($c > 0 || $parts === []) {
            $parts[] = $c.'c';
        }

        return ($negative ? '-' : '').implode(' ', $parts);
    }

    public function rollingSignal(WatchedItem $item): array
    {
        $sevenDayQuery = $item->priceSnapshots()
            ->where('polled_at', '>=', now()->subDays(7));

        $snapshotCount = $sevenDayQuery->count();

        if ($snapshotCount < 24) {
            return ['signal' => 'insufficient_data', 'magnitude' => 0.0, 'rollingAvg' => 0];
        }

        $rollingAvg = (int) round((float) (
            $item->priceSnapshots()
                ->where('polled_at', '>=', now()->subDays(7))
                ->avg('median_price') ?? 0
        ));

        if ($rollingAvg === 0) {
            return ['signal' => 'none', 'magnitude' => 0.0, 'rollingAvg' => 0];
        }

        $snapshots = $item->catalogItem?->priceSnapshots ?? $item->priceSnapshots;
        $currentPrice = $snapshots->first()?->median_price ?? 0;

        $buyLevel = (int) round($rollingAvg * (1 - $item->buy_threshold / 100));
        $sellLevel = (int) round($rollingAvg * (1 + $item->sell_threshold / 100));

        if ($currentPrice <= $buyLevel && $currentPrice > 0) {
            $magnitude = round((($rollingAvg - $currentPrice) / $rollingAvg) * 100, 1);
            return ['signal' => 'buy', 'magnitude' => $magnitude, 'rollingAvg' => $rollingAvg];
        }

        if ($currentPrice >= $sellLevel) {
            $magnitude = round((($currentPrice - $rollingAvg) / $rollingAvg) * 100, 1);
            return ['signal' => 'sell', 'magnitude' => $magnitude, 'rollingAvg' => $rollingAvg];
        }

        return ['signal' => 'none', 'magnitude' => 0.0, 'rollingAvg' => $rollingAvg];
    }

    public function trendDirection(WatchedItem $item): string
    {
        $snapshots = $item->catalogItem?->priceSnapshots ?? $item->priceSnapshots;

        if ($snapshots->count() < 2) {
            return 'flat';
        }

        $current = $snapshots->first()->median_price;
        $previous = $snapshots->last()->median_price;

        if ($current > $previous) {
            return 'up';
        }

        if ($current < $previous) {
            return 'down';
        }

        return 'flat';
    }

    public function trendPercent(WatchedItem $item): ?float
    {
        $snapshots = $item->catalogItem?->priceSnapshots ?? $item->priceSnapshots;

        if ($snapshots->count() < 2) {
            return null;
        }

        $current = $snapshots->first()->median_price;
        $previous = $snapshots->last()->median_price;

        if ($previous === 0) {
            return null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}
