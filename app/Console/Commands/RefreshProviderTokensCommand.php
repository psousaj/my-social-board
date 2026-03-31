<?php

namespace App\Console\Commands;

use App\Models\ProviderToken;
use App\Social\IngestionProviderManager;
use Illuminate\Console\Command;
use RuntimeException;

class RefreshProviderTokensCommand extends Command
{
    protected $signature = 'providers:refresh-tokens {--provider=instagram}';

    protected $description = 'Refresh long-lived provider tokens and mark accounts for reauth when refresh fails near expiry.';

    public function handle(IngestionProviderManager $providers): int
    {
        $provider = strtolower((string) $this->option('provider'));
        $adapter = $providers->adapterFor($provider);

        $tokens = ProviderToken::query()
            ->with('providerAccount')
            ->where('provider', $provider)
            ->whereNull('revoked_at')
            ->orderBy('id')
            ->get();

        /** @var \Illuminate\Support\Collection<int,ProviderToken> $tokens */

        $refreshed = 0;
        $failed = 0;

        foreach ($tokens as $token) {
            /** @var ProviderToken $token */
            try {
                $adapter->refreshToken($token, true);
                $refreshed++;
            } catch (RuntimeException $exception) {
                $failed++;

                $expiresSoon = $token->expires_at !== null && $token->expires_at->lessThanOrEqualTo(now()->addDay());
                if ($expiresSoon && $token->providerAccount) {
                    $token->providerAccount->update(['status' => 'reauth_required']);
                }

                $this->warn(sprintf(
                    'refresh failed token=%d provider_account=%d error=%s',
                    $token->id,
                    (int) ($token->provider_account_id ?? 0),
                    $exception->getMessage(),
                ));
            }
        }

        $this->info(sprintf('providers:refresh-tokens refreshed=%d failed=%d', $refreshed, $failed));

        return self::SUCCESS;
    }
}
