<?php

namespace App\Social;

use App\Models\ProviderAccount;
use App\Models\ProviderToken;

interface IngestionProviderAdapter
{
    public function providerName(): string;

    public function refreshToken(ProviderToken $token): ProviderToken;

    /**
     * @return array{items:array<int,array<string,mixed>>,next_cursor:?string}
     */
    public function fetchMediaPage(ProviderAccount $account, ?string $cursor, int $limit): array;
}
