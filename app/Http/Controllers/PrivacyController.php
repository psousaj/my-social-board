<?php

namespace App\Http\Controllers;

use App\Models\DeveloperClient;
use App\Models\Embed;
use App\Models\MediaItem;
use App\Models\ProviderAccount;
use App\Models\ProviderToken;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PrivacyController extends Controller
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function export(Request $request): JsonResponse
    {
        $tenantId = (int) $request->input('tenant_id');

        $payload = [
            'tenant_id' => $tenantId,
            'provider_accounts_count' => ProviderAccount::query()->where('tenant_id', $tenantId)->count(),
            'media_items_count' => MediaItem::query()->where('tenant_id', $tenantId)->count(),
            'embeds_count' => Embed::query()->where('tenant_id', $tenantId)->count(),
            'developer_clients_count' => DeveloperClient::query()->where('tenant_id', $tenantId)->count(),
        ];

        $this->audit->log($tenantId, 'privacy.export', 'tenant', (string) $tenantId, $payload, $request);

        return response()->json($payload);
    }

    public function deauthorize(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tenant_id' => ['required', 'integer'],
            'provider' => ['nullable', 'string', 'max:32'],
        ]);

        $tenantId = (int) $data['tenant_id'];
        $provider = isset($data['provider']) ? strtolower((string) $data['provider']) : null;

        $query = ProviderToken::query()->where('tenant_id', $tenantId)->whereNull('revoked_at');
        if ($provider !== null) {
            $query->where('provider', $provider);
        }

        $revoked = $query->update(['revoked_at' => now(), 'updated_at' => now()]);

        $consentQuery = DB::table('oauth_consents')->where('tenant_id', $tenantId)->whereNull('revoked_at');
        if ($provider !== null) {
            $consentQuery->where('provider', $provider);
        }
        $consentQuery->update(['revoked_at' => now(), 'updated_at' => now()]);

        $this->audit->log($tenantId, 'privacy.deauthorize', 'tenant', (string) $tenantId, [
            'provider' => $provider,
            'tokens_revoked' => $revoked,
        ], $request);

        return response()->json(['revoked_tokens' => $revoked]);
    }

    public function deleteData(Request $request): JsonResponse
    {
        $tenantId = (int) $request->input('tenant_id');

        DB::transaction(function () use ($tenantId): void {
            DB::table('embeds')->where('tenant_id', $tenantId)->delete();
            DB::table('media_metric_snapshots')->where('tenant_id', $tenantId)->delete();
            DB::table('media_items')->where('tenant_id', $tenantId)->delete();
            DB::table('ingestion_jobs')->where('tenant_id', $tenantId)->delete();
            DB::table('api_access_tokens')->where('tenant_id', $tenantId)->delete();
            DB::table('api_refresh_tokens')->where('tenant_id', $tenantId)->delete();
            DB::table('developer_clients')->where('tenant_id', $tenantId)->delete();
            DB::table('provider_tokens')->where('tenant_id', $tenantId)->delete();
            DB::table('oauth_consents')->where('tenant_id', $tenantId)->delete();
            DB::table('provider_accounts')->where('tenant_id', $tenantId)->delete();
        });

        $this->audit->log($tenantId, 'privacy.delete', 'tenant', (string) $tenantId, null, $request);

        return response()->json(['status' => 'deleted']);
    }
}
