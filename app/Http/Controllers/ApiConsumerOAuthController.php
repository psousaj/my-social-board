<?php

namespace App\Http\Controllers;

use App\Models\DeveloperClient;
use App\Security\ApiTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ApiConsumerOAuthController extends Controller
{
    public function __construct(private readonly ApiTokenService $tokenService)
    {
    }

    public function token(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tenant_id' => ['required', 'integer'],
            'client_id' => ['required', 'string'],
            'client_secret' => ['required', 'string'],
        ]);

        $client = $this->resolveAuthorizedClient($data);

        if (! $client) {
            return response()->json([
                'message' => 'Client is not authorized.',
                'error_code' => 'CLIENT_UNAUTHORIZED',
            ], 401);
        }

        return response()->json($this->tokenService->issue($client->tenant_id, $client->id));
    }

    public function refresh(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tenant_id' => ['required', 'integer'],
            'client_id' => ['required', 'string'],
            'client_secret' => ['required', 'string'],
            'refresh_token' => ['required', 'string'],
        ]);

        $client = $this->resolveAuthorizedClient($data);

        if (! $client) {
            return response()->json(['message' => 'Client is not authorized.', 'error_code' => 'CLIENT_UNAUTHORIZED'], 401);
        }

        $refreshed = $this->tokenService->refresh($client->tenant_id, $client->id, (string) $data['refresh_token']);

        if (! $refreshed) {
            return response()->json(['message' => 'Invalid refresh token.', 'error_code' => 'REFRESH_TOKEN_INVALID'], 401);
        }

        return response()->json($refreshed);
    }

    public function revoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tenant_id' => ['required', 'integer'],
            'client_id' => ['required', 'string'],
            'client_secret' => ['required', 'string'],
            'refresh_token' => ['required', 'string'],
        ]);

        $client = $this->resolveAuthorizedClient($data);

        if (! $client) {
            return response()->json(['message' => 'Client is not authorized.', 'error_code' => 'CLIENT_UNAUTHORIZED'], 401);
        }

        $ok = $this->tokenService->revokeByRefreshToken($client->tenant_id, $client->id, (string) $data['refresh_token']);

        return response()->json(['revoked' => $ok]);
    }

    /** @param array<string,mixed> $data */
    private function resolveAuthorizedClient(array $data): ?DeveloperClient
    {
        $client = DeveloperClient::query()
            ->where('tenant_id', (int) $data['tenant_id'])
            ->where('client_id', (string) $data['client_id'])
            ->where('status', 'active')
            ->whereNull('revoked_at')
            ->first();

        if (! $client) {
            return null;
        }

        $secret = (string) $data['client_secret'];

        if (Hash::check($secret, $client->primary_secret_hash)) {
            return $client;
        }

        if (
            $client->secondary_secret_hash !== null
            && $client->secondary_expires_at !== null
            && $client->secondary_expires_at->isFuture()
            && Hash::check($secret, $client->secondary_secret_hash)
        ) {
            return $client;
        }

        return null;
    }
}
