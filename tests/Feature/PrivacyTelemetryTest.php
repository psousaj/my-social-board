<?php

namespace Tests\Feature;

use App\Models\ProviderAccount;
use App\Models\ProviderToken;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PrivacyTelemetryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function privacy_endpoints_work_and_emit_minimal_telemetry(): void
    {
        $tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Tenant P',
            'slug' => 'tenant-p',
            'status' => 'active',
        ]);

        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'User P',
            'email' => 'p@example.com',
            'password' => 'secret',
        ]);

        $account = ProviderAccount::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'provider' => 'instagram',
            'external_account_id' => 'igp',
            'display_name' => 'IGP',
            'status' => 'active',
        ]);

        ProviderToken::create([
            'tenant_id' => $tenant->id,
            'provider_account_id' => $account->id,
            'provider' => 'instagram',
            'token_type' => 'bearer',
            'access_token' => 'access_p',
            'refresh_token' => 'refresh_p',
            'expires_at' => now()->addDays(30),
        ]);

        $export = $this->postJson('/privacy/data-export', ['tenant_id' => $tenant->id])
            ->assertOk()
            ->assertHeader('X-Trace-Id');

        $this->assertSame($tenant->id, $export->json('tenant_id'));

        $this->postJson('/privacy/deauthorize', [
            'tenant_id' => $tenant->id,
            'provider' => 'instagram',
        ])->assertOk()->assertJsonPath('revoked_tokens', 1);

        $this->assertDatabaseHas('provider_tokens', [
            'tenant_id' => $tenant->id,
            'provider' => 'instagram',
        ]);

        $this->postJson('/privacy/data-deletion', ['tenant_id' => $tenant->id])
            ->assertOk()
            ->assertJson(['status' => 'deleted']);

        $this->assertDatabaseCount('provider_accounts', 0);
        $this->assertDatabaseCount('provider_tokens', 0);

        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'event_type' => 'privacy.export',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'event_type' => 'privacy.deauthorize',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'event_type' => 'privacy.delete',
        ]);
    }
}
