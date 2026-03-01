<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IngestionMetadata extends Model
{
    protected $fillable = [
        'last_modified_at',
        'response_hash',
        'last_fetched_at',
        'consecutive_failures',
    ];

    protected $casts = [
        'last_fetched_at'      => 'datetime',
        'consecutive_failures' => 'integer',
    ];

    /**
     * Return the single global metadata row, creating it on first access.
     */
    public static function singleton(): self
    {
        return self::firstOrCreate(['id' => 1]);
    }
}
