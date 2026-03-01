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
    ];

    protected $casts = [
        'blizzard_item_id' => 'integer',
    ];
}
