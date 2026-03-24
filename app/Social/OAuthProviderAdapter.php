<?php

namespace App\Social;

interface OAuthProviderAdapter
{
    public function authorizationUrl(string $state, string $redirectUri): string;

    /**
     * @return array{external_account_id:string,display_name:?string,access_token:string,refresh_token:?string,expires_at:?string,scopes:array<int,string>}
     */
    public function exchangeCode(string $code, string $redirectUri): array;
}
