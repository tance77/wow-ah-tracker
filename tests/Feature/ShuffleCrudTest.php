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

// Detail page — renders

test('shuffle detail page shows shuffle name', function () {
    $user = User::factory()->create();
    $shuffle = Shuffle::factory()->create(['user_id' => $user->id, 'name' => 'My Detail Shuffle']);

    Volt::actingAs($user)->test('pages.shuffle-detail', ['shuffle' => $shuffle])
        ->assertSee('My Detail Shuffle');
});
