<?php

namespace App\Social;

use App\Models\ProviderAccount;
use App\Models\ProviderToken;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class InstagramIngestionAdapter implements IngestionProviderAdapter
{
    public function providerName(): string
    {
        return 'instagram';
    }

    public function refreshToken(ProviderToken $token, bool $force = false): ProviderToken
    {
        if ($this->useMockMode()) {
            return $token;
        }

        if (! $force && $token->expires_at !== null && $token->expires_at->gt(now()->addDays(21))) {
            return $token;
        }

        $accessToken = $token->access_token;

        if (! is_string($accessToken) || $accessToken === '') {
            throw new RuntimeException('Missing Instagram access token for refresh.');
        }

        $response = Http::acceptJson()
            ->timeout(15)
            ->get($this->graphUrl('/refresh_access_token'), [
                'grant_type' => 'ig_refresh_token',
                'access_token' => $accessToken,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException($this->responseError($response, 'Instagram token refresh failed.'));
        }

        $nextAccessToken = (string) $response->json('access_token', $accessToken);
        $expiresIn = (int) $response->json('expires_in', 0);
        $nextExpiresAt = $expiresIn > 0 ? now()->addSeconds($expiresIn) : $token->expires_at;

        $token->fill([
            'access_token' => $nextAccessToken,
            'expires_at' => $nextExpiresAt,
            'revoked_at' => null,
        ])->save();

        $token->providerAccount?->update(['status' => 'active']);

        return $token;
    }

    public function fetchMediaPage(ProviderAccount $account, ?string $cursor, int $limit): array
    {
        $limit = max(1, min($limit, 50));

        if ($this->useMockMode()) {
            $dataset = $this->datasetFor((string) $account->external_account_id);

            $offset = $cursor !== null ? max(0, (int) $cursor) : 0;
            $slice = array_slice($dataset, $offset, $limit);
            $nextOffset = $offset + count($slice);

            return [
                'items' => $slice,
                'next_cursor' => $nextOffset < count($dataset) ? (string) $nextOffset : null,
            ];
        }

        $token = ProviderToken::query()
            ->where('tenant_id', $account->tenant_id)
            ->where('provider_account_id', $account->id)
            ->where('provider', 'instagram')
            ->whereNull('revoked_at')
            ->latest('id')
            ->first();

        if (! $token) {
            throw new RuntimeException('No active token found for Instagram account.');
        }

        $token = $this->refreshToken($token, false);

        $accessToken = $token->access_token;
        if (! is_string($accessToken) || $accessToken === '') {
            throw new RuntimeException('Missing Instagram access token for media ingestion.');
        }

        $response = Http::acceptJson()
            ->timeout(20)
            ->get($this->graphUrl('/'.$account->external_account_id.'/media'), array_filter([
                'fields' => 'id,caption,media_type,media_url,permalink,timestamp,thumbnail_url,like_count,comments_count',
                'limit' => $limit,
                'after' => $cursor,
                'access_token' => $accessToken,
            ], static fn (mixed $value): bool => $value !== null && $value !== ''));

        if (! $response->successful()) {
            throw new RuntimeException($this->responseError($response, 'Instagram media fetch failed.'));
        }

        $payload = $response->json();
        $items = (array) ($payload['data'] ?? []);
        $nextCursor = $payload['paging']['cursors']['after'] ?? null;

        $normalized = array_map(function (mixed $raw): array {
            $entry = is_array($raw) ? $raw : [];

            $likes = (int) ($entry['like_count'] ?? 0);
            $comments = (int) ($entry['comments_count'] ?? 0);

            return [
                'id' => (string) ($entry['id'] ?? ''),
                'caption' => isset($entry['caption']) ? (string) $entry['caption'] : null,
                'media_type' => (string) ($entry['media_type'] ?? 'UNKNOWN'),
                'media_url' => isset($entry['media_url']) ? (string) $entry['media_url'] : (isset($entry['thumbnail_url']) ? (string) $entry['thumbnail_url'] : null),
                'permalink' => isset($entry['permalink']) ? (string) $entry['permalink'] : null,
                'timestamp' => isset($entry['timestamp']) ? (string) $entry['timestamp'] : null,
                'metrics' => [
                    'likes' => $likes,
                    'comments' => $comments,
                ],
            ];
        }, $items);

        $filtered = array_values(array_filter($normalized, static fn (array $entry): bool => $entry['id'] !== ''));

        return [
            'items' => $filtered,
            'next_cursor' => is_string($nextCursor) && $nextCursor !== '' ? $nextCursor : null,
        ];
    }

    private function graphUrl(string $path): string
    {
        $base = (string) config('services.instagram.graph_url', 'https://graph.instagram.com');

        return rtrim($base, '/').'/'.ltrim($path, '/');
    }

    private function useMockMode(): bool
    {
        return (bool) config('services.instagram.use_mock', false);
    }

    private function responseError(Response $response, string $fallback): string
    {
        $error = $response->json('error.message')
            ?? $response->json('error_description')
            ?? $response->json('message');

        if (is_string($error) && $error !== '') {
            return $error;
        }

        return $fallback;
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
