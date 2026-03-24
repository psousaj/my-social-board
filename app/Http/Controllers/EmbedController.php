<?php

namespace App\Http\Controllers;

use App\Models\Embed;
use App\Security\EmbedAccessTokenService;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EmbedController extends Controller
{
    public function __construct(
        private readonly EmbedAccessTokenService $tokenService,
        private readonly AuditLogger $audit,
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

        return response()->json($embed->fresh());
    }

    public function destroy(Request $request, string $embedId): JsonResponse
    {
        $embed = $this->findTenantEmbed($request, $embedId);
        if (! $embed) {
            return response()->json(['message' => 'Embed not found.', 'error_code' => 'EMBED_NOT_FOUND'], 404);
        }

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

    public function runtime(Request $request, string $embedId): JsonResponse
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

        $origin = (string) $request->header('Origin', '');

        if (($payload['embed_id'] ?? null) !== $embed->embed_uid || (int) ($payload['tenant_id'] ?? 0) !== $embed->tenant_id) {
            return response()->json(['message' => 'Invalid embed context.', 'error_code' => 'EMBED_TOKEN_CONTEXT_INVALID'], 401);
        }

        if ($origin === '' || $origin !== (string) ($payload['origin'] ?? '')) {
            return response()->json(['message' => 'Origin mismatch.', 'error_code' => 'EMBED_TOKEN_ORIGIN_MISMATCH'], 401);
        }

        $this->audit->log($embed->tenant_id, 'embed.runtime.render', 'embed', $embed->embed_uid, [
            'origin' => $origin,
        ], $request);

        return response()->json([
            'embed_id' => $embed->embed_uid,
            'tenant_id' => $embed->tenant_id,
            'widget_type' => $embed->widget_type,
            'config' => $embed->config,
            'status' => 'ok',
        ])->withHeaders([
            'Content-Security-Policy' => "default-src 'none'; frame-ancestors {$origin}; sandbox allow-scripts",
            'X-Frame-Options' => 'ALLOWALL',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }

    private function findTenantEmbed(Request $request, string $embedId): ?Embed
    {
        $tenantId = (int) ($request->input('tenant_id', $request->query('tenant_id')));

        return Embed::query()
            ->where('tenant_id', $tenantId)
            ->where('embed_uid', $embedId)
            ->first();
    }
}
