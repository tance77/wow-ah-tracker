<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Recipe extends Model
{
    use HasFactory;

    protected $fillable = [
        'profession_id',
        'blizzard_recipe_id',
        'name',
        'crafted_item_id_silver',
        'crafted_item_id_gold',
        'crafted_quantity',
        'is_commodity',
        'last_synced_at',
    ];

    protected $casts = [
        'blizzard_recipe_id' => 'integer',
        'crafted_quantity' => 'integer',
        'is_commodity' => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    public function profession(): BelongsTo
    {
        return $this->belongsTo(Profession::class);
    }

    public function reagents(): HasMany
    {
        return $this->hasMany(RecipeReagent::class);
    }

    public function craftedItemSilver(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class, 'crafted_item_id_silver');
    }

    public function craftedItemGold(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class, 'crafted_item_id_gold');
    }
}
