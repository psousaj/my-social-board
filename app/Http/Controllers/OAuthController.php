<?php

namespace App\Http\Controllers;

use App\Models\OauthState;
use App\Models\ProviderAccount;
use App\Models\ProviderToken;
use App\Models\User;
use App\Social\SocialProviderManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class OAuthController extends Controller
{
    public function __construct(
        private readonly SocialProviderManager $providerManager,
    ) {
    }

    public function start(Request $request, string $provider): RedirectResponse|JsonResponse
    {
        $tenantId = (int) $request->query('tenant_id');
        $userId = (int) $request->query('user_id');

        $user = User::query()
            ->whereKey($userId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $user) {
            return response()->json(['message' => 'Invalid tenant/user context.'], 422);
        }

        $state = bin2hex(random_bytes(32));

        OauthState::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'provider' => strtolower($provider),
            'state_hash' => hash('sha256', $state),
            'expires_at' => now()->addMinutes(10),
        ]);

        $redirectUri = route('oauth.callback', ['provider' => $provider]);
        $authorizationUrl = $this->providerManager
            ->adapterFor($provider)
            ->authorizationUrl($state, $redirectUri);

        return redirect()->away($authorizationUrl);
    }

    public function callback(Request $request, string $provider): JsonResponse
    {
        $state = (string) $request->query('state', '');
        $code = (string) $request->query('code', '');

        if ($state === '' || $code === '') {
            return response()->json(['message' => 'Missing OAuth callback parameters.'], 422);
        }

        $stateRecord = OauthState::query()
            ->where('provider', strtolower($provider))
            ->where('state_hash', hash('sha256', $state))
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->lockForUpdate()
            ->first();

        if (! $stateRecord) {
            return response()->json(['message' => 'Unauthorized OAuth state.'], 401);
        }

        $stateRecord->update(['used_at' => now()]);

        try {
            $tokenData = $this->providerManager
                ->adapterFor($provider)
                ->exchangeCode($code, route('oauth.callback', ['provider' => $provider]));
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => 'Provider token exchange failed.',
                'error' => $exception->getMessage(),
            ], 502);
        }

        $providerAccount = ProviderAccount::query()->updateOrCreate(
            [
                'tenant_id' => $stateRecord->tenant_id,
                'provider' => strtolower($provider),
                'external_account_id' => $tokenData['external_account_id'],
            ],
            [
                'user_id' => $stateRecord->user_id,
                'display_name' => $tokenData['display_name'],
                'status' => 'active',
            ],
        );

        \DB::table('oauth_consents')->updateOrInsert(
            [
                'tenant_id' => $stateRecord->tenant_id,
                'provider_account_id' => $providerAccount->id,
                'provider' => strtolower($provider),
            ],
            [
                'scopes' => json_encode($tokenData['scopes'], JSON_THROW_ON_ERROR),
                'consented_at' => now(),
                'revoked_at' => null,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        ProviderToken::query()->updateOrCreate(
            [
                'tenant_id' => $stateRecord->tenant_id,
                'provider_account_id' => $providerAccount->id,
                'provider' => strtolower($provider),
            ],
            [
                'token_type' => 'bearer',
                'access_token' => $tokenData['access_token'],
                'refresh_token' => $tokenData['refresh_token'],
                'expires_at' => $tokenData['expires_at'],
                'revoked_at' => null,
            ],
        );

        return response()->json([
            'status' => 'connected',
            'provider_account_id' => $providerAccount->id,
        ]);
    }

    public function status(Request $request, string $provider): JsonResponse
    {
        $tenantId = (int) $request->query('tenant_id');
        $userId = (int) $request->query('user_id');

        $providerAccount = ProviderAccount::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('provider', strtolower($provider))
            ->first();

        if (! $providerAccount) {
            return response()->json(['connected' => false, 'state' => 'disconnected']);
        }

        $hasConsent = \DB::table('oauth_consents')
            ->where('tenant_id', $tenantId)
            ->where('provider_account_id', $providerAccount->id)
            ->where('provider', strtolower($provider))
            ->whereNull('revoked_at')
            ->exists();

        $hasToken = ProviderToken::query()
            ->where('tenant_id', $tenantId)
            ->where('provider_account_id', $providerAccount->id)
            ->where('provider', strtolower($provider))
            ->whereNull('revoked_at')
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->exists();

        if ($hasConsent && $hasToken) {
            return response()->json(['connected' => true, 'state' => 'connected']);
        }

        return response()->json(['connected' => false, 'state' => 'revoked']);
    }

    public function revoke(Request $request, string $provider): JsonResponse
    {
        $tenantId = (int) $request->input('tenant_id');
        $userId = (int) $request->input('user_id');

        $providerAccount = ProviderAccount::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('provider', strtolower($provider))
            ->first();

        if (! $providerAccount) {
            return response()->json(['status' => 'ok', 'state' => 'disconnected']);
        }

        ProviderToken::query()
            ->where('tenant_id', $tenantId)
            ->where('provider_account_id', $providerAccount->id)
            ->where('provider', strtolower($provider))
            ->update([
                'revoked_at' => now(),
                'updated_at' => now(),
            ]);

        \DB::table('oauth_consents')
            ->where('tenant_id', $tenantId)
            ->where('provider_account_id', $providerAccount->id)
            ->where('provider', strtolower($provider))
            ->update([
                'revoked_at' => now(),
                'updated_at' => now(),
            ]);

        $providerAccount->update(['status' => 'revoked']);

        return response()->json(['status' => 'ok', 'state' => 'revoked']);
    }
}
