<?php

namespace App\Security;

class EmbedAccessTokenService
{
    public function issue(int $tenantId, string $embedUid, string $origin, string $widgetType, int $ttlSeconds = 300): string
    {
        $payload = [
            'tenant_id' => $tenantId,
            'embed_id' => $embedUid,
            'origin' => $origin,
            'widget' => $widgetType,
            'exp' => now()->addSeconds($ttlSeconds)->timestamp,
        ];

        $encoded = rtrim(strtr(base64_encode(json_encode($payload, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
        $sig = hash_hmac('sha256', $encoded, (string) config('app.key'));

        return $encoded.'.'.$sig;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function validate(string $token): ?array
    {
        $parts = explode('.', $token, 2);

        if (count($parts) !== 2) {
            return null;
        }

        [$encoded, $sig] = $parts;
        $expected = hash_hmac('sha256', $encoded, (string) config('app.key'));

        if (! hash_equals($expected, $sig)) {
            return null;
        }

        $decoded = base64_decode(strtr($encoded, '-_', '+/'), true);
        if (! is_string($decoded)) {
            return null;
        }

        $payload = json_decode($decoded, true);

        if (! is_array($payload) || ! isset($payload['exp']) || now()->timestamp > (int) $payload['exp']) {
            return null;
        }

        return $payload;
    }
}
