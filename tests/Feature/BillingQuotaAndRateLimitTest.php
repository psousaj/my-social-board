<?php

namespace Tests\Feature;

use App\Models\DeveloperClient;
use App\Models\ProviderAccount;
use App\Models\ProviderToken;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BillingQuotaAndRateLimitTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function ingestion_is_blocked_when_tenant_is_over_quota(): void
    {
        [$tenant, $account] = $this->seedProviderContext('quota');
        $this->attachPlan($tenant->id, 1, 10);

        $this->postJson('/instagram/ingestion/run', [
            'tenant_id' => $tenant->id,
            'provider_account_id' => $account->id,
        ])->assertOk();

        $this->postJson('/instagram/ingestion/run', [
            'tenant_id' => $tenant->id,
            'provider_account_id' => $account->id,
        ])->assertStatus(429)->assertJsonPath('error_code', 'INGESTION_QUOTA_EXCEEDED');
    }

    #[Test]
    public function app_side_rate_limit_enforcement_is_consistent(): void
    {
        $tenant = $this->tenant('rate');
        $this->attachPlan($tenant->id, 100, 2);

        $client = DeveloperClient::create([
            'tenant_id' => $tenant->id,
            'name' => 'client',
            'client_id' => 'cli_'.Str::lower(Str::random(8)),
            'primary_secret_hash' => Hash::make('secret-x'),
            'status' => 'active',
        ]);

        $token = $this->postJson('/oauth/token', [
            'tenant_id' => $tenant->id,
            'client_id' => $client->client_id,
            'client_secret' => 'secret-x',
        ])->assertOk()->json('access_token');

        $headers = [
            'Authorization' => 'Bearer '.$token,
            'X-Tenant-Id' => (string) $tenant->id,
        ];

        $this->getJson('/v1/usage', $headers)->assertOk();
        $this->getJson('/v1/usage', $headers)->assertOk();
        $this->getJson('/v1/usage', $headers)->assertStatus(429)->assertJsonPath('error_code', 'RATE_LIMIT_EXCEEDED');
    }

    private function attachPlan(int $tenantId, int $mediaQuota, int $rpm): void
    {
        $plan = SubscriptionPlan::create([
            'code' => 'p_'.Str::lower(Str::random(6)),
            'name' => 'Plan',
            'quotas' => [
                'monthly_media_ingestion' => $mediaQuota,
                'api_requests_per_minute' => $rpm,
            ],
        ]);

        TenantSubscription::create([
            'tenant_id' => $tenantId,
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
            'period_start' => now(),
            'period_end' => now()->addDays(30),
        ]);
    }

    /** @return array{Tenant, ProviderAccount} */
    private function seedProviderContext(string $suffix): array
    {
        $tenant = $this->tenant($suffix);

        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'User '.$suffix,
            'email' => 'u-'.$suffix.'@example.com',
            'password' => 'secret',
        ]);

        $account = ProviderAccount::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'provider' => 'instagram',
            'external_account_id' => 'ig_'.$suffix,
            'display_name' => 'IG '.$suffix,
            'status' => 'active',
        ]);

        ProviderToken::create([
            'tenant_id' => $tenant->id,
            'provider_account_id' => $account->id,
            'provider' => 'instagram',
            'token_type' => 'bearer',
            'access_token' => 'access_'.$suffix,
            'refresh_token' => 'refresh_'.$suffix,
            'expires_at' => now()->addDays(30),
        ]);

        return [$tenant, $account];
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
