<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\PriceSnapshot;
use App\Models\WatchedItem;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ItemCatalogSeeder::class);

        // Create 5 sample watched items with 20 price snapshots each
        WatchedItem::factory()
            ->count(5)
            ->has(PriceSnapshot::factory()->count(20))
            ->create();
    }
}
