<?php

namespace App\Security;

use App\Models\ApiAccessToken;
use App\Models\ApiRefreshToken;

class ApiTokenService
{
    /** @return array{access_token:string,refresh_token:string,access_expires_in:int,refresh_expires_in:int} */
    public function issue(int $tenantId, int $developerClientId): array
    {
        $access = bin2hex(random_bytes(32));
        $refresh = bin2hex(random_bytes(32));

        ApiAccessToken::create([
            'tenant_id' => $tenantId,
            'developer_client_id' => $developerClientId,
            'token_hash' => hash('sha256', $access),
            'expires_at' => now()->addMinutes(10),
        ]);

        ApiRefreshToken::create([
            'tenant_id' => $tenantId,
            'developer_client_id' => $developerClientId,
            'token_hash' => hash('sha256', $refresh),
            'expires_at' => now()->addDays(30),
        ]);

        return [
            'access_token' => $access,
            'refresh_token' => $refresh,
            'access_expires_in' => 600,
            'refresh_expires_in' => 2592000,
        ];
    }

    /** @return array{access_token:string,refresh_token:string,access_expires_in:int,refresh_expires_in:int}|null */
    public function refresh(int $tenantId, int $developerClientId, string $refreshToken): ?array
    {
        $hash = hash('sha256', $refreshToken);

        $refresh = ApiRefreshToken::query()
            ->where('tenant_id', $tenantId)
            ->where('developer_client_id', $developerClientId)
            ->where('token_hash', $hash)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->first();

        if (! $refresh) {
            return null;
        }

        $refresh->update(['revoked_at' => now()]);

        return $this->issue($tenantId, $developerClientId);
    }

    public function revokeByRefreshToken(int $tenantId, int $developerClientId, string $refreshToken): bool
    {
        $hash = hash('sha256', $refreshToken);

        $updated = ApiRefreshToken::query()
            ->where('tenant_id', $tenantId)
            ->where('developer_client_id', $developerClientId)
            ->where('token_hash', $hash)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now(), 'updated_at' => now()]);

        return $updated > 0;
    }
}
