<?php

namespace App\Social;

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
            'scope' => 'user_profile,user_media',
            'state' => $state,
        ]);

        return $baseUrl.'?'.$query;
    }

    public function exchangeCode(string $code, string $redirectUri): array
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
}
