<?php

namespace App\Http\Controllers;

use App\Ingestion\ProviderPayloadMapper;
use App\Models\MediaItem;
use App\Models\MediaMetricSnapshot;
use App\Models\ProviderAccount;
use App\Social\IngestionProviderManager;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RuntimeException;

class InstagramWebhookController extends Controller
{
    public function __construct(
        private readonly IngestionProviderManager $providers,
        private readonly ProviderPayloadMapper $mapper,
        private readonly AuditLogger $audit,
    ) {
    }

    public function verify(Request $request): Response
    {
        $mode = (string) ($request->query('hub.mode', $request->query('hub_mode', '')));
        $token = (string) ($request->query('hub.verify_token', $request->query('hub_verify_token', '')));
        $challenge = (string) ($request->query('hub.challenge', $request->query('hub_challenge', '')));
        $expected = (string) config('services.instagram.webhook_verify_token', '');

        if ($mode !== 'subscribe' || $challenge === '' || $expected === '' || ! hash_equals($expected, $token)) {
            return response('forbidden', 403);
        }

        return response($challenge, 200)->header('Content-Type', 'text/plain');
    }

    public function receive(Request $request): JsonResponse
    {
        if (! $this->isValidSignature($request)) {
            return response()->json([
                'message' => 'Invalid webhook signature.',
                'error_code' => 'WEBHOOK_SIGNATURE_INVALID',
            ], 401);
        }

        $payload = $request->all();
        $accountIds = $this->extractAccountIds($payload);

        if ($accountIds === []) {
            return response()->json(['status' => 'accepted', 'synced_accounts' => 0, 'errors' => 0], 202);
        }

        $accounts = ProviderAccount::query()
            ->where('provider', 'instagram')
            ->where('status', 'active')
            ->whereIn('external_account_id', $accountIds)
            ->get();

        /** @var \Illuminate\Support\Collection<int,ProviderAccount> $accounts */

        $adapter = $this->providers->adapterFor('instagram');
        $synced = 0;
        $errors = 0;

        foreach ($accounts as $account) {
            /** @var ProviderAccount $account */
            try {
                $page = $adapter->fetchMediaPage($account, null, 12);
                $written = $this->persistItems($account, (array) ($page['items'] ?? []));

                $account->update(['last_synced_at' => now(), 'status' => 'active']);

                $this->audit->log($account->tenant_id, 'webhook.instagram.sync.succeeded', 'provider_account', (string) $account->id, [
                    'items_written' => $written,
                ], $request);

                $synced++;
            } catch (RuntimeException $exception) {
                $errors++;

                $this->audit->log($account->tenant_id, 'webhook.instagram.sync.failed', 'provider_account', (string) $account->id, [
                    'error' => $exception->getMessage(),
                ], $request);
            }
        }

        return response()->json([
            'status' => 'accepted',
            'synced_accounts' => $synced,
            'errors' => $errors,
        ], 202);
    }

    private function isValidSignature(Request $request): bool
    {
        $secret = (string) config('services.instagram.webhook_app_secret', '');

        if ($secret === '') {
            return true;
        }

        $signature = (string) $request->header('X-Hub-Signature-256', '');

        if (! str_starts_with($signature, 'sha256=')) {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', (string) $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }

    /** @param array<string,mixed> $payload */
    private function extractAccountIds(array $payload): array
    {
        $ids = [];

        $entries = $payload['entry'] ?? [];

        if (! is_array($entries)) {
            return [];
        }

        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $entryId = (string) ($entry['id'] ?? '');
            if ($entryId !== '') {
                $ids[] = $entryId;
            }

            $changes = $entry['changes'] ?? [];

            if (! is_array($changes)) {
                continue;
            }

            foreach ($changes as $change) {
                if (! is_array($change)) {
                    continue;
                }

                $value = $change['value'] ?? [];
                if (! is_array($value)) {
                    continue;
                }

                $from = $value['from'] ?? [];
                $fromId = is_array($from) ? (string) ($from['id'] ?? '') : '';
                $accountId = (string) ($value['id'] ?? $fromId);

                if ($accountId !== '') {
                    $ids[] = $accountId;
                }
            }
        }

        return array_values(array_unique(array_filter($ids, static fn (string $value): bool => $value !== '')));
    }

    /** @param array<int,mixed> $items */
    private function persistItems(ProviderAccount $account, array $items): int
    {
        $written = 0;

        foreach ($items as $itemPayload) {
            $canonical = $this->mapper->toCanonical('instagram', $account->tenant_id, $account->id, (array) $itemPayload);

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

            $written++;
        }

        return $written;
    }
}
