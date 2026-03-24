<?php

namespace App\Http\Middleware;

use App\Billing\EdgeCounter;
use App\Billing\TenantQuotaService;
use App\Models\ApiAccessToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiConsumerAuthMiddleware
{
    public function __construct(
        private readonly EdgeCounter $counter,
        private readonly TenantQuotaService $quotaService,
    ) {
    }

    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $auth = (string) $request->header('Authorization', '');
        if (! str_starts_with($auth, 'Bearer ')) {
            return response()->json([
                'message' => 'Missing bearer token.',
                'error_code' => 'API_AUTH_MISSING_TOKEN',
            ], 401);
        }

        $token = substr($auth, 7);
        $tenantId = (int) $request->header('X-Tenant-Id');

        $accessToken = ApiAccessToken::query()
            ->where('token_hash', hash('sha256', $token))
            ->where('tenant_id', $tenantId)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->first();

        if (! $accessToken) {
            return response()->json([
                'message' => 'Invalid token.',
                'error_code' => 'API_AUTH_INVALID_TOKEN',
            ], 401);
        }

        $quotas = $this->quotaService->quotasForTenant($tenantId);
        $rateKey = 'rate:tenant:'.$tenantId.':'.now()->format('YmdHi');
        $count = $this->counter->increment($rateKey, 70);

        if ($count > $quotas['api_requests_per_minute']) {
            return response()->json([
                'message' => 'Rate limit exceeded.',
                'error_code' => 'RATE_LIMIT_EXCEEDED',
            ], 429);
        }

        $request->attributes->set('tenant_id', $tenantId);
        $request->attributes->set('developer_client_id', $accessToken->developer_client_id);

        return $next($request);
    }
}
