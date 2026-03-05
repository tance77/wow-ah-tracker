<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShuffleStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'shuffle_id',
        'input_blizzard_item_id',
        'output_blizzard_item_id',
        'output_qty_min',
        'output_qty_max',
        'sort_order',
    ];

    protected $casts = [
        'input_blizzard_item_id' => 'integer',
        'output_blizzard_item_id' => 'integer',
        'output_qty_min' => 'integer',
        'output_qty_max' => 'integer',
        'sort_order' => 'integer',
    ];

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
}
