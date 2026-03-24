<?php

namespace App\Social;

use InvalidArgumentException;

class SocialProviderManager
{
    public function __construct(
        private readonly InstagramOAuthAdapter $instagram,
    ) {
    }

    public function adapterFor(string $provider): OAuthProviderAdapter
    {
        return match (strtolower($provider)) {
            'instagram' => $this->instagram,
            default => throw new InvalidArgumentException("Unsupported provider [{$provider}]."),
        };
    }
}
