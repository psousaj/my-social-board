<?php

namespace App\Billing;

use App\Models\TenantSubscription;

class TenantQuotaService
{
    /** @return array{monthly_media_ingestion:int,api_requests_per_minute:int} */
    public function quotasForTenant(int $tenantId): array
    {
        $subscription = TenantSubscription::query()
            ->with('plan')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->where('period_end', '>', now())
            ->latest('id')
            ->first();

        if (! $subscription || ! $subscription->plan) {
            return [
                'monthly_media_ingestion' => 100,
                'api_requests_per_minute' => 60,
            ];
        }

        $quotas = (array) $subscription->plan->quotas;

        return [
            'monthly_media_ingestion' => max(1, (int) ($quotas['monthly_media_ingestion'] ?? 100)),
            'api_requests_per_minute' => max(1, (int) ($quotas['api_requests_per_minute'] ?? 60)),
        ];
    }
}
