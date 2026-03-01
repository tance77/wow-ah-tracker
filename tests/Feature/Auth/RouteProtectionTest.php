<?php

declare(strict_types=1);

use App\Models\User;

test('root redirects unauthenticated users to login', function () {
    $response = $this->get('/');

    $response->assertRedirect('/login');
});

test('root redirects authenticated users to dashboard', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/');

    $response->assertRedirect(route('dashboard', absolute: false));
});

test('dashboard redirects unauthenticated users to login', function () {
    $response = $this->get('/dashboard');

    $response->assertRedirect('/login');
});

test('dashboard is accessible when authenticated', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk();
});

test('profile redirects unauthenticated users to login', function () {
    $response = $this->get('/profile');

    $response->assertRedirect('/login');
});
