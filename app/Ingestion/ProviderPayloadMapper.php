<?php

namespace App\Ingestion;

class ProviderPayloadMapper
{
    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function toCanonical(string $provider, int $tenantId, int $providerAccountId, array $payload): array
    {
        return [
            'tenant_id' => $tenantId,
            'provider_account_id' => $providerAccountId,
            'provider' => strtolower($provider),
            'external_media_id' => (string) ($payload['id'] ?? ''),
            'caption' => isset($payload['caption']) ? (string) $payload['caption'] : null,
            'media_type' => (string) ($payload['media_type'] ?? 'UNKNOWN'),
            'media_url' => isset($payload['media_url']) ? (string) $payload['media_url'] : null,
            'permalink' => isset($payload['permalink']) ? (string) $payload['permalink'] : null,
            'published_at' => isset($payload['timestamp']) ? (string) $payload['timestamp'] : null,
            'raw_payload' => $payload,
        ];
    }
}
