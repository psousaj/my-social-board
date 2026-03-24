<?php

namespace App\Social;

use InvalidArgumentException;

class IngestionProviderManager
{
    public function __construct(
        private readonly InstagramIngestionAdapter $instagram,
    ) {
    }

    public function adapterFor(string $provider): IngestionProviderAdapter
    {
        return match (strtolower($provider)) {
            'instagram' => $this->instagram,
            default => throw new InvalidArgumentException("Unsupported provider [{$provider}]."),
        };
    }
}
