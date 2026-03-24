<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DeveloperClientOAuthFlowTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function client_secret_rotation_supports_overlap_and_revocation_blocks_access(): void
    {
        $tenant = $this->tenant('oauth-a');

        $created = $this->postJson('/developer-clients', [
            'tenant_id' => $tenant->id,
            'name' => 'SDK',
        ])->assertCreated();

        $clientId = (string) $created->json('client_id');
        $oldSecret = (string) $created->json('client_secret');

        $rotated = $this->postJson('/developer-clients/'.$clientId.'/rotate-secret', [
            'tenant_id' => $tenant->id,
            'overlap_seconds' => 600,
        ])->assertOk();

        $newSecret = (string) $rotated->json('new_client_secret');

        $this->postJson('/oauth/token', [
            'tenant_id' => $tenant->id,
            'client_id' => $clientId,
            'client_secret' => $oldSecret,
        ])->assertOk();

        $tokenResponse = $this->postJson('/oauth/token', [
            'tenant_id' => $tenant->id,
            'client_id' => $clientId,
            'client_secret' => $newSecret,
        ])->assertOk();

        $refreshToken = (string) $tokenResponse->json('refresh_token');

        $this->postJson('/oauth/token/refresh', [
            'tenant_id' => $tenant->id,
            'client_id' => $clientId,
            'client_secret' => $newSecret,
            'refresh_token' => $refreshToken,
        ])->assertOk()->assertJsonStructure(['access_token', 'refresh_token']);

        $this->postJson('/developer-clients/'.$clientId.'/revoke', [
            'tenant_id' => $tenant->id,
        ])->assertOk()->assertJson(['status' => 'revoked']);

        $this->postJson('/oauth/token', [
            'tenant_id' => $tenant->id,
            'client_id' => $clientId,
            'client_secret' => $newSecret,
        ])->assertStatus(401)->assertJsonPath('error_code', 'CLIENT_UNAUTHORIZED');
    }

    #[Test]
    public function oauth_revoke_invalidates_refresh_token(): void
    {
        $tenant = $this->tenant('oauth-b');

        $created = $this->postJson('/developer-clients', [
            'tenant_id' => $tenant->id,
            'name' => 'SDK2',
        ])->assertCreated();

        $clientId = (string) $created->json('client_id');
        $secret = (string) $created->json('client_secret');

        $tokenResponse = $this->postJson('/oauth/token', [
            'tenant_id' => $tenant->id,
            'client_id' => $clientId,
            'client_secret' => $secret,
        ])->assertOk();

        $refreshToken = (string) $tokenResponse->json('refresh_token');

        $this->postJson('/oauth/token/revoke', [
            'tenant_id' => $tenant->id,
            'client_id' => $clientId,
            'client_secret' => $secret,
            'refresh_token' => $refreshToken,
        ])->assertOk()->assertJson(['revoked' => true]);

        $this->postJson('/oauth/token/refresh', [
            'tenant_id' => $tenant->id,
            'client_id' => $clientId,
            'client_secret' => $secret,
            'refresh_token' => $refreshToken,
        ])->assertStatus(401)->assertJsonPath('error_code', 'REFRESH_TOKEN_INVALID');
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
