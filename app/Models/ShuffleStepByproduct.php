<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShuffleStepByproduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'shuffle_step_id',
        'blizzard_item_id',
        'item_name',
        'chance_percent',
        'quantity',
    ];

    protected $casts = [
        'blizzard_item_id' => 'integer',
        'chance_percent' => 'decimal:2',
        'quantity' => 'integer',
    ];

    public function step(): BelongsTo
    {
        return $this->belongsTo(ShuffleStep::class, 'shuffle_step_id');
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class, 'blizzard_item_id', 'blizzard_item_id');
    }
}
