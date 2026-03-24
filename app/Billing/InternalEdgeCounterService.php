<?php

namespace App\Billing;

use Illuminate\Support\Facades\Cache;

class InternalEdgeCounterService implements EdgeCounter
{
    public function increment(string $key, int $ttlSeconds): int
    {
        if (! Cache::has($key)) {
            Cache::put($key, 0, now()->addSeconds($ttlSeconds));
        }

        $value = (int) Cache::increment($key);
        Cache::put($key, $value, now()->addSeconds($ttlSeconds));

        return $value;
    }
}
