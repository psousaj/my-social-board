<?php

namespace Tests\Feature;

use App\Ingestion\ProviderPayloadMapper;
use App\Models\ProviderAccount;
use App\Models\ProviderToken;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IngestionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function run_ingestion_succeeds_and_persists_media_and_snapshots(): void
    {
        [$tenant, $account] = $this->seedContext('ok');

        $response = $this->postJson('/instagram/ingestion/run', [
            'tenant_id' => $tenant->id,
            'provider_account_id' => $account->id,
        ]);

        $response->assertOk()->assertJson([
            'state' => 'succeeded',
            'attempts' => 1,
        ]);

        $this->assertDatabaseCount('media_items', 5);
        $this->assertDatabaseCount('media_metric_snapshots', 5);

        $snapshot = \App\Models\MediaMetricSnapshot::query()->firstOrFail();
        $this->assertIsArray($snapshot->raw_snapshot);
        $this->assertArrayHasKey('likes', $snapshot->raw_snapshot);
        $this->assertNotEmpty($snapshot->getRawOriginal('raw_snapshot_encrypted'));
    }

    #[Test]
    public function run_ingestion_retries_before_success_when_transient_failures_happen(): void
    {
        [$tenant, $account] = $this->seedContext('retry');

        $response = $this->postJson('/instagram/ingestion/run', [
            'tenant_id' => $tenant->id,
            'provider_account_id' => $account->id,
            'simulate_retry_failures' => 2,
        ]);

        $response->assertOk()->assertJson([
            'state' => 'succeeded',
            'attempts' => 3,
        ]);

        $this->assertDatabaseHas('ingestion_jobs', [
            'tenant_id' => $tenant->id,
            'state' => 'succeeded',
            'attempts' => 3,
        ]);
    }

    #[Test]
    public function run_ingestion_returns_terminal_failure_when_provider_fails(): void
    {
        [$tenant, $account] = $this->seedContext('fail');

        $response = $this->postJson('/instagram/ingestion/run', [
            'tenant_id' => $tenant->id,
            'provider_account_id' => $account->id,
            'simulate_terminal_failure' => true,
        ]);

        $response->assertStatus(502)->assertJson([
            'state' => 'failed',
            'error_code' => 'INGESTION_TERMINAL_FAILURE',
        ]);

        $jobId = (int) $response->json('id');

        $this->getJson('/instagram/ingestion/jobs?tenant_id='.$tenant->id)
            ->assertOk()
            ->assertJsonPath('data.0.id', $jobId);

        $this->getJson('/instagram/ingestion/jobs/'.$jobId.'?tenant_id='.$tenant->id)
            ->assertOk()
            ->assertJsonPath('error_code', 'INGESTION_TERMINAL_FAILURE');
    }

    #[Test]
    public function payload_mapper_produces_canonical_shape_and_supports_baseline_query(): void
    {
        $mapper = new ProviderPayloadMapper();

        $canonical = $mapper->toCanonical('instagram', 10, 11, [
            'id' => 'ext_1',
            'caption' => 'Hello',
            'media_type' => 'IMAGE',
            'media_url' => 'https://example.test/img.jpg',
            'permalink' => 'https://example.test/p/1',
            'timestamp' => now()->toISOString(),
            'metrics' => ['likes' => 1, 'comments' => 2],
        ]);

        $this->assertSame('ext_1', $canonical['external_media_id']);
        $this->assertSame('instagram', $canonical['provider']);

        [$tenant, $account] = $this->seedContext('baseline');
        $this->postJson('/instagram/ingestion/run', [
            'tenant_id' => $tenant->id,
            'provider_account_id' => $account->id,
        ])->assertOk();

        $count = \App\Models\MediaItem::query()
            ->where('tenant_id', $tenant->id)
            ->where('provider', 'instagram')
            ->count();

        $this->assertSame(5, $count);
    }

    /** @return array{Tenant, ProviderAccount} */
    private function seedContext(string $suffix): array
    {
        $tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Tenant '.$suffix,
            'slug' => 'tenant-'.$suffix,
            'status' => 'active',
        ]);

        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'User '.$suffix,
            'email' => $suffix.'@example.com',
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
            'expires_at' => now()->addDays(60),
        ]);

        return [$tenant, $account];
    }
}
