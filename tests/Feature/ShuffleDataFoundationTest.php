<?php

declare(strict_types=1);

use App\Models\CatalogItem;
use App\Models\Shuffle;
use App\Models\ShuffleStep;
use App\Models\User;
use App\Models\WatchedItem;

uses()->group('shuffle-data-foundation');

// Factory tests

test('factory creates valid shuffle record', function () {
    $shuffle = Shuffle::factory()->create();

    expect($shuffle->id)->not->toBeNull()
        ->and($shuffle->name)->not->toBeEmpty()
        ->and($shuffle->user_id)->not->toBeNull();
});

test('factory creates valid shuffle step record', function () {
    $step = ShuffleStep::factory()->create();

    expect($step->id)->not->toBeNull()
        ->and($step->shuffle_id)->not->toBeNull()
        ->and($step->input_blizzard_item_id)->not->toBeNull()
        ->and($step->output_blizzard_item_id)->not->toBeNull();
});

// Relationship tests

test('user has many shuffles', function () {
    $user = User::factory()->create();
    Shuffle::factory()->count(3)->create(['user_id' => $user->id]);

    expect($user->shuffles()->count())->toBe(3);
});

test('shuffle belongs to user', function () {
    $user = User::factory()->create();
    $shuffle = Shuffle::factory()->create(['user_id' => $user->id]);

    expect($shuffle->user->id)->toBe($user->id);
});

test('shuffle steps are ordered by sort_order', function () {
    $shuffle = Shuffle::factory()->create();

    ShuffleStep::factory()->create(['shuffle_id' => $shuffle->id, 'sort_order' => 2]);
    ShuffleStep::factory()->create(['shuffle_id' => $shuffle->id, 'sort_order' => 0]);
    ShuffleStep::factory()->create(['shuffle_id' => $shuffle->id, 'sort_order' => 1]);

    $sortOrders = $shuffle->steps()->pluck('sort_order')->toArray();

    expect($sortOrders)->toBe([0, 1, 2]);
});

test('shuffle step has input and output catalog item relationships', function () {
    $inputItem = CatalogItem::factory()->create(['blizzard_item_id' => 111111]);
    $outputItem = CatalogItem::factory()->create(['blizzard_item_id' => 222222]);

    $step = ShuffleStep::factory()->create([
        'input_blizzard_item_id' => 111111,
        'output_blizzard_item_id' => 222222,
    ]);

    expect($step->inputCatalogItem->id)->toBe($inputItem->id)
        ->and($step->outputCatalogItem->id)->toBe($outputItem->id);
});

// Cascade delete tests

test('deleting a shuffle cascade-deletes all its steps', function () {
    $shuffle = Shuffle::factory()->create();
    ShuffleStep::factory()->count(3)->create(['shuffle_id' => $shuffle->id]);

    expect(ShuffleStep::where('shuffle_id', $shuffle->id)->count())->toBe(3);

    $shuffle->delete();

    expect(ShuffleStep::where('shuffle_id', $shuffle->id)->count())->toBe(0);
});

// Orphan cleanup tests

test('deleting a shuffle removes orphan auto-watched items', function () {
    $user = User::factory()->create();
    $shuffle = Shuffle::factory()->create(['user_id' => $user->id]);
    ShuffleStep::factory()->create([
        'shuffle_id' => $shuffle->id,
        'input_blizzard_item_id' => 155001,
        'output_blizzard_item_id' => 155002,
    ]);

    // Auto-watched item created by this shuffle
    WatchedItem::factory()->create([
        'user_id' => $user->id,
        'blizzard_item_id' => 155001,
        'created_by_shuffle_id' => $shuffle->id,
    ]);

    expect(WatchedItem::where('blizzard_item_id', 155001)->count())->toBe(1);

    $shuffle->delete();

    expect(WatchedItem::where('blizzard_item_id', 155001)->count())->toBe(0);
});

test('deleting a shuffle preserves auto-watched items referenced by other shuffles', function () {
    $user = User::factory()->create();

    $shuffleA = Shuffle::factory()->create(['user_id' => $user->id]);
    ShuffleStep::factory()->create([
        'shuffle_id' => $shuffleA->id,
        'input_blizzard_item_id' => 166001,
        'output_blizzard_item_id' => 166002,
    ]);

    $shuffleB = Shuffle::factory()->create(['user_id' => $user->id]);
    ShuffleStep::factory()->create([
        'shuffle_id' => $shuffleB->id,
        'input_blizzard_item_id' => 166001,
        'output_blizzard_item_id' => 166003,
    ]);

    // Auto-watched item created by shuffleA, but shuffleB also references the same item
    WatchedItem::factory()->create([
        'user_id' => $user->id,
        'blizzard_item_id' => 166001,
        'created_by_shuffle_id' => $shuffleA->id,
    ]);

    $shuffleA->delete();

    // Item still referenced by shuffleB — should be preserved
    expect(WatchedItem::where('blizzard_item_id', 166001)->count())->toBe(1);
});

test('deleting a shuffle preserves manually-watched items', function () {
    $user = User::factory()->create();
    $shuffle = Shuffle::factory()->create(['user_id' => $user->id]);
    ShuffleStep::factory()->create([
        'shuffle_id' => $shuffle->id,
        'input_blizzard_item_id' => 177001,
        'output_blizzard_item_id' => 177002,
    ]);

    // Manually-watched item — no created_by_shuffle_id
    WatchedItem::factory()->create([
        'user_id' => $user->id,
        'blizzard_item_id' => 177001,
        'created_by_shuffle_id' => null,
    ]);

    $shuffle->delete();

    // Manual watch should survive
    expect(WatchedItem::where('blizzard_item_id', 177001)->count())->toBe(1);
});
