<?php

namespace App\Social;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class InstagramOAuthAdapter implements OAuthProviderAdapter
{
    public function authorizationUrl(string $state, string $redirectUri): string
    {
        $clientId = (string) config('services.instagram.client_id', '');

        if ($clientId === '') {
            throw new RuntimeException('Missing Instagram client id configuration.');
        }

        $baseUrl = (string) config('services.instagram.authorize_url', 'https://api.instagram.com/oauth/authorize');

        $query = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => implode(',', $this->configuredScopes()),
            'state' => $state,
        ]);

        return $baseUrl.'?'.$query;
    }

    public function exchangeCode(string $code, string $redirectUri): array
    {
        if ($this->useMockMode()) {
            return $this->mockTokenPayload($code);
        }

        $clientId = $this->requiredConfig('services.instagram.client_id', 'Missing Instagram client id configuration.');
        $clientSecret = $this->requiredConfig('services.instagram.client_secret', 'Missing Instagram client secret configuration.');
        $tokenUrl = $this->requiredConfig('services.instagram.token_url', 'Missing Instagram token url configuration.');

        $tokenResponse = Http::asForm()
            ->acceptJson()
            ->timeout(15)
            ->post($tokenUrl, [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'grant_type' => 'authorization_code',
                'redirect_uri' => $redirectUri,
                'code' => $code,
            ]);

        if (! $tokenResponse->successful()) {
            throw new RuntimeException($this->responseError($tokenResponse, 'Instagram code exchange failed.'));
        }

        $shortToken = (string) $tokenResponse->json('access_token', '');
        $userId = (string) $tokenResponse->json('user_id', '');

        if ($shortToken === '') {
            throw new RuntimeException('Instagram code exchange did not return an access token.');
        }

        $longResponse = Http::acceptJson()
            ->timeout(15)
            ->get($this->graphUrl('/access_token'), [
                'grant_type' => 'ig_exchange_token',
                'client_secret' => $clientSecret,
                'access_token' => $shortToken,
            ]);

        if (! $longResponse->successful()) {
            throw new RuntimeException($this->responseError($longResponse, 'Instagram long-lived exchange failed.'));
        }

        $longToken = (string) $longResponse->json('access_token', '');
        $expiresIn = (int) $longResponse->json('expires_in', 0);

        if ($longToken === '') {
            throw new RuntimeException('Instagram long-lived exchange did not return an access token.');
        }

        $profileResponse = Http::acceptJson()
            ->timeout(15)
            ->get($this->graphUrl('/me'), [
                'fields' => 'id,username',
                'access_token' => $longToken,
            ]);

        if (! $profileResponse->successful()) {
            throw new RuntimeException($this->responseError($profileResponse, 'Instagram profile request failed.'));
        }

        $externalAccountId = (string) $profileResponse->json('id', $userId);
        if ($externalAccountId === '') {
            throw new RuntimeException('Instagram profile payload is missing account id.');
        }

        $displayName = (string) $profileResponse->json('username', '');
        $expiresAt = $expiresIn > 0 ? now()->addSeconds($expiresIn)->toISOString() : null;

        return [
            'external_account_id' => $externalAccountId,
            'display_name' => $displayName !== '' ? $displayName : null,
            'access_token' => $longToken,
            'refresh_token' => null,
            'expires_at' => $expiresAt,
            'scopes' => $this->configuredScopes(),
        ];
    }

    /** @return array<int,string> */
    private function configuredScopes(): array
    {
        $scopes = config('services.instagram.scopes', ['user_profile', 'user_media']);

        if (! is_array($scopes)) {
            return ['user_profile', 'user_media'];
        }

        $values = array_values(array_filter(array_map(static fn (mixed $scope): string => trim((string) $scope), $scopes)));

        return $values !== [] ? $values : ['user_profile', 'user_media'];
    }

    private function graphUrl(string $path): string
    {
        $base = $this->requiredConfig('services.instagram.graph_url', 'Missing Instagram graph url configuration.');

        return rtrim($base, '/').'/'.ltrim($path, '/');
    }

    private function useMockMode(): bool
    {
        return (bool) config('services.instagram.use_mock', false);
    }

    /** @return array{external_account_id:string,display_name:?string,access_token:string,refresh_token:?string,expires_at:?string,scopes:array<int,string>} */
    private function mockTokenPayload(string $code): array
    {
        if (str_starts_with($code, 'fail_')) {
            throw new RuntimeException('Token exchange failed for provider response.');
        }

        return [
            'external_account_id' => 'ig_'.substr(sha1($code), 0, 12),
            'display_name' => 'Instagram Account',
            'access_token' => 'access_'.$code,
            'refresh_token' => 'refresh_'.$code,
            'expires_at' => now()->addDays(60)->toISOString(),
            'scopes' => ['user_profile', 'user_media'],
        ];
    }

    private function requiredConfig(string $key, string $message): string
    {
        $value = (string) config($key, '');

        if ($value === '') {
            throw new RuntimeException($message);
        }

        return $value;
    }

    private function responseError(Response $response, string $fallback): string
    {
        $error = $response->json('error.message')
            ?? $response->json('error_description')
            ?? $response->json('message');

        if (is_string($error) && $error !== '') {
            return $error;
        }

        return $fallback;
    }
}
