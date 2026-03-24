<?php

namespace App\Billing;

interface EdgeCounter
{
    public function increment(string $key, int $ttlSeconds): int;
}
