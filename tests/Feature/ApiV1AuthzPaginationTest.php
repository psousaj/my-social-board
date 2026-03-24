<?php

namespace Tests\Feature;

use App\Models\DeveloperClient;
use App\Models\MediaItem;
use App\Models\ProviderAccount;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ApiV1AuthzPaginationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function v1_requires_auth_and_applies_tenant_filter_pagination_and_contract(): void
    {
        $tenantA = $this->tenant('api-a');
        $tenantB = $this->tenant('api-b');
        $accountA = $this->providerAccount($tenantA->id, 'a');
        $accountB = $this->providerAccount($tenantB->id, 'b');

        $client = $this->createClient($tenantA->id, 'sec-a');
        $tokenResponse = $this->postJson('/oauth/token', [
            'tenant_id' => $tenantA->id,
            'client_id' => $client->client_id,
            'client_secret' => 'sec-a',
        ])->assertOk();

        $access = (string) $tokenResponse->json('access_token');

        for ($i = 1; $i <= 6; $i++) {
            MediaItem::create([
                'tenant_id' => $tenantA->id,
                'provider_account_id' => $accountA->id,
                'provider' => 'instagram',
                'external_media_id' => 'a-'.$i,
                'caption' => 'A'.$i,
                'media_type' => 'IMAGE',
                'media_url' => 'https://example.test/a'.$i,
                'permalink' => 'https://example.test/p/a'.$i,
                'raw_payload' => ['i' => $i],
                'published_at' => now(),
            ]);
        }

        MediaItem::create([
            'tenant_id' => $tenantB->id,
            'provider_account_id' => $accountB->id,
            'provider' => 'instagram',
            'external_media_id' => 'b-1',
            'caption' => 'B1',
            'media_type' => 'IMAGE',
            'media_url' => 'https://example.test/b1',
            'permalink' => 'https://example.test/p/b1',
            'raw_payload' => ['i' => 1],
            'published_at' => now(),
        ]);

        $this->getJson('/v1/media')->assertStatus(401);

        $response = $this->getJson('/v1/media?page=2&per_page=2&provider=instagram', [
            'Authorization' => 'Bearer '.$access,
            'X-Tenant-Id' => (string) $tenantA->id,
        ])->assertOk();

        $response->assertJsonPath('meta.page', 2);
        $response->assertJsonPath('meta.per_page', 2);
        $response->assertJsonPath('meta.total', 6);
        $response->assertJsonCount(2, 'data');

        foreach ((array) $response->json('data') as $item) {
            $this->assertSame($tenantA->id, $item['tenant_id']);
        }
    }

    private function createClient(int $tenantId, string $secret): DeveloperClient
    {
        return DeveloperClient::create([
            'tenant_id' => $tenantId,
            'name' => 'client',
            'client_id' => 'cli_'.Str::lower(Str::random(10)),
            'primary_secret_hash' => Hash::make($secret),
            'status' => 'active',
        ]);
    }

    private function providerAccount(int $tenantId, string $suffix): ProviderAccount
    {
        $user = User::create([
            'tenant_id' => $tenantId,
            'name' => 'User '.$suffix,
            'email' => 'u-'.$suffix.'@example.com',
            'password' => 'secret',
        ]);

        return ProviderAccount::create([
            'tenant_id' => $tenantId,
            'user_id' => $user->id,
            'provider' => 'instagram',
            'external_account_id' => 'ig-'.$suffix,
            'display_name' => 'IG '.$suffix,
            'status' => 'active',
        ]);
    }

    private function tenant(string $suffix): Tenant
    {
        return Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Tenant '.$suffix,
            'slug' => 'tenant-'.$suffix,
            'status' => 'active',
        ]);
    }
}
