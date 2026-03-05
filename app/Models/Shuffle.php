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

        // Apply yield from last step (use min qty for conservative estimate)
        $outputQty = $lastStep->output_qty_min ?? 1;
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
            WatchedItem::where('created_by_shuffle_id', $shuffle->id)
                ->whereNotExists(function ($query) use ($shuffle) {
                    $query->select('wi2.id')
                        ->from('watched_items as wi2')
                        ->join('shuffle_steps as ss', function ($join) {
                            $join->on('wi2.blizzard_item_id', '=', 'ss.input_blizzard_item_id')
                                ->orOn('wi2.blizzard_item_id', '=', 'ss.output_blizzard_item_id');
                        })
                        ->join('shuffles', 'ss.shuffle_id', '=', 'shuffles.id')
                        ->where('shuffles.id', '!=', $shuffle->id)
                        ->whereColumn('wi2.id', 'watched_items.id');
                })
                ->delete();
        });
    }
}
