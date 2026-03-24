<?php

namespace Tests\Feature;

use App\Models\Embed;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmbedSecurityFlowTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function embeds_crud_and_tenant_isolation_work(): void
    {
        $tenantA = $this->tenant('a');
        $tenantB = $this->tenant('b');

        $created = $this->postJson('/embeds', [
            'tenant_id' => $tenantA->id,
            'name' => 'Widget A',
            'widget_type' => 'media-grid',
            'config' => ['theme' => 'light'],
        ])->assertCreated();

        $embedId = (string) $created->json('embed_uid');

        $this->getJson('/embeds?tenant_id='.$tenantA->id)->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/embeds?tenant_id='.$tenantB->id)->assertOk()->assertJsonCount(0, 'data');

        $this->patchJson('/embeds/'.$embedId.'?tenant_id='.$tenantA->id, [
            'name' => 'Widget A2',
        ])->assertOk()->assertJsonPath('name', 'Widget A2');

        $this->getJson('/embeds/'.$embedId.'?tenant_id='.$tenantB->id)->assertStatus(404);

        $this->deleteJson('/embeds/'.$embedId.'?tenant_id='.$tenantA->id)->assertOk();
        $this->assertDatabaseCount('embeds', 0);
    }

    #[Test]
    public function runtime_validates_token_origin_and_sets_security_headers(): void
    {
        $tenant = $this->tenant('sec');
        $embed = Embed::create([
            'tenant_id' => $tenant->id,
            'embed_uid' => (string) Str::uuid(),
            'name' => 'Widget',
            'widget_type' => 'media-grid',
            'config' => ['density' => 'compact'],
            'status' => 'active',
        ]);

        $token = $this->postJson('/embeds/'.$embed->embed_uid.'/token', [
            'tenant_id' => $tenant->id,
            'origin' => 'https://site.example',
            'ttl_seconds' => 60,
        ])->assertOk()->json('token');

        $this->getJson('/embed/'.$embed->embed_uid.'?token='.$token, [
            'Origin' => 'https://site.example',
        ])->assertOk()
            ->assertHeader('Content-Security-Policy')
            ->assertHeader('X-Frame-Options', 'ALLOWALL')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->getJson('/embed/'.$embed->embed_uid.'?token='.$token, [
            'Origin' => 'https://evil.example',
        ])->assertStatus(401)->assertJsonPath('error_code', 'EMBED_TOKEN_ORIGIN_MISMATCH');

        $forged = $token.'x';
        $this->getJson('/embed/'.$embed->embed_uid.'?token='.$forged, [
            'Origin' => 'https://site.example',
        ])->assertStatus(401)->assertJsonPath('error_code', 'EMBED_TOKEN_INVALID');

        $expiredPayload = [
            'tenant_id' => $tenant->id,
            'embed_id' => $embed->embed_uid,
            'origin' => 'https://site.example',
            'widget' => 'media-grid',
            'exp' => now()->subMinute()->timestamp,
        ];
        $encoded = rtrim(strtr(base64_encode(json_encode($expiredPayload, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
        $sig = hash_hmac('sha256', $encoded, (string) config('app.key'));
        $expired = $encoded.'.'.$sig;

        $this->getJson('/embed/'.$embed->embed_uid.'?token='.$expired, [
            'Origin' => 'https://site.example',
        ])->assertStatus(401)->assertJsonPath('error_code', 'EMBED_TOKEN_INVALID');
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
