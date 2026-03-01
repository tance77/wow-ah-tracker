<?php

declare(strict_types=1);

use App\Services\BlizzardTokenService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Cache::forget('blizzard_token');
});

it('fetches a new token via Basic Auth when cache is empty', function (): void {
    Http::fake([
        'oauth.battle.net/token' => Http::response(
            ['access_token' => 'test-token', 'token_type' => 'bearer', 'expires_in' => 86400],
            200
        ),
    ]);

    $service = app(BlizzardTokenService::class);
    $token = $service->getToken();

    expect($token)->toBe('test-token');

    Http::assertSent(function (Request $request): bool {
        return str_contains($request->url(), 'oauth.battle.net/token')
            && str_starts_with($request->header('Authorization')[0] ?? '', 'Basic ');
    });

    Http::assertSentCount(1);
});

it('returns cached token without HTTP request on cache hit', function (): void {
    Http::fake([
        'oauth.battle.net/token' => Http::response(
            ['access_token' => 'test-token', 'token_type' => 'bearer', 'expires_in' => 86400],
            200
        ),
    ]);

    $service = app(BlizzardTokenService::class);

    $first = $service->getToken();
    $second = $service->getToken();

    expect($first)->toBe('test-token');
    expect($second)->toBe('test-token');

    Http::assertSentCount(1);
});

it('throws RuntimeException on 401 unauthorized', function (): void {
    Http::fake([
        'oauth.battle.net/token' => Http::response([], 401),
    ]);

    $service = app(BlizzardTokenService::class);

    expect(fn () => $service->getToken())->toThrow(RuntimeException::class);
});

it('throws RuntimeException on 500 server error', function (): void {
    Http::fake([
        'oauth.battle.net/token' => Http::response([], 500),
    ]);

    $service = app(BlizzardTokenService::class);

    expect(fn () => $service->getToken())->toThrow(RuntimeException::class);
});
