<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class WatchedItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'blizzard_item_id',
        'name',
        'buy_threshold',
        'sell_threshold',
    ];

    protected $casts = [
        'blizzard_item_id' => 'integer',
        'buy_threshold' => 'integer',
        'sell_threshold' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class, 'blizzard_item_id', 'blizzard_item_id');
    }

    public function priceSnapshots(): HasManyThrough
    {
        return $this->hasManyThrough(
            PriceSnapshot::class,
            CatalogItem::class,
            'blizzard_item_id', // FK on catalog_items
            'catalog_item_id',  // FK on price_snapshots
            'blizzard_item_id', // local key on watched_items
            'id',               // local key on catalog_items
        );
    }
}
