<?php

declare(strict_types=1);

use App\Models\Shuffle;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Volt\Volt;

// Auth guard

test('shuffles page requires authentication', function () {
    $this->get('/shuffles')->assertRedirect('/login');
});

// View list

test('user can view shuffles list', function () {
    $user = User::factory()->create();
    $shuffle = Shuffle::factory()->create(['user_id' => $user->id, 'name' => 'My Shuffle']);

    Volt::actingAs($user)->test('pages.shuffles')
        ->assertSee('My Shuffle');
});

// SHUF-01 — Create

test('user can create a shuffle', function () {
    $user = User::factory()->create();

    Volt::actingAs($user)->test('pages.shuffles')
        ->call('createShuffle')
        ->assertRedirect();

    expect($user->shuffles()->count())->toBe(1);
    expect($user->shuffles()->first()->name)->toBe('New Shuffle');
});

// SHUF-03 — Rename

test('user can rename a shuffle', function () {
    $user = User::factory()->create();
    $shuffle = Shuffle::factory()->create(['user_id' => $user->id, 'name' => 'Old Name']);

    Volt::actingAs($user)->test('pages.shuffles')
        ->call('renameShuffle', $shuffle->id, 'New Name');

    expect($shuffle->fresh()->name)->toBe('New Name');
});

// SHUF-04 — Delete

test('user can delete a shuffle', function () {
    $user = User::factory()->create();
    $shuffle = Shuffle::factory()->create(['user_id' => $user->id]);

    Volt::actingAs($user)->test('pages.shuffles')
        ->call('deleteShuffle', $shuffle->id);

    expect(Shuffle::find($shuffle->id))->toBeNull();
});

// SHUF-05 — List / profitability badge

test('shuffles list shows shuffle name', function () {
    $user = User::factory()->create();
    $shuffle = Shuffle::factory()->create(['user_id' => $user->id, 'name' => 'Empty Shuffle']);

    Volt::actingAs($user)->test('pages.shuffles')
        ->assertSee('Empty Shuffle');
});

// User isolation — view

test('user cannot see another user shuffles', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    Shuffle::factory()->create(['user_id' => $otherUser->id, 'name' => 'Secret Shuffle']);

    Volt::actingAs($user)->test('pages.shuffles')
        ->assertDontSee('Secret Shuffle');
});

// User isolation — delete

test('user cannot delete another user shuffle', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $shuffle = Shuffle::factory()->create(['user_id' => $otherUser->id]);

    expect(fn () => Volt::actingAs($user)->test('pages.shuffles')
        ->call('deleteShuffle', $shuffle->id)
    )->toThrow(ModelNotFoundException::class);

    expect(Shuffle::find($shuffle->id))->not->toBeNull();
});

// Detail page — SHUF-03 rename from detail

test('user can rename a shuffle from detail page', function () {
    $user = User::factory()->create();
    $shuffle = Shuffle::factory()->create(['user_id' => $user->id, 'name' => 'Old Name']);

    Volt::actingAs($user)->test('pages.shuffle-detail', ['shuffle' => $shuffle])
        ->call('renameShuffle', 'New Name');

    expect($shuffle->fresh()->name)->toBe('New Name');
});

test('rename ignores empty name on detail page', function () {
    $user = User::factory()->create();
    $shuffle = Shuffle::factory()->create(['user_id' => $user->id, 'name' => 'My Shuffle']);

    Volt::actingAs($user)->test('pages.shuffle-detail', ['shuffle' => $shuffle])
        ->call('renameShuffle', '   ');

    expect($shuffle->fresh()->name)->toBe('My Shuffle');
});

// Detail page — SHUF-04 delete from detail

test('user can delete a shuffle from detail page', function () {
    $user = User::factory()->create();
    $shuffle = Shuffle::factory()->create(['user_id' => $user->id]);

    Volt::actingAs($user)->test('pages.shuffle-detail', ['shuffle' => $shuffle])
        ->call('deleteShuffle')
        ->assertRedirect(route('shuffles'));

    expect(Shuffle::find($shuffle->id))->toBeNull();
});

// Detail page — authorization

test('shuffle detail page returns 403 for another user shuffle', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $shuffle = Shuffle::factory()->create(['user_id' => $otherUser->id]);

    $this->actingAs($user)
        ->get(route('shuffles.show', $shuffle))
        ->assertForbidden();
});

// Clone

test('cloneShuffle creates a new shuffle named with Copy suffix', function () {
    $user = User::factory()->create();
    $shuffle = Shuffle::factory()->create(['user_id' => $user->id, 'name' => 'My Alchemy Shuffle']);

    Volt::actingAs($user)->test('pages.shuffles')
        ->call('cloneShuffle', $shuffle->id)
        ->assertRedirect();

    expect($user->shuffles()->count())->toBe(2);
    $clone = $user->shuffles()->where('id', '!=', $shuffle->id)->first();
    expect($clone->name)->toBe('My Alchemy Shuffle (Copy)');
});

test('cloned shuffle has same steps with identical fields', function () {
    $user = User::factory()->create();
    $shuffle = Shuffle::factory()->create(['user_id' => $user->id, 'name' => 'Multi Step']);
    $step1 = \App\Models\ShuffleStep::factory()->create([
        'shuffle_id' => $shuffle->id,
        'input_blizzard_item_id' => 100001,
        'output_blizzard_item_id' => 100002,
        'input_qty' => 5,
        'output_qty_min' => 2,
        'output_qty_max' => 4,
        'sort_order' => 0,
    ]);
    $step2 = \App\Models\ShuffleStep::factory()->create([
        'shuffle_id' => $shuffle->id,
        'input_blizzard_item_id' => 100002,
        'output_blizzard_item_id' => 100003,
        'input_qty' => 2,
        'output_qty_min' => 1,
        'output_qty_max' => 3,
        'sort_order' => 1,
    ]);

    Volt::actingAs($user)->test('pages.shuffles')
        ->call('cloneShuffle', $shuffle->id);

    $clone = $user->shuffles()->where('id', '!=', $shuffle->id)->first();
    $clonedSteps = $clone->steps()->orderBy('sort_order')->get();

    expect($clonedSteps)->toHaveCount(2);
    expect($clonedSteps[0]->input_blizzard_item_id)->toBe(100001);
    expect($clonedSteps[0]->output_blizzard_item_id)->toBe(100002);
    expect($clonedSteps[0]->input_qty)->toBe(5);
    expect($clonedSteps[0]->output_qty_min)->toBe(2);
    expect($clonedSteps[0]->output_qty_max)->toBe(4);
    expect($clonedSteps[0]->sort_order)->toBe(0);
    expect($clonedSteps[1]->input_blizzard_item_id)->toBe(100002);
    expect($clonedSteps[1]->output_blizzard_item_id)->toBe(100003);
    expect($clonedSteps[1]->sort_order)->toBe(1);
});

test('cloned shuffle steps have all byproducts copied', function () {
    $user = User::factory()->create();
    $shuffle = Shuffle::factory()->create(['user_id' => $user->id]);
    $step = \App\Models\ShuffleStep::factory()->create([
        'shuffle_id' => $shuffle->id,
        'input_blizzard_item_id' => 200001,
        'output_blizzard_item_id' => 200002,
        'sort_order' => 0,
    ]);
    \App\Models\ShuffleStepByproduct::factory()->create([
        'shuffle_step_id' => $step->id,
        'blizzard_item_id' => 200010,
        'item_name' => 'Byproduct A',
        'chance_percent' => 25.50,
        'quantity' => 3,
    ]);
    \App\Models\ShuffleStepByproduct::factory()->create([
        'shuffle_step_id' => $step->id,
        'blizzard_item_id' => 200011,
        'item_name' => 'Byproduct B',
        'chance_percent' => 10.00,
        'quantity' => 1,
    ]);

    Volt::actingAs($user)->test('pages.shuffles')
        ->call('cloneShuffle', $shuffle->id);

    $clone = $user->shuffles()->where('id', '!=', $shuffle->id)->first();
    $clonedStep = $clone->steps()->first();
    $byproducts = $clonedStep->byproducts()->orderBy('blizzard_item_id')->get();

    expect($byproducts)->toHaveCount(2);
    expect($byproducts[0]->blizzard_item_id)->toBe(200010);
    expect($byproducts[0]->item_name)->toBe('Byproduct A');
    expect($byproducts[0]->chance_percent)->toBe('25.50');
    expect($byproducts[0]->quantity)->toBe(3);
    expect($byproducts[1]->blizzard_item_id)->toBe(200011);
    expect($byproducts[1]->item_name)->toBe('Byproduct B');
});

test('cloned shuffle auto-watches all input output and byproduct items', function () {
    $user = User::factory()->create();
    $shuffle = Shuffle::factory()->create(['user_id' => $user->id]);
    $step = \App\Models\ShuffleStep::factory()->create([
        'shuffle_id' => $shuffle->id,
        'input_blizzard_item_id' => 300001,
        'output_blizzard_item_id' => 300002,
        'sort_order' => 0,
    ]);
    \App\Models\ShuffleStepByproduct::factory()->create([
        'shuffle_step_id' => $step->id,
        'blizzard_item_id' => 300003,
        'item_name' => 'BP Item',
        'chance_percent' => 50.00,
        'quantity' => 1,
    ]);

    Volt::actingAs($user)->test('pages.shuffles')
        ->call('cloneShuffle', $shuffle->id);

    $clone = $user->shuffles()->where('id', '!=', $shuffle->id)->first();
    $watchedItemIds = $user->watchedItems()->pluck('blizzard_item_id')->sort()->values()->all();

    expect($watchedItemIds)->toContain(300001);
    expect($watchedItemIds)->toContain(300002);
    expect($watchedItemIds)->toContain(300003);

    // Verify created_by_shuffle_id points to the clone
    $watched = $user->watchedItems()->where('blizzard_item_id', 300001)->first();
    expect($watched->created_by_shuffle_id)->toBe($clone->id);
});

test('user cannot clone another user shuffle', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $shuffle = Shuffle::factory()->create(['user_id' => $otherUser->id]);

    expect(fn () => Volt::actingAs($user)->test('pages.shuffles')
        ->call('cloneShuffle', $shuffle->id)
    )->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
});

test('clone redirects to the new shuffle detail page', function () {
    $user = User::factory()->create();
    $shuffle = Shuffle::factory()->create(['user_id' => $user->id, 'name' => 'Original']);

    $component = Volt::actingAs($user)->test('pages.shuffles')
        ->call('cloneShuffle', $shuffle->id);

    $clone = $user->shuffles()->where('id', '!=', $shuffle->id)->first();
    $component->assertRedirect(route('shuffles.show', $clone));
});

// Detail page — renders

test('shuffle detail page shows shuffle name', function () {
    $user = User::factory()->create();
    $shuffle = Shuffle::factory()->create(['user_id' => $user->id, 'name' => 'My Detail Shuffle']);

    Volt::actingAs($user)->test('pages.shuffle-detail', ['shuffle' => $shuffle])
        ->assertSee('My Detail Shuffle');
});
