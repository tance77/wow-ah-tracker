<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Profession extends Model
{
    use HasFactory;

    protected $fillable = [
        'blizzard_profession_id',
        'name',
        'icon_url',
        'last_synced_at',
    ];

    protected $casts = [
        'blizzard_profession_id' => 'integer',
        'last_synced_at' => 'datetime',
    ];

    public function recipes(): HasMany
    {
        return $this->hasMany(Recipe::class);
    }
}
