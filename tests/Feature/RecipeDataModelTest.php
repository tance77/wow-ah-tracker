<?php

declare(strict_types=1);

use App\Models\CatalogItem;
use App\Models\Profession;
use App\Models\Recipe;
use App\Models\RecipeReagent;

uses()->group('recipe-data-model');

// Test 1: Tables exist after migration
test('migrations create professions, recipes, and recipe_reagents tables', function () {
    expect(\Illuminate\Support\Facades\Schema::hasTable('professions'))->toBeTrue()
        ->and(\Illuminate\Support\Facades\Schema::hasTable('recipes'))->toBeTrue()
        ->and(\Illuminate\Support\Facades\Schema::hasTable('recipe_reagents'))->toBeTrue();
});

// Test 2: Factories create valid rows
test('profession factory creates valid row', function () {
    $profession = Profession::factory()->create();

    expect($profession->id)->not->toBeNull()
        ->and($profession->blizzard_profession_id)->not->toBeNull()
        ->and($profession->name)->not->toBeEmpty();
});

test('recipe factory creates valid row with profession FK', function () {
    $recipe = Recipe::factory()->create();

    expect($recipe->id)->not->toBeNull()
        ->and($recipe->profession_id)->not->toBeNull()
        ->and($recipe->blizzard_recipe_id)->not->toBeNull()
        ->and($recipe->name)->not->toBeEmpty();
});

// Test 3: Profession->recipes() returns related recipes
test('profession has many recipes', function () {
    $profession = Profession::factory()->create();
    Recipe::factory()->count(3)->create(['profession_id' => $profession->id]);

    expect($profession->recipes()->count())->toBe(3);
});

// Test 4: Recipe->profession() returns parent profession
test('recipe belongs to profession', function () {
    $profession = Profession::factory()->create();
    $recipe = Recipe::factory()->create(['profession_id' => $profession->id]);

    expect($recipe->profession->id)->toBe($profession->id);
});

// Test 5: Recipe->reagents() returns related RecipeReagent rows
test('recipe has many reagents', function () {
    $recipe = Recipe::factory()->create();
    RecipeReagent::factory()->count(3)->create(['recipe_id' => $recipe->id]);

    expect($recipe->reagents()->count())->toBe(3);
});

// Test 6: Recipe->craftedItemSilver() and ->craftedItemGold() return nullable CatalogItem
test('recipe crafted item silver and gold relationships return nullable catalog item', function () {
    $catalogItemSilver = CatalogItem::factory()->create();
    $catalogItemGold = CatalogItem::factory()->create();

    $recipe = Recipe::factory()->create([
        'crafted_item_id_silver' => $catalogItemSilver->id,
        'crafted_item_id_gold' => $catalogItemGold->id,
    ]);

    expect($recipe->craftedItemSilver->id)->toBe($catalogItemSilver->id)
        ->and($recipe->craftedItemGold->id)->toBe($catalogItemGold->id);
});

test('recipe crafted item silver and gold can be null', function () {
    $recipe = Recipe::factory()->create([
        'crafted_item_id_silver' => null,
        'crafted_item_id_gold' => null,
    ]);

    expect($recipe->craftedItemSilver)->toBeNull()
        ->and($recipe->craftedItemGold)->toBeNull();
});

// Test 7: RecipeReagent->catalogItem() returns CatalogItem
test('recipe reagent belongs to catalog item', function () {
    $catalogItem = CatalogItem::factory()->create();
    $reagent = RecipeReagent::factory()->create(['catalog_item_id' => $catalogItem->id]);

    expect($reagent->catalogItem->id)->toBe($catalogItem->id);
});

// Test 8: Deleting a profession cascades to recipes and recipe_reagents
test('deleting a profession cascades to its recipes and recipe_reagents', function () {
    $profession = Profession::factory()->create();
    $recipe = Recipe::factory()->create(['profession_id' => $profession->id]);
    RecipeReagent::factory()->count(2)->create(['recipe_id' => $recipe->id]);

    expect(Recipe::where('profession_id', $profession->id)->count())->toBe(1)
        ->and(RecipeReagent::where('recipe_id', $recipe->id)->count())->toBe(2);

    $profession->delete();

    expect(Recipe::where('profession_id', $profession->id)->count())->toBe(0)
        ->and(RecipeReagent::where('recipe_id', $recipe->id)->count())->toBe(0);
});

// Test 9: recipes.last_synced_at is fillable and castable as datetime
test('recipe last_synced_at is fillable and cast as datetime', function () {
    $now = now();
    $recipe = Recipe::factory()->create(['last_synced_at' => $now]);

    expect($recipe->last_synced_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class)
        ->and($recipe->last_synced_at->timestamp)->toBe($now->timestamp);
});
