<?php

namespace App\Console\Commands;

use App\Ingestion\ProviderPayloadMapper;
use App\Models\MediaItem;
use App\Models\MediaMetricSnapshot;
use App\Models\ProviderAccount;
use App\Social\IngestionProviderManager;
use Illuminate\Console\Command;
use RuntimeException;

class PollInstagramIngestionFallbackCommand extends Command
{
    protected $signature = 'ingestion:poll-instagram-fallback {--stale-minutes=15} {--limit=50}';

    protected $description = 'Poll stale Instagram provider accounts as webhook fallback ingestion.';

    public function handle(IngestionProviderManager $providers, ProviderPayloadMapper $mapper): int
    {
        $staleMinutes = max(1, (int) $this->option('stale-minutes'));
        $limit = max(1, min(500, (int) $this->option('limit')));
        $cutoff = now()->subMinutes($staleMinutes);

        $accounts = ProviderAccount::query()
            ->where('provider', 'instagram')
            ->where('status', 'active')
            ->where(function ($query) use ($cutoff): void {
                $query->whereNull('last_synced_at')
                    ->orWhere('last_synced_at', '<=', $cutoff);
            })
            ->orderBy('last_synced_at')
            ->limit($limit)
            ->get();

        /** @var \Illuminate\Support\Collection<int,ProviderAccount> $accounts */

        $adapter = $providers->adapterFor('instagram');
        $synced = 0;
        $errors = 0;

        foreach ($accounts as $account) {
            /** @var ProviderAccount $account */
            try {
                $page = $adapter->fetchMediaPage($account, null, 12);

                foreach ((array) ($page['items'] ?? []) as $itemPayload) {
                    $canonical = $mapper->toCanonical(
                        provider: 'instagram',
                        tenantId: $account->tenant_id,
                        providerAccountId: $account->id,
                        payload: (array) $itemPayload,
                    );

                    if ((string) $canonical['external_media_id'] === '') {
                        continue;
                    }

                    $mediaItem = MediaItem::query()->updateOrCreate(
                        [
                            'tenant_id' => $account->tenant_id,
                            'provider' => 'instagram',
                            'external_media_id' => (string) $canonical['external_media_id'],
                        ],
                        [
                            'provider_account_id' => $account->id,
                            'caption' => $canonical['caption'],
                            'media_type' => $canonical['media_type'],
                            'media_url' => $canonical['media_url'],
                            'permalink' => $canonical['permalink'],
                            'published_at' => $canonical['published_at'],
                            'raw_payload' => $canonical['raw_payload'],
                        ],
                    );

                    $metrics = (array) (((array) $itemPayload)['metrics'] ?? []);

                    MediaMetricSnapshot::query()->create([
                        'tenant_id' => $account->tenant_id,
                        'media_item_id' => $mediaItem->id,
                        'provider' => 'instagram',
                        'likes_count' => (int) ($metrics['likes'] ?? 0),
                        'comments_count' => (int) ($metrics['comments'] ?? 0),
                        'snapshot_at' => now(),
                        'raw_snapshot' => $metrics,
                    ]);
                }

                $account->update([
                    'last_synced_at' => now(),
                    'status' => 'active',
                ]);

                $synced++;
            } catch (RuntimeException $exception) {
                $errors++;
                $this->warn(sprintf(
                    'poll fallback failed provider_account=%d error=%s',
                    $account->id,
                    $exception->getMessage(),
                ));
            }
        }

        $this->info(sprintf('ingestion:poll-instagram-fallback synced=%d errors=%d', $synced, $errors));

        return self::SUCCESS;
    }
}
