<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Shuffle;
use Closure;
use Illuminate\Http\Request;

class EnsureShuffleOwner
{
    public function handle(Request $request, Closure $next): mixed
    {
        $shuffle = $request->route('shuffle');

        // If model binding hasn't resolved yet, resolve it manually
        if (is_string($shuffle) || is_int($shuffle)) {
            $shuffle = Shuffle::find($shuffle);
        }

        abort_unless($shuffle && $shuffle->user_id === auth()->id(), 403);

        return $next($request);
    }
}
