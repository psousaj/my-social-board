<?php

namespace App\Http\Controllers;

use App\Billing\TenantQuotaService;
use App\Ingestion\Exceptions\TransientIngestionException;
use App\Ingestion\ProviderPayloadMapper;
use App\Models\IngestionJob;
use App\Models\MediaItem;
use App\Models\MediaMetricSnapshot;
use App\Models\ProviderAccount;
use App\Models\ProviderToken;
use App\Social\IngestionProviderManager;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class IngestionController extends Controller
{
    public function __construct(
        private readonly IngestionProviderManager $providerManager,
        private readonly ProviderPayloadMapper $mapper,
        private readonly TenantQuotaService $quotaService,
        private readonly AuditLogger $audit,
    ) {
    }

    public function run(Request $request, string $provider): JsonResponse
    {
        $data = $request->validate([
            'tenant_id' => ['required', 'integer'],
            'provider_account_id' => ['required', 'integer'],
            'simulate_retry_failures' => ['nullable', 'integer', 'min:0', 'max:5'],
            'simulate_terminal_failure' => ['nullable', 'boolean'],
        ]);

        $provider = strtolower($provider);
        $tenantId = (int) $data['tenant_id'];
        $quotas = $this->quotaService->quotasForTenant($tenantId);
        $periodStart = now()->startOfMonth();
        $currentCount = MediaItem::query()
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', $periodStart)
            ->count();

        if ($currentCount >= $quotas['monthly_media_ingestion']) {
            return response()->json([
                'message' => 'Ingestion quota exceeded.',
                'error_code' => 'INGESTION_QUOTA_EXCEEDED',
            ], 429);
        }

        $account = ProviderAccount::query()
            ->whereKey((int) $data['provider_account_id'])
            ->where('tenant_id', $tenantId)
            ->where('provider', $provider)
            ->first();

        if (! $account) {
            return response()->json([
                'message' => 'Invalid ingestion context.',
                'error_code' => 'INGESTION_CONTEXT_INVALID',
            ], 422);
        }

        $job = IngestionJob::create([
            'tenant_id' => $tenantId,
            'provider_account_id' => $account->id,
            'provider' => $provider,
            'state' => 'running',
            'attempts' => 0,
            'max_attempts' => 3,
            'started_at' => now(),
        ]);

        $retryFailures = (int) ($data['simulate_retry_failures'] ?? 0);
        $terminalFailure = (bool) ($data['simulate_terminal_failure'] ?? false);
        $adapter = $this->providerManager->adapterFor($provider);

        $token = ProviderToken::query()
            ->where('tenant_id', $tenantId)
            ->where('provider_account_id', $account->id)
            ->where('provider', $provider)
            ->whereNull('revoked_at')
            ->latest('id')
            ->first();

        if ($token) {
            $adapter->refreshToken($token, false);
        }

        try {
            for ($attempt = 1; $attempt <= $job->max_attempts; $attempt++) {
                $job->update(['attempts' => $attempt]);

                try {
                    if ($terminalFailure) {
                        throw new RuntimeException('Simulated terminal provider failure.');
                    }

                    if ($attempt <= $retryFailures) {
                        throw new TransientIngestionException('Simulated transient ingestion failure.');
                    }

                    $this->ingestAccount($adapter, $account, $provider, $tenantId);

                    $job->update([
                        'state' => 'succeeded',
                        'error_code' => null,
                        'error_message' => null,
                        'completed_at' => now(),
                    ]);

                    $this->audit->log($tenantId, 'ingestion.run.succeeded', 'ingestion_job', (string) $job->id, [
                        'attempts' => $job->attempts,
                        'provider' => $provider,
                    ], $request);

                    return response()->json([
                        'id' => $job->id,
                        'state' => $job->state,
                        'attempts' => $job->attempts,
                    ]);
                } catch (TransientIngestionException $exception) {
                    if ($attempt >= $job->max_attempts) {
                        throw $exception;
                    }
                }
            }
        } catch (TransientIngestionException $exception) {
            $job->update([
                'state' => 'failed',
                'error_code' => 'INGESTION_RETRY_EXHAUSTED',
                'error_message' => $exception->getMessage(),
                'completed_at' => now(),
            ]);

            $this->audit->log($tenantId, 'ingestion.run.failed', 'ingestion_job', (string) $job->id, [
                'error_code' => 'INGESTION_RETRY_EXHAUSTED',
            ], $request);

            return response()->json([
                'id' => $job->id,
                'state' => 'failed',
                'attempts' => $job->attempts,
                'error_code' => 'INGESTION_RETRY_EXHAUSTED',
            ], 503);
        } catch (RuntimeException $exception) {
            $job->update([
                'state' => 'failed',
                'error_code' => 'INGESTION_TERMINAL_FAILURE',
                'error_message' => $exception->getMessage(),
                'completed_at' => now(),
            ]);

            $this->audit->log($tenantId, 'ingestion.run.failed', 'ingestion_job', (string) $job->id, [
                'error_code' => 'INGESTION_TERMINAL_FAILURE',
            ], $request);

            return response()->json([
                'id' => $job->id,
                'state' => 'failed',
                'attempts' => $job->attempts,
                'error_code' => 'INGESTION_TERMINAL_FAILURE',
            ], 502);
        }

        $job->update([
            'state' => 'failed',
            'error_code' => 'INGESTION_RETRY_EXHAUSTED',
            'error_message' => 'Retries exhausted.',
            'completed_at' => now(),
        ]);

        return response()->json([
            'id' => $job->id,
            'state' => 'failed',
            'attempts' => $job->attempts,
            'error_code' => 'INGESTION_RETRY_EXHAUSTED',
        ], 503);
    }

    public function jobs(Request $request, string $provider): JsonResponse
    {
        $tenantId = (int) $request->query('tenant_id');

        $jobs = IngestionJob::query()
            ->where('tenant_id', $tenantId)
            ->where('provider', strtolower($provider))
            ->orderByDesc('id')
            ->limit(50)
            ->get(['id', 'state', 'attempts', 'max_attempts', 'error_code', 'created_at', 'completed_at']);

        return response()->json([
            'data' => $jobs,
        ]);
    }

    public function showJob(Request $request, string $provider, int $jobId): JsonResponse
    {
        $tenantId = (int) $request->query('tenant_id');

        $job = IngestionJob::query()
            ->where('id', $jobId)
            ->where('tenant_id', $tenantId)
            ->where('provider', strtolower($provider))
            ->first();

        if (! $job) {
            return response()->json([
                'message' => 'Job not found.',
                'error_code' => 'INGESTION_JOB_NOT_FOUND',
            ], 404);
        }

        return response()->json([
            'id' => $job->id,
            'state' => $job->state,
            'attempts' => $job->attempts,
            'max_attempts' => $job->max_attempts,
            'error_code' => $job->error_code,
            'error_message' => $job->error_message,
            'started_at' => optional($job->started_at)?->toISOString(),
            'completed_at' => optional($job->completed_at)?->toISOString(),
        ]);
    }

    private function ingestAccount(mixed $adapter, ProviderAccount $account, string $provider, int $tenantId): void
    {
        $cursor = null;
        $pages = 0;

        do {
            $page = $adapter->fetchMediaPage($account, $cursor, 2);
            $pages++;

            foreach ((array) ($page['items'] ?? []) as $itemPayload) {
                $canonical = $this->mapper->toCanonical($provider, $tenantId, $account->id, (array) $itemPayload);

                $mediaItem = MediaItem::query()->updateOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'provider' => $provider,
                        'external_media_id' => $canonical['external_media_id'],
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

                $metrics = (array) (($itemPayload['metrics'] ?? []));

                MediaMetricSnapshot::query()->create([
                    'tenant_id' => $tenantId,
                    'media_item_id' => $mediaItem->id,
                    'provider' => $provider,
                    'likes_count' => (int) ($metrics['likes'] ?? 0),
                    'comments_count' => (int) ($metrics['comments'] ?? 0),
                    'snapshot_at' => now(),
                    'raw_snapshot' => $metrics,
                ]);
            }

            $cursor = $page['next_cursor'] ?? null;
        } while ($cursor !== null && $pages < 10);
    }
}
