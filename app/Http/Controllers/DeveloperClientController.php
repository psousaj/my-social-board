<?php

namespace App\Http\Controllers;

use App\Models\DeveloperClient;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class DeveloperClientController extends Controller
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $tenantId = (int) $request->query('tenant_id');

        return response()->json([
            'data' => DeveloperClient::query()->where('tenant_id', $tenantId)->orderByDesc('id')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tenant_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $secret = bin2hex(random_bytes(24));
        $clientId = 'cli_'.bin2hex(random_bytes(8));

        $client = DeveloperClient::create([
            'tenant_id' => (int) $data['tenant_id'],
            'name' => (string) $data['name'],
            'client_id' => $clientId,
            'primary_secret_hash' => Hash::make($secret),
            'status' => 'active',
        ]);

        return response()->json([
            'id' => $client->id,
            'tenant_id' => $client->tenant_id,
            'name' => $client->name,
            'client_id' => $client->client_id,
            'client_secret' => $secret,
        ], 201);
    }

    public function update(Request $request, string $clientId): JsonResponse
    {
        $client = $this->findTenantClient($request, $clientId);

        if (! $client) {
            return response()->json(['message' => 'Client not found.', 'error_code' => 'CLIENT_NOT_FOUND'], 404);
        }

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $client->update($data);

        return response()->json($client->fresh());
    }

    public function destroy(Request $request, string $clientId): JsonResponse
    {
        $client = $this->findTenantClient($request, $clientId);

        if (! $client) {
            return response()->json(['message' => 'Client not found.', 'error_code' => 'CLIENT_NOT_FOUND'], 404);
        }

        $client->delete();

        return response()->json(['status' => 'deleted']);
    }

    public function rotateSecret(Request $request, string $clientId): JsonResponse
    {
        $client = $this->findTenantClient($request, $clientId);

        if (! $client) {
            return response()->json(['message' => 'Client not found.', 'error_code' => 'CLIENT_NOT_FOUND'], 404);
        }

        $data = $request->validate([
            'overlap_seconds' => ['nullable', 'integer', 'min:60', 'max:3600'],
        ]);

        $newSecret = bin2hex(random_bytes(24));
        $client->update([
            'secondary_secret_hash' => $client->primary_secret_hash,
            'secondary_expires_at' => now()->addSeconds((int) ($data['overlap_seconds'] ?? 600)),
            'primary_secret_hash' => Hash::make($newSecret),
        ]);

        $this->audit->log($client->tenant_id, 'developer_client.secret_rotated', 'developer_client', $client->client_id, null, $request);

        return response()->json([
            'client_id' => $client->client_id,
            'new_client_secret' => $newSecret,
            'overlap_until' => $client->fresh()->secondary_expires_at?->toISOString(),
        ]);
    }

    public function revoke(Request $request, string $clientId): JsonResponse
    {
        $client = $this->findTenantClient($request, $clientId);

        if (! $client) {
            return response()->json(['message' => 'Client not found.', 'error_code' => 'CLIENT_NOT_FOUND'], 404);
        }

        $client->update([
            'revoked_at' => now(),
            'status' => 'revoked',
        ]);

        $this->audit->log($client->tenant_id, 'developer_client.revoked', 'developer_client', $client->client_id, null, $request);

        return response()->json(['status' => 'revoked']);
    }

    private function findTenantClient(Request $request, string $clientId): ?DeveloperClient
    {
        $tenantId = (int) ($request->input('tenant_id', $request->query('tenant_id')));

        return DeveloperClient::query()
            ->where('tenant_id', $tenantId)
            ->where('client_id', $clientId)
            ->first();
    }
}
