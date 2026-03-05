<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShuffleStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'shuffle_id',
        'input_blizzard_item_id',
        'output_blizzard_item_id',
        'input_qty',
        'output_qty_min',
        'output_qty_max',
        'sort_order',
    ];

    protected $casts = [
        'input_blizzard_item_id' => 'integer',
        'output_blizzard_item_id' => 'integer',
        'input_qty' => 'integer',
        'output_qty_min' => 'integer',
        'output_qty_max' => 'integer',
        'sort_order' => 'integer',
    ];

    protected static function boot(): void
    {
        parent::boot();

        // Orphan cleanup: after a step is deleted, remove auto-watched items
        // that are no longer referenced by any remaining step in any shuffle.
        // Uses 'deleted' (after delete) so the step is already gone from DB,
        // meaning ShuffleStep::where(...)->exists() returns false for truly orphaned items.
        // Only auto-watched items (created_by_shuffle_id IS NOT NULL) are eligible for removal;
        // manually-added watched items are preserved.
        static::deleted(function (ShuffleStep $step): void {
            // Collect all item IDs from this step: input, output, and byproducts
            $byproductItemIds = $step->byproducts->pluck('blizzard_item_id')->all();
            $allItemIds = array_unique(array_merge(
                [$step->input_blizzard_item_id, $step->output_blizzard_item_id],
                $byproductItemIds,
            ));

            foreach ($allItemIds as $blizzardItemId) {
                $stillReferenced = ShuffleStep::where('input_blizzard_item_id', $blizzardItemId)
                    ->orWhere('output_blizzard_item_id', $blizzardItemId)
                    ->exists();

                if (! $stillReferenced) {
                    // Also check if any other byproduct still references this item
                    $stillReferencedByByproduct = ShuffleStepByproduct::where('blizzard_item_id', $blizzardItemId)->exists();

                    if (! $stillReferencedByByproduct) {
                        WatchedItem::where('blizzard_item_id', $blizzardItemId)
                            ->whereNotNull('created_by_shuffle_id')
                            ->delete();
                    }
                }
            }
        });
    }

    public function shuffle(): BelongsTo
    {
        return $this->belongsTo(Shuffle::class);
    }

    public function inputCatalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class, 'input_blizzard_item_id', 'blizzard_item_id');
    }

    public function outputCatalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class, 'output_blizzard_item_id', 'blizzard_item_id');
    }

    public function byproducts(): HasMany
    {
        return $this->hasMany(ShuffleStepByproduct::class);
    }
}
