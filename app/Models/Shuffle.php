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
        // Start with 1 unit input and cascade through each step
        $outputQty = 1;
        foreach ($steps as $step) {
            $outputQty = (int) floor($outputQty * $step->output_qty_min / max(1, $step->input_qty));
        }

        $grossOutput = $outputPrice * $outputQty;
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
                ->whereNotIn('id', function ($query) use ($shuffle) {
                    $query->select('watched_items.id')
                        ->from('watched_items')
                        ->join('shuffle_steps as ss', function ($join) {
                            $join->on('watched_items.blizzard_item_id', '=', 'ss.input_blizzard_item_id')
                                ->orOn('watched_items.blizzard_item_id', '=', 'ss.output_blizzard_item_id');
                        })
                        ->join('shuffles', 'ss.shuffle_id', '=', 'shuffles.id')
                        ->where('shuffles.id', '!=', $shuffle->id);
                })
                ->pluck('id');

            if ($orphanIds->isNotEmpty()) {
                WatchedItem::whereIn('id', $orphanIds)->delete();
            }
        });
    }
}
