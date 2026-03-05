<?php

declare(strict_types=1);

use App\Models\Shuffle;
use App\Models\ShuffleStep;
use App\Models\User;
use App\Models\WatchedItem;
use Illuminate\Testing\Fluent\AssertableJson;
use Livewire\Volt\Volt;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class)->group('shuffle-step-editor');

// ---------------------------------------------------------------------------
// SHUF-02: User can define multi-step conversion chains (A -> B -> C)
// ---------------------------------------------------------------------------

test('can add a step to a shuffle', function () {
    $user = User::factory()->create();
    $shuffle = Shuffle::factory()->create(['user_id' => $user->id]);

    Volt::actingAs($user)->test('pages.shuffle-detail', ['shuffle' => $shuffle])
        ->call('addStep', 100001, 100002, 1, 1, 1);

    expect(ShuffleStep::where('shuffle_id', $shuffle->id)->count())->toBe(1);

    $step = ShuffleStep::where('shuffle_id', $shuffle->id)->first();
    expect($step->input_blizzard_item_id)->toBe(100001)
        ->and($step->output_blizzard_item_id)->toBe(100002)
        ->and($step->input_qty)->toBe(1)
        ->and($step->output_qty_min)->toBe(1)
        ->and($step->output_qty_max)->toBe(1)
        ->and($step->sort_order)->toBe(0);
});

test('can add multiple steps forming a chain with sequential sort_order', function () {
    $user = User::factory()->create();
    $shuffle = Shuffle::factory()->create(['user_id' => $user->id]);

    Volt::actingAs($user)->test('pages.shuffle-detail', ['shuffle' => $shuffle])
        ->call('addStep', 200001, 200002, 1, 1, 1)
        ->call('addStep', 200002, 200003, 1, 1, 1);

    expect(ShuffleStep::where('shuffle_id', $shuffle->id)->count())->toBe(2);

    $steps = ShuffleStep::where('shuffle_id', $shuffle->id)->orderBy('sort_order')->get();
    expect($steps[0]->input_blizzard_item_id)->toBe(200001)
        ->and($steps[0]->sort_order)->toBe(0)
        ->and($steps[1]->input_blizzard_item_id)->toBe(200002)
        ->and($steps[1]->sort_order)->toBe(1);
});

test('cannot add a step to another users shuffle', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $shuffle = Shuffle::factory()->create(['user_id' => $otherUser->id]);

    Volt::actingAs($user)->test('pages.shuffle-detail', ['shuffle' => $shuffle])
        ->call('addStep', 300001, 300002, 1, 1, 1)
        ->assertForbidden();
});

// ---------------------------------------------------------------------------
// YILD-01: User can set a fixed yield ratio per conversion step
// ---------------------------------------------------------------------------

test('can save fixed yield with input_qty', function () {
    $user = User::factory()->create();
    $shuffle = Shuffle::factory()->create(['user_id' => $user->id]);
    $step = ShuffleStep::factory()->create([
        'shuffle_id' => $shuffle->id,
        'input_qty' => 1,
        'output_qty_min' => 1,
        'output_qty_max' => 1,
    ]);

    Volt::actingAs($user)->test('pages.shuffle-detail', ['shuffle' => $shuffle])
        ->call('saveStep', $step->id, 5, 1, 1);

    $step->refresh();
    expect($step->input_qty)->toBe(5)
        ->and($step->output_qty_min)->toBe(1)
        ->and($step->output_qty_max)->toBe(1);
});

test('input_qty defaults to 1 when not specified on addStep', function () {
    $user = User::factory()->create();
    $shuffle = Shuffle::factory()->create(['user_id' => $user->id]);

    Volt::actingAs($user)->test('pages.shuffle-detail', ['shuffle' => $shuffle])
        ->call('addStep', 400001, 400002, 1, 1, 1);

    $step = ShuffleStep::where('shuffle_id', $shuffle->id)->first();
    expect($step->input_qty)->toBe(1);
});

// ---------------------------------------------------------------------------
// YILD-02: User can set min/max yield range per step
// ---------------------------------------------------------------------------

test('can save yield range where min differs from max', function () {
    $user = User::factory()->create();
    $shuffle = Shuffle::factory()->create(['user_id' => $user->id]);
    $step = ShuffleStep::factory()->create([
        'shuffle_id' => $shuffle->id,
        'output_qty_min' => 1,
        'output_qty_max' => 1,
    ]);

    Volt::actingAs($user)->test('pages.shuffle-detail', ['shuffle' => $shuffle])
        ->call('saveStep', $step->id, 1, 1, 3);

    $step->refresh();
    expect($step->output_qty_min)->toBe(1)
        ->and($step->output_qty_max)->toBe(3);
});

test('rejects invalid yield where max is less than min', function () {
    $user = User::factory()->create();
    $shuffle = Shuffle::factory()->create(['user_id' => $user->id]);
    $step = ShuffleStep::factory()->create([
        'shuffle_id' => $shuffle->id,
        'output_qty_min' => 2,
        'output_qty_max' => 2,
    ]);

    Volt::actingAs($user)->test('pages.shuffle-detail', ['shuffle' => $shuffle])
        ->call('saveStep', $step->id, 1, 3, 1)
        ->assertHasErrors();
});

test('rejects invalid yield where min is less than 1', function () {
    $user = User::factory()->create();
    $shuffle = Shuffle::factory()->create(['user_id' => $user->id]);
    $step = ShuffleStep::factory()->create([
        'shuffle_id' => $shuffle->id,
        'output_qty_min' => 1,
        'output_qty_max' => 1,
    ]);

    Volt::actingAs($user)->test('pages.shuffle-detail', ['shuffle' => $shuffle])
        ->call('saveStep', $step->id, 1, 0, 1)
        ->assertHasErrors();
});

// ---------------------------------------------------------------------------
// YILD-03: User can reorder steps and delete/renumber
// ---------------------------------------------------------------------------

test('can move a step up by swapping sort_order with previous step', function () {
    $user = User::factory()->create();
    $shuffle = Shuffle::factory()->create(['user_id' => $user->id]);
    $step1 = ShuffleStep::factory()->create(['shuffle_id' => $shuffle->id, 'sort_order' => 0]);
    $step2 = ShuffleStep::factory()->create(['shuffle_id' => $shuffle->id, 'sort_order' => 1]);

    Volt::actingAs($user)->test('pages.shuffle-detail', ['shuffle' => $shuffle])
        ->call('moveStepUp', $step2->id);

    expect($step2->fresh()->sort_order)->toBe(0)
        ->and($step1->fresh()->sort_order)->toBe(1);
});

test('can move a step down by swapping sort_order with next step', function () {
    $user = User::factory()->create();
    $shuffle = Shuffle::factory()->create(['user_id' => $user->id]);
    $step1 = ShuffleStep::factory()->create(['shuffle_id' => $shuffle->id, 'sort_order' => 0]);
    $step2 = ShuffleStep::factory()->create(['shuffle_id' => $shuffle->id, 'sort_order' => 1]);

    Volt::actingAs($user)->test('pages.shuffle-detail', ['shuffle' => $shuffle])
        ->call('moveStepDown', $step1->id);

    expect($step1->fresh()->sort_order)->toBe(1)
        ->and($step2->fresh()->sort_order)->toBe(0);
});

test('move up on first step is a no-op', function () {
    $user = User::factory()->create();
    $shuffle = Shuffle::factory()->create(['user_id' => $user->id]);
    $step = ShuffleStep::factory()->create(['shuffle_id' => $shuffle->id, 'sort_order' => 0]);

    Volt::actingAs($user)->test('pages.shuffle-detail', ['shuffle' => $shuffle])
        ->call('moveStepUp', $step->id);

    expect($step->fresh()->sort_order)->toBe(0);
});

test('move down on last step is a no-op', function () {
    $user = User::factory()->create();
    $shuffle = Shuffle::factory()->create(['user_id' => $user->id]);
    $step = ShuffleStep::factory()->create(['shuffle_id' => $shuffle->id, 'sort_order' => 0]);

    Volt::actingAs($user)->test('pages.shuffle-detail', ['shuffle' => $shuffle])
        ->call('moveStepDown', $step->id);

    expect($step->fresh()->sort_order)->toBe(0);
});

test('deleting a step renumbers remaining steps contiguously', function () {
    $user = User::factory()->create();
    $shuffle = Shuffle::factory()->create(['user_id' => $user->id]);
    $step1 = ShuffleStep::factory()->create(['shuffle_id' => $shuffle->id, 'sort_order' => 0, 'input_blizzard_item_id' => 500001, 'output_blizzard_item_id' => 500002]);
    $step2 = ShuffleStep::factory()->create(['shuffle_id' => $shuffle->id, 'sort_order' => 1, 'input_blizzard_item_id' => 500003, 'output_blizzard_item_id' => 500004]);
    $step3 = ShuffleStep::factory()->create(['shuffle_id' => $shuffle->id, 'sort_order' => 2, 'input_blizzard_item_id' => 500005, 'output_blizzard_item_id' => 500006]);

    Volt::actingAs($user)->test('pages.shuffle-detail', ['shuffle' => $shuffle])
        ->call('deleteStep', $step1->id);

    $remaining = ShuffleStep::where('shuffle_id', $shuffle->id)->orderBy('sort_order')->get();
    expect($remaining)->toHaveCount(2)
        ->and($remaining[0]->id)->toBe($step2->id)
        ->and($remaining[0]->sort_order)->toBe(0)
        ->and($remaining[1]->id)->toBe($step3->id)
        ->and($remaining[1]->sort_order)->toBe(1);
});

// ---------------------------------------------------------------------------
// INTG-01: Auto-watch via firstOrCreate on step save
// ---------------------------------------------------------------------------

test('saving a step auto-watches both input and output items', function () {
    $user = User::factory()->create();
    $shuffle = Shuffle::factory()->create(['user_id' => $user->id]);

    Volt::actingAs($user)->test('pages.shuffle-detail', ['shuffle' => $shuffle])
        ->call('addStep', 600001, 600002, 1, 1, 1);

    expect(WatchedItem::where('user_id', $user->id)->where('blizzard_item_id', 600001)->exists())->toBeTrue()
        ->and(WatchedItem::where('user_id', $user->id)->where('blizzard_item_id', 600002)->exists())->toBeTrue();
});

test('auto-watch does not overwrite existing manual watch thresholds', function () {
    $user = User::factory()->create();
    $shuffle = Shuffle::factory()->create(['user_id' => $user->id]);

    // Pre-existing manual watch with thresholds set
    WatchedItem::factory()->create([
        'user_id' => $user->id,
        'blizzard_item_id' => 700001,
        'buy_threshold' => 15,
        'sell_threshold' => 20,
        'created_by_shuffle_id' => null,
    ]);

    Volt::actingAs($user)->test('pages.shuffle-detail', ['shuffle' => $shuffle])
        ->call('addStep', 700001, 700002, 1, 1, 1);

    // Manual watch thresholds must be preserved (firstOrCreate doesn't update existing)
    $watch = WatchedItem::where('user_id', $user->id)->where('blizzard_item_id', 700001)->first();
    expect($watch->buy_threshold)->toBe(15)
        ->and($watch->sell_threshold)->toBe(20)
        ->and($watch->created_by_shuffle_id)->toBeNull();
});

test('auto-watched items get null thresholds and created_by_shuffle_id set', function () {
    $user = User::factory()->create();
    $shuffle = Shuffle::factory()->create(['user_id' => $user->id]);

    Volt::actingAs($user)->test('pages.shuffle-detail', ['shuffle' => $shuffle])
        ->call('addStep', 800001, 800002, 1, 1, 1);

    $watch = WatchedItem::where('user_id', $user->id)->where('blizzard_item_id', 800001)->first();
    expect($watch->buy_threshold)->toBeNull()
        ->and($watch->sell_threshold)->toBeNull()
        ->and($watch->created_by_shuffle_id)->toBe($shuffle->id);
});

// ---------------------------------------------------------------------------
// INTG-01: Orphan cleanup on step delete (model-level boot event — should PASS)
// ---------------------------------------------------------------------------

test('deleting a step removes orphan auto-watched items not referenced by other steps', function () {
    $user = User::factory()->create();
    $shuffle = Shuffle::factory()->create(['user_id' => $user->id]);
    $step = ShuffleStep::factory()->create([
        'shuffle_id' => $shuffle->id,
        'input_blizzard_item_id' => 910001,
        'output_blizzard_item_id' => 910002,
    ]);

    WatchedItem::factory()->create([
        'user_id' => $user->id,
        'blizzard_item_id' => 910001,
        'created_by_shuffle_id' => $shuffle->id,
    ]);
    WatchedItem::factory()->create([
        'user_id' => $user->id,
        'blizzard_item_id' => 910002,
        'created_by_shuffle_id' => $shuffle->id,
    ]);

    expect(WatchedItem::where('user_id', $user->id)->count())->toBe(2);

    $step->delete();

    expect(WatchedItem::where('user_id', $user->id)->count())->toBe(0);
});

test('deleting a step preserves items still referenced by other steps', function () {
    $user = User::factory()->create();
    $shuffleA = Shuffle::factory()->create(['user_id' => $user->id]);
    $shuffleB = Shuffle::factory()->create(['user_id' => $user->id]);

    $stepA = ShuffleStep::factory()->create([
        'shuffle_id' => $shuffleA->id,
        'input_blizzard_item_id' => 920001,
        'output_blizzard_item_id' => 920002,
    ]);
    ShuffleStep::factory()->create([
        'shuffle_id' => $shuffleB->id,
        'input_blizzard_item_id' => 920001,
        'output_blizzard_item_id' => 920003,
    ]);

    // Auto-watched item that both shuffles reference via blizzard_item_id 920001
    WatchedItem::factory()->create([
        'user_id' => $user->id,
        'blizzard_item_id' => 920001,
        'created_by_shuffle_id' => $shuffleA->id,
    ]);

    $stepA->delete();

    // 920001 still used by shuffleB's step — preserve
    expect(WatchedItem::where('blizzard_item_id', 920001)->count())->toBe(1);
});

test('deleting a step preserves manually-watched items', function () {
    $user = User::factory()->create();
    $shuffle = Shuffle::factory()->create(['user_id' => $user->id]);
    $step = ShuffleStep::factory()->create([
        'shuffle_id' => $shuffle->id,
        'input_blizzard_item_id' => 930001,
        'output_blizzard_item_id' => 930002,
    ]);

    // Manually-watched — no created_by_shuffle_id
    WatchedItem::factory()->create([
        'user_id' => $user->id,
        'blizzard_item_id' => 930001,
        'created_by_shuffle_id' => null,
    ]);

    $step->delete();

    // Manual watch must survive orphan cleanup
    expect(WatchedItem::where('blizzard_item_id', 930001)->count())->toBe(1);
});
