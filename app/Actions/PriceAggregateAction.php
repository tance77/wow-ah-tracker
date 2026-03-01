<?php

declare(strict_types=1);

namespace App\Actions;

class PriceAggregateAction
{
    /**
     * Compute price metrics from Blizzard commodity listing data.
     *
     * @param  array<int, array{unit_price: int, quantity: int}>  $listings  Listings for a single item
     * @return array{min_price: int, avg_price: int, median_price: int, total_volume: int}
     */
    public function __invoke(array $listings): array
    {
        if (empty($listings)) {
            return [
                'min_price'    => 0,
                'avg_price'    => 0,
                'median_price' => 0,
                'total_volume' => 0,
            ];
        }

        $totalVolume = 0;
        $totalValue = 0;
        $minPrice = PHP_INT_MAX;

        foreach ($listings as $listing) {
            $price = $listing['unit_price'];
            $quantity = $listing['quantity'];
            $totalVolume += $quantity;
            $totalValue += $price * $quantity;
            if ($price < $minPrice) {
                $minPrice = $price;
            }
        }

        return [
            'min_price'    => $minPrice,
            'avg_price'    => (int) round($totalValue / $totalVolume),
            'median_price' => $this->computeMedian($listings, $totalVolume),
            'total_volume' => $totalVolume,
        ];
    }

    /**
     * Compute frequency-distribution median by cumulative quantity traversal.
     *
     * @param  array<int, array{unit_price: int, quantity: int}>  $listings
     */
    private function computeMedian(array $listings, int $totalVolume): int
    {
        usort($listings, fn (array $a, array $b): int => $a['unit_price'] <=> $b['unit_price']);

        $medianPosition = (int) ceil($totalVolume / 2);
        $cumulative = 0;

        foreach ($listings as $listing) {
            $cumulative += $listing['quantity'];
            if ($cumulative >= $medianPosition) {
                return $listing['unit_price'];
            }
        }

        // Fallback — should not be reached with non-empty input
        return end($listings)['unit_price'];
    }
}
