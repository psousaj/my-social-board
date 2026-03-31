<?php

namespace Tests\Feature;

use App\Models\OauthState;
use App\Models\Tenant;
use App\Models\User;
use App\Social\OAuthProviderAdapter;
use App\Social\SocialProviderManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class OAuthCoreFlowTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function start_route_generates_state_persists_it_and_redirects(): void
    {
        [$tenant, $user] = $this->tenantUserContext('a');

        $this->bindAdapter(new class implements OAuthProviderAdapter {
            public function authorizationUrl(string $state, string $redirectUri): string
            {
                return 'https://provider.test/oauth?state='.$state.'&redirect_uri='.urlencode($redirectUri);
            }

            public function exchangeCode(string $code, string $redirectUri): array
            {
                return [];
            }
        });

        $response = $this->actingAs($user)->get('/instagram/authorize/start');
        $response->assertStatus(302);

        $location = (string) $response->headers->get('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $params);

        $this->assertArrayHasKey('state', $params);
        $this->assertDatabaseHas('oauth_states', [
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'provider' => 'instagram',
            'state_hash' => hash('sha256', (string) $params['state']),
        ]);
    }

    #[Test]
    public function callback_rejects_state_mismatch_with_unauthorized(): void
    {
        $this->bindAdapter($this->successfulAdapter());

        $response = $this->get('/instagram/authorize/callback?state=wrong&code=test-code');
        $response->assertStatus(401);
    }

    #[Test]
    public function callback_success_persists_consent_and_token_records(): void
    {
        [$tenant, $user] = $this->tenantUserContext('b');
        $this->bindAdapter($this->successfulAdapter());

        $start = $this->actingAs($user)->get('/instagram/authorize/start');
        $location = (string) $start->headers->get('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $params);

        $callback = $this->get('/instagram/authorize/callback?state='.$params['state'].'&code=ok_code');
        $callback->assertOk()->assertJson(['status' => 'connected']);

        $this->assertDatabaseHas('provider_accounts', [
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'provider' => 'instagram',
            'external_account_id' => 'ig_external_1',
        ]);

        $this->assertDatabaseCount('oauth_consents', 1);
        $this->assertDatabaseCount('provider_tokens', 1);

        // second callback with same state must be rejected (state is single-use)
        $this->get('/instagram/authorize/callback?state='.$params['state'].'&code=ok_code')
            ->assertStatus(401);
    }

    #[Test]
    public function callback_handles_token_exchange_failure_safely(): void
    {
        [$tenant, $user] = $this->tenantUserContext('c');

        $state = OauthState::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'provider' => 'instagram',
            'state_hash' => hash('sha256', 'state-1'),
            'expires_at' => now()->addMinutes(5),
        ]);

        $this->bindAdapter(new class implements OAuthProviderAdapter {
            public function authorizationUrl(string $state, string $redirectUri): string
            {
                return 'https://provider.test/oauth';
            }

            public function exchangeCode(string $code, string $redirectUri): array
            {
                throw new RuntimeException('upstream failed');
            }
        });

        $response = $this->get('/instagram/authorize/callback?state=state-1&code=bad_code');
        $response->assertStatus(502);

        $state->refresh();
        $this->assertNotNull($state->used_at);
    }

    #[Test]
    public function status_and_revoke_reflect_connection_state(): void
    {
        [$tenant, $user] = $this->tenantUserContext('d');
        $this->bindAdapter($this->successfulAdapter());

        $start = $this->actingAs($user)->get('/instagram/authorize/start');
        parse_str((string) parse_url((string) $start->headers->get('Location'), PHP_URL_QUERY), $params);

        $this->get('/instagram/authorize/callback?state='.$params['state'].'&code=ok_code')->assertOk();

        $this->actingAs($user)->get('/instagram/authorize/status')
            ->assertOk()
            ->assertJson(['connected' => true, 'state' => 'connected']);

        $this->actingAs($user)->post('/instagram/authorize/revoke')
            ->assertOk()->assertJson(['state' => 'revoked']);

        $this->actingAs($user)->get('/instagram/authorize/status')
            ->assertOk()
            ->assertJson(['connected' => false, 'state' => 'revoked']);
    }

    private function bindAdapter(OAuthProviderAdapter $adapter): void
    {
        $manager = \Mockery::mock(SocialProviderManager::class);
        $manager->shouldReceive('adapterFor')->andReturn($adapter);

        $this->app->instance(SocialProviderManager::class, $manager);
    }

    private function successfulAdapter(): OAuthProviderAdapter
    {
        return new class implements OAuthProviderAdapter {
            public function authorizationUrl(string $state, string $redirectUri): string
            {
                return 'https://provider.test/oauth?state='.$state.'&redirect_uri='.urlencode($redirectUri);
            }

            public function exchangeCode(string $code, string $redirectUri): array
            {
                return [
                    'external_account_id' => 'ig_external_1',
                    'display_name' => 'IG Main',
                    'access_token' => 'access_'.$code,
                    'refresh_token' => 'refresh_'.$code,
                    'expires_at' => now()->addDays(60)->toISOString(),
                    'scopes' => ['user_profile', 'user_media'],
                ];
            }
        };
    }

    /** @return array{Tenant, User} */
    private function tenantUserContext(string $suffix): array
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
            'email' => 'user-'.$suffix.'@example.com',
            'password' => 'secret',
        ]);

        return [$tenant, $user];
    }
}
