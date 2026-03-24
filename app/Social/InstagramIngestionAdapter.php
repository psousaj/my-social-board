<?php

namespace App\Social;

use App\Models\ProviderAccount;
use App\Models\ProviderToken;

class InstagramIngestionAdapter implements IngestionProviderAdapter
{
    public function providerName(): string
    {
        return 'instagram';
    }

    public function refreshToken(ProviderToken $token): ProviderToken
    {
        return $token;
    }

    public function fetchMediaPage(ProviderAccount $account, ?string $cursor, int $limit): array
    {
        $limit = max(1, min($limit, 50));
        $dataset = $this->datasetFor((string) $account->external_account_id);

        $offset = $cursor !== null ? max(0, (int) $cursor) : 0;
        $slice = array_slice($dataset, $offset, $limit);
        $nextOffset = $offset + count($slice);

        return [
            'items' => $slice,
            'next_cursor' => $nextOffset < count($dataset) ? (string) $nextOffset : null,
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function datasetFor(string $externalAccountId): array
    {
        $items = [];

        for ($i = 1; $i <= 5; $i++) {
            $items[] = [
                'id' => $externalAccountId.'_media_'.$i,
                'caption' => 'Caption '.$i,
                'media_type' => 'IMAGE',
                'media_url' => 'https://cdn.instagram.test/'.$externalAccountId.'/'.$i.'.jpg',
                'permalink' => 'https://instagram.test/p/'.$externalAccountId.'-'.$i,
                'timestamp' => now()->subDays($i)->toISOString(),
                'metrics' => [
                    'likes' => 100 + $i,
                    'comments' => 10 + $i,
                ],
            ];
        }

        return $items;
    }
}
