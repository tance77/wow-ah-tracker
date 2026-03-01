<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CatalogItem;
use Illuminate\Database\Seeder;

class ItemCatalogSeeder extends Seeder
{
    // Item IDs are TWW-era placeholders — verify against live Blizzard API in Phase 4
    public function run(): void
    {
        $items = [
            // Herbs
            ['blizzard_item_id' => 222788, 'name' => 'Luredrop', 'category' => 'herb'],
            ['blizzard_item_id' => 222785, 'name' => 'Orbinid', 'category' => 'herb'],
            ['blizzard_item_id' => 222790, 'name' => 'Blessing Blossom', 'category' => 'herb'],
            ['blizzard_item_id' => 222789, 'name' => 'Gundegrass', 'category' => 'herb'],
            ['blizzard_item_id' => 222791, 'name' => "Arathor's Spear", 'category' => 'herb'],
            ['blizzard_item_id' => 222792, 'name' => 'Ironcap Mushroom', 'category' => 'herb'],

            // Ores
            ['blizzard_item_id' => 224023, 'name' => 'Uldricite', 'category' => 'ore'],
            ['blizzard_item_id' => 224025, 'name' => 'Bismuth', 'category' => 'ore'],
            ['blizzard_item_id' => 224024, 'name' => 'Ironcite', 'category' => 'ore'],

            // Cloth
            ['blizzard_item_id' => 224570, 'name' => 'Weavercloth', 'category' => 'cloth'],
            ['blizzard_item_id' => 224569, 'name' => 'Dawnweave', 'category' => 'cloth'],

            // Leather
            ['blizzard_item_id' => 224552, 'name' => 'Amplified Monstrous Hide', 'category' => 'leather'],
            ['blizzard_item_id' => 224551, 'name' => 'Monstrous Hide', 'category' => 'leather'],

            // Enchanting
            ['blizzard_item_id' => 222825, 'name' => 'Resonant Crystal', 'category' => 'enchanting'],
            ['blizzard_item_id' => 222826, 'name' => 'Spark of Omens', 'category' => 'enchanting'],
            ['blizzard_item_id' => 222823, 'name' => 'Glittering Parchment', 'category' => 'enchanting'],

            // Gems
            ['blizzard_item_id' => 220156, 'name' => 'Irradiated Taaffeite', 'category' => 'gem'],
            ['blizzard_item_id' => 220153, 'name' => 'Irradiated Ruby', 'category' => 'gem'],
            ['blizzard_item_id' => 220155, 'name' => 'Irradiated Emerald', 'category' => 'gem'],
        ];

        foreach ($items as $item) {
            CatalogItem::updateOrCreate(
                ['blizzard_item_id' => $item['blizzard_item_id']],
                ['name' => $item['name'], 'category' => $item['category']]
            );
        }
    }
}
