<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shuffle extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ShuffleStep::class)->orderBy('sort_order');
    }

    public function profitPerUnit(): ?int
    {
        $steps = $this->steps()->with([
            'inputCatalogItem.priceSnapshots' => fn ($q) => $q->latest('polled_at')->limit(1),
            'outputCatalogItem.priceSnapshots' => fn ($q) => $q->latest('polled_at')->limit(1),
            'byproducts.catalogItem.priceSnapshots' => fn ($q) => $q->latest('polled_at')->limit(1),
        ])->get();

        if ($steps->isEmpty()) {
            return null;
        }

        $firstInputPrice = $steps->first()->inputCatalogItem?->priceSnapshots->first()?->median_price;
        if ($firstInputPrice === null) {
            return null;
        }

        $lastStep = $steps->last();
        $outputPrice = $lastStep->outputCatalogItem?->priceSnapshots->first()?->median_price;
        if ($outputPrice === null) {
            return null;
        }

        // Cascade yield ratios through all steps using conservative min yield
        // Start with 1 unit input and cascade through each step.
        // Track cascaded qty at each step for byproduct EV calculation.
        $cascadedQty = 1;
        $byproductEV = 0;

        foreach ($steps as $step) {
            // Calculate byproduct EV for this step based on input batches
            $batches = (int) floor($cascadedQty / max(1, $step->input_qty));
            foreach ($step->byproducts as $bp) {
                $bpPrice = $bp->catalogItem?->priceSnapshots->first()?->median_price;
                if ($bpPrice !== null) {
                    $byproductEV += $bpPrice * ((float) $bp->chance_percent / 100) * $bp->quantity * $batches;
                }
            }

            $cascadedQty = (int) floor($cascadedQty * $step->output_qty_min / max(1, $step->input_qty));
        }

        $grossOutput = ($outputPrice * $cascadedQty) + $byproductEV;
        $netOutput = (int) round($grossOutput * 0.95); // 5% AH cut

        return $netOutput - $firstInputPrice;
    }

    protected static function boot(): void
    {
        parent::boot();

        static::deleting(function (Shuffle $shuffle): void {
            // Orphan cleanup: remove auto-watched items that were created by
            // this shuffle and are not referenced by any other shuffle.
            // Uses 'deleting' (before delete) so steps still exist in DB for the check.
            $orphanIds = WatchedItem::where('created_by_shuffle_id', $shuffle->id)
                ->where('user_id', $shuffle->user_id)
                ->whereNotIn('id', function ($query) use ($shuffle) {
                    // Still referenced by another of THIS USER's shuffle's step input/output
                    $query->select('watched_items.id')
                        ->from('watched_items')
                        ->join('shuffle_steps as ss', function ($join) {
                            $join->on('watched_items.blizzard_item_id', '=', 'ss.input_blizzard_item_id')
                                ->orOn('watched_items.blizzard_item_id', '=', 'ss.output_blizzard_item_id');
                        })
                        ->join('shuffles', 'ss.shuffle_id', '=', 'shuffles.id')
                        ->where('shuffles.id', '!=', $shuffle->id)
                        ->where('shuffles.user_id', $shuffle->user_id);
                })
                ->whereNotIn('id', function ($query) use ($shuffle) {
                    // Still referenced by another of THIS USER's shuffle's step byproduct
                    $query->select('watched_items.id')
                        ->from('watched_items')
                        ->join('shuffle_step_byproducts as ssb', 'watched_items.blizzard_item_id', '=', 'ssb.blizzard_item_id')
                        ->join('shuffle_steps as ss2', 'ssb.shuffle_step_id', '=', 'ss2.id')
                        ->join('shuffles as s2', 'ss2.shuffle_id', '=', 's2.id')
                        ->where('s2.id', '!=', $shuffle->id)
                        ->where('s2.user_id', $shuffle->user_id);
                })
                ->pluck('id');

            if ($orphanIds->isNotEmpty()) {
                WatchedItem::whereIn('id', $orphanIds)->delete();
            }
        });
    }
}
