<?php

namespace App\Http\Controllers;

use App\Ingestion\ProviderPayloadMapper;
use App\Models\Embed;
use App\Models\MediaItem;
use App\Models\MediaMetricSnapshot;
use App\Models\ProviderAccount;
use App\Security\EmbedAccessTokenService;
use App\Social\IngestionProviderManager;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;

class EmbedController extends Controller
{
    public function __construct(
        private readonly EmbedAccessTokenService $tokenService,
        private readonly AuditLogger $audit,
        private readonly IngestionProviderManager $ingestionProviders,
        private readonly ProviderPayloadMapper $mapper,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $tenantId = (int) $request->query('tenant_id');

        return response()->json([
            'data' => Embed::query()->where('tenant_id', $tenantId)->orderByDesc('id')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tenant_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'widget_type' => ['nullable', 'string', 'max:64'],
            'config' => ['nullable', 'array'],
        ]);

        $embed = Embed::create([
            'tenant_id' => (int) $data['tenant_id'],
            'embed_uid' => (string) Str::uuid(),
            'name' => (string) $data['name'],
            'widget_type' => (string) ($data['widget_type'] ?? 'media-grid'),
            'config' => $data['config'] ?? null,
            'status' => 'active',
        ]);

        return response()->json($embed, 201);
    }

    public function show(Request $request, string $embedId): JsonResponse
    {
        $embed = $this->findTenantEmbed($request, $embedId);
        if (! $embed) {
            return response()->json(['message' => 'Embed not found.', 'error_code' => 'EMBED_NOT_FOUND'], 404);
        }

        return response()->json($embed);
    }

    public function update(Request $request, string $embedId): JsonResponse
    {
        $embed = $this->findTenantEmbed($request, $embedId);
        if (! $embed) {
            return response()->json(['message' => 'Embed not found.', 'error_code' => 'EMBED_NOT_FOUND'], 404);
        }

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'widget_type' => ['nullable', 'string', 'max:64'],
            'config' => ['nullable', 'array'],
            'status' => ['nullable', 'string', 'max:32'],
        ]);

        $embed->update($data);
        Cache::forget($this->runtimeCacheKey($embed->embed_uid));

        return response()->json($embed->fresh());
    }

    public function destroy(Request $request, string $embedId): JsonResponse
    {
        $embed = $this->findTenantEmbed($request, $embedId);
        if (! $embed) {
            return response()->json(['message' => 'Embed not found.', 'error_code' => 'EMBED_NOT_FOUND'], 404);
        }

        Cache::forget($this->runtimeCacheKey($embed->embed_uid));
        $embed->delete();

        return response()->json(['status' => 'deleted']);
    }

    public function issueToken(Request $request, string $embedId): JsonResponse
    {
        $embed = $this->findTenantEmbed($request, $embedId);
        if (! $embed) {
            return response()->json(['message' => 'Embed not found.', 'error_code' => 'EMBED_NOT_FOUND'], 404);
        }

        $data = $request->validate([
            'origin' => ['required', 'url'],
            'ttl_seconds' => ['nullable', 'integer', 'min:10', 'max:900'],
        ]);

        $token = $this->tokenService->issue(
            tenantId: $embed->tenant_id,
            embedUid: $embed->embed_uid,
            origin: (string) $data['origin'],
            widgetType: $embed->widget_type,
            ttlSeconds: (int) ($data['ttl_seconds'] ?? 300),
        );

        return response()->json(['token' => $token]);
    }

    public function runtime(Request $request, string $embedId): Response|JsonResponse
    {
        $embed = Embed::query()->where('embed_uid', $embedId)->first();
        if (! $embed) {
            return response()->json(['message' => 'Embed not found.', 'error_code' => 'EMBED_NOT_FOUND'], 404);
        }

        $token = (string) $request->query('token', '');
        $payload = $this->tokenService->validate($token);

        if (! $payload) {
            return response()->json(['message' => 'Invalid embed token.', 'error_code' => 'EMBED_TOKEN_INVALID'], 401);
        }

        $origin = $this->resolveRequestOrigin($request);

        if (($payload['embed_id'] ?? null) !== $embed->embed_uid || (int) ($payload['tenant_id'] ?? 0) !== $embed->tenant_id) {
            return response()->json(['message' => 'Invalid embed context.', 'error_code' => 'EMBED_TOKEN_CONTEXT_INVALID'], 401);
        }

        if ($origin === '' || $origin !== (string) ($payload['origin'] ?? '')) {
            return response()->json(['message' => 'Origin mismatch.', 'error_code' => 'EMBED_TOKEN_ORIGIN_MISMATCH'], 401);
        }

        $this->audit->log($embed->tenant_id, 'embed.runtime.render', 'embed', $embed->embed_uid, [
            'origin' => $origin,
        ], $request);

        $allowedOrigin = (string) ($payload['origin'] ?? '');
        $runtime = $this->resolveRuntimePayload($embed);

        return response()->view('embed.runtime', [
            'embed' => $embed,
            'origin' => $allowedOrigin,
            'runtime' => $runtime,
        ])->withHeaders([
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; img-src https: data:; frame-ancestors {$allowedOrigin}; base-uri 'none'; form-action 'none';",
            'X-Frame-Options' => 'ALLOWALL',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }

    /** @return array{items:array<int,array<string,mixed>>,source:string,stale:bool,message:?string,updated_at:?string} */
    private function resolveRuntimePayload(Embed $embed): array
    {
        return Cache::remember(
            $this->runtimeCacheKey($embed->embed_uid),
            now()->addSeconds(60),
            fn (): array => $this->buildRuntimePayload($embed),
        );
    }

    /** @return array{items:array<int,array<string,mixed>>,source:string,stale:bool,message:?string,updated_at:?string} */
    private function buildRuntimePayload(Embed $embed): array
    {
        $config = is_array($embed->config) ? $embed->config : [];
        $provider = strtolower((string) ($config['provider'] ?? 'instagram'));
        $limit = max(1, min(30, (int) ($config['limit'] ?? 12)));
        $preferredAccountId = isset($config['provider_account_id']) ? (int) $config['provider_account_id'] : null;

        $databaseItems = $this->queryDatabaseItems($embed, $provider, $limit, $preferredAccountId);
        $latestDatabaseUpdate = $databaseItems->max('updated_at');
        $isFresh = $latestDatabaseUpdate !== null && $latestDatabaseUpdate->greaterThanOrEqualTo(now()->subMinutes(15));

        if ($isFresh && $databaseItems->isNotEmpty()) {
            return [
                'items' => $this->serializeMediaItems($databaseItems),
                'source' => 'database',
                'stale' => false,
                'message' => null,
                'updated_at' => $latestDatabaseUpdate?->toISOString(),
            ];
        }

        try {
            $fromApi = $this->refreshFromApi($embed, $provider, $limit, $preferredAccountId);

            if ($fromApi !== []) {
                return [
                    'items' => $fromApi,
                    'source' => 'api',
                    'stale' => false,
                    'message' => null,
                    'updated_at' => now()->toISOString(),
                ];
            }
        } catch (RuntimeException) {
            if ($databaseItems->isNotEmpty()) {
                return [
                    'items' => $this->serializeMediaItems($databaseItems),
                    'source' => 'degraded',
                    'stale' => true,
                    'message' => 'Exibindo ultimo snapshot disponivel enquanto o provider esta indisponivel.',
                    'updated_at' => $latestDatabaseUpdate?->toISOString(),
                ];
            }
        }

        if ($databaseItems->isNotEmpty()) {
            return [
                'items' => $this->serializeMediaItems($databaseItems),
                'source' => 'degraded',
                'stale' => true,
                'message' => 'Exibindo snapshot desatualizado.',
                'updated_at' => $latestDatabaseUpdate?->toISOString(),
            ];
        }

        return [
            'items' => [],
            'source' => 'empty',
            'stale' => true,
            'message' => 'Nenhum dado disponivel no momento.',
            'updated_at' => null,
        ];
    }

    private function runtimeCacheKey(string $embedUid): string
    {
        return 'embed_runtime:'.$embedUid;
    }

    private function queryDatabaseItems(Embed $embed, string $provider, int $limit, ?int $preferredAccountId): Collection
    {
        $query = MediaItem::query()
            ->where('tenant_id', $embed->tenant_id)
            ->where('provider', $provider);

        if ($preferredAccountId !== null && $preferredAccountId > 0) {
            $query->where('provider_account_id', $preferredAccountId);
        }

        return $query
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /** @return array<int,array<string,mixed>> */
    private function refreshFromApi(Embed $embed, string $provider, int $limit, ?int $preferredAccountId): array
    {
        $accountQuery = ProviderAccount::query()
            ->where('tenant_id', $embed->tenant_id)
            ->where('provider', $provider)
            ->where('status', 'active');

        if ($preferredAccountId !== null && $preferredAccountId > 0) {
            $accountQuery->whereKey($preferredAccountId);
        }

        $account = $accountQuery
            ->orderByDesc('last_synced_at')
            ->orderByDesc('id')
            ->first();

        if (! $account) {
            throw new RuntimeException('No active provider account is connected to this embed.');
        }

        $adapter = $this->ingestionProviders->adapterFor($provider);
        $page = $adapter->fetchMediaPage($account, null, $limit);
        $items = (array) ($page['items'] ?? []);

        foreach ($items as $itemPayload) {
            $canonical = $this->mapper->toCanonical($provider, $embed->tenant_id, $account->id, (array) $itemPayload);

            if ((string) $canonical['external_media_id'] === '') {
                continue;
            }

            $mediaItem = MediaItem::query()->updateOrCreate(
                [
                    'tenant_id' => $embed->tenant_id,
                    'provider' => $provider,
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

            $metrics = (array) ($itemPayload['metrics'] ?? []);

            MediaMetricSnapshot::query()->create([
                'tenant_id' => $embed->tenant_id,
                'media_item_id' => $mediaItem->id,
                'provider' => $provider,
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

        return $this->serializeMediaItems($this->queryDatabaseItems($embed, $provider, $limit, $preferredAccountId));
    }

    /** @return array<int,array<string,mixed>> */
    private function serializeMediaItems(Collection $items): array
    {
        return $items
            ->map(static function (MediaItem $item): array {
                return [
                    'id' => $item->external_media_id,
                    'caption' => $item->caption,
                    'media_type' => $item->media_type,
                    'media_url' => $item->media_url,
                    'permalink' => $item->permalink,
                    'published_at' => $item->published_at?->toISOString(),
                ];
            })
            ->all();
    }

    private function resolveRequestOrigin(Request $request): string
    {
        $origin = (string) $request->header('Origin', '');

        if ($origin !== '') {
            return rtrim($origin, '/');
        }

        $referer = (string) $request->header('Referer', '');
        if ($referer === '') {
            return '';
        }

        $parts = parse_url($referer);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return '';
        }

        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return $parts['scheme'].'://'.$parts['host'].$port;
    }

    private function findTenantEmbed(Request $request, string $embedId): ?Embed
    {
        $tenantId = (int) ($request->input('tenant_id', $request->query('tenant_id')));

        if ($tenantId <= 0 && $request->user()) {
            $tenantId = (int) $request->user()->tenant_id;
        }

        return Embed::query()
            ->where('tenant_id', $tenantId)
            ->where('embed_uid', $embedId)
            ->first();
    }
}
