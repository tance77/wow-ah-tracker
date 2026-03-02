<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CatalogItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'blizzard_item_id',
        'name',
        'category',
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
}
