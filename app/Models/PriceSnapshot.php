<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'catalog_item_id',
        'min_price',
        'avg_price',
        'median_price',
        'total_volume',
        'polled_at',
    ];

    protected $casts = [
        'min_price' => 'integer',
        'avg_price' => 'integer',
        'median_price' => 'integer',
        'total_volume' => 'integer',
        'polled_at' => 'datetime',
    ];

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class);
    }
}
