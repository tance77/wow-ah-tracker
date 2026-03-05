<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Recipe;

class RecipeProfitAction
{
    /**
     * Compute profit for a single recipe from live AH prices.
     *
     * IMPORTANT: Recipe must be loaded with eager relationships:
     *   reagents.catalogItem.priceSnapshots (latest 1)
     *   craftedItemSilver.priceSnapshots (latest 1)
     *   craftedItemGold.priceSnapshots (latest 1)
     *
     * All prices in copper (BIGINT). Never persisted — computed at call time.
     *
     * @return array{
     *   reagent_cost: int|null,
     *   sell_price_silver: int|null,
     *   sell_price_gold: int|null,
     *   profit_silver: int|null,
     *   profit_gold: int|null,
     *   median_profit: int|null,
     *   has_missing_prices: bool,
     * }
     */
    public function __invoke(Recipe $recipe): array
    {
        // --- Reagent cost (PROFIT-01) ---
        $reagentCost = 0;
        $hasMissingPrices = false;

        foreach ($recipe->reagents as $reagent) {
            $snapshot = $reagent->catalogItem?->priceSnapshots->first();

            if ($snapshot === null) {
                $hasMissingPrices = true;
                continue;
            }

            $reagentCost += $reagent->quantity * $snapshot->median_price;
        }

        // If any reagent has no price, reagent_cost is incomplete — signal as null
        $reagentCostFinal = $hasMissingPrices ? null : $reagentCost;

        // --- Sell prices (PROFIT-02) ---
        $sellPriceSilver = $recipe->craftedItemSilver?->priceSnapshots->first()?->median_price;
        $sellPriceGold   = $recipe->craftedItemGold?->priceSnapshots->first()?->median_price;

        if ($sellPriceSilver === null && $recipe->craftedItemSilver !== null) {
            $hasMissingPrices = true;
        }

        if ($sellPriceGold === null && $recipe->craftedItemGold !== null) {
            $hasMissingPrices = true;
        }

        // --- Per-tier profit (PROFIT-03): (sell_price * 0.95) - reagent_cost ---
        $profitSilver = null;
        $profitGold   = null;

        if ($sellPriceSilver !== null && $reagentCostFinal !== null) {
            $profitSilver = (int) round($sellPriceSilver * 0.95) - $reagentCostFinal;
        }

        if ($sellPriceGold !== null && $reagentCostFinal !== null) {
            $profitGold = (int) round($sellPriceGold * 0.95) - $reagentCostFinal;
        }

        // --- Median profit across tiers (PROFIT-04) ---
        $profits = array_filter([$profitSilver, $profitGold], fn ($p) => $p !== null);
        $medianProfit = match (count($profits)) {
            2       => (int) round(array_sum($profits) / 2),
            1       => (int) reset($profits),
            default => null,
        };

        return [
            'reagent_cost'       => $reagentCostFinal,
            'sell_price_silver'  => $sellPriceSilver,
            'sell_price_gold'    => $sellPriceGold,
            'profit_silver'      => $profitSilver,
            'profit_gold'        => $profitGold,
            'median_profit'      => $medianProfit,
            'has_missing_prices' => $hasMissingPrices,
        ];
    }
}
