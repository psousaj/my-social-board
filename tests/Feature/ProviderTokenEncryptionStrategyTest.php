<?php

namespace Tests\Feature;

use App\Models\ProviderAccount;
use App\Models\ProviderToken;
use App\Models\Tenant;
use App\Models\User;
use App\Security\Exceptions\UnknownKeyVersionException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProviderTokenEncryptionStrategyTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_persists_tokens_encrypted_and_exposes_plain_values_through_crypto_service(): void
    {
        $tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Tenant A',
            'slug' => 'tenant-a',
            'status' => 'active',
        ]);

        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => 'secret',
        ]);

        $providerAccount = ProviderAccount::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'provider' => 'instagram',
            'external_account_id' => 'ig_123',
            'display_name' => 'My IG',
            'status' => 'active',
        ]);

        $token = ProviderToken::create([
            'tenant_id' => $tenant->id,
            'provider_account_id' => $providerAccount->id,
            'provider' => 'instagram',
            'token_type' => 'bearer',
            'access_token' => 'plain-access-token',
            'refresh_token' => 'plain-refresh-token',
        ]);

        $stored = ProviderToken::query()->findOrFail($token->id);

        $this->assertNotSame('plain-access-token', $stored->getRawOriginal('access_token_encrypted'));
        $this->assertNotSame('plain-refresh-token', $stored->getRawOriginal('refresh_token_encrypted'));
        $this->assertSame('plain-access-token', $stored->access_token);
        $this->assertSame('plain-refresh-token', $stored->refresh_token);
    }

    #[Test]
    public function it_fails_to_read_tokens_when_key_version_is_invalid(): void
    {
        $tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Tenant B',
            'slug' => 'tenant-b',
            'status' => 'active',
        ]);

        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Owner B',
            'email' => 'owner-b@example.com',
            'password' => 'secret',
        ]);

        $providerAccount = ProviderAccount::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'provider' => 'instagram',
            'external_account_id' => 'ig_999',
            'display_name' => 'Other IG',
            'status' => 'active',
        ]);

        $token = ProviderToken::create([
            'tenant_id' => $tenant->id,
            'provider_account_id' => $providerAccount->id,
            'provider' => 'instagram',
            'token_type' => 'bearer',
            'access_token' => 'plain-access-token',
        ]);

        $payload = json_decode((string) $token->getRawOriginal('access_token_encrypted'), true, flags: JSON_THROW_ON_ERROR);
        $payload['version'] = 'v-missing';

        $token->forceFill([
            'access_token_encrypted' => json_encode($payload, JSON_THROW_ON_ERROR),
        ])->save();

        $this->expectException(UnknownKeyVersionException::class);
        $token->fresh()->access_token;
    }
}
