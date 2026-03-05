<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Profession extends Model
{
    use HasFactory;

    protected $fillable = [
        'blizzard_profession_id',
        'name',
        'slug',
        'icon_url',
        'last_synced_at',
    ];

    protected $casts = [
        'blizzard_profession_id' => 'integer',
        'last_synced_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(fn (Profession $p) => $p->slug = $p->slug ?? Str::slug($p->name));
        static::updating(fn (Profession $p) => $p->slug = Str::slug($p->name));
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function recipes(): HasMany
    {
        return $this->hasMany(Recipe::class);
    }
}
