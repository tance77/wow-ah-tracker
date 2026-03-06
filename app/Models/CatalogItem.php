<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CatalogItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'blizzard_item_id',
        'name',
        'category',
        'rarity',
        'icon_url',
        'quality_tier',
    ];

    protected $casts = [
        'blizzard_item_id' => 'integer',
        'quality_tier' => 'integer',
    ];

    protected $appends = ['display_name'];

    public function getDisplayNameAttribute(): string
    {
        return $this->quality_tier ? "{$this->name} (T{$this->quality_tier})" : $this->name;
    }

    public function priceSnapshots(): HasMany
    {
        return $this->hasMany(PriceSnapshot::class);
    }

    public function latestPriceSnapshot(): HasOne
    {
        return $this->hasOne(PriceSnapshot::class)->latestOfMany('polled_at');
    }

    public function rarityColorClass(): string
    {
        return match ($this->rarity) {
            'POOR' => 'text-rarity-poor',
            'COMMON' => 'text-rarity-common',
            'UNCOMMON' => 'text-rarity-uncommon',
            'RARE' => 'text-rarity-rare',
            'EPIC' => 'text-rarity-epic',
            'LEGENDARY' => 'text-rarity-legendary',
            default => 'text-gray-100',
        };
    }
}
