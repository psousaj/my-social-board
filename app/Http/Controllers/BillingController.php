<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPlan;
use App\Models\TenantSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    public function createPlan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:255'],
            'quotas' => ['required', 'array'],
        ]);

        $plan = SubscriptionPlan::query()->updateOrCreate(
            ['code' => (string) $data['code']],
            ['name' => (string) $data['name'], 'quotas' => $data['quotas']],
        );

        return response()->json($plan, 201);
    }

    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tenant_id' => ['required', 'integer'],
            'plan_code' => ['required', 'string', 'max:64'],
            'period_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $plan = SubscriptionPlan::query()->where('code', (string) $data['plan_code'])->firstOrFail();

        TenantSubscription::query()
            ->where('tenant_id', (int) $data['tenant_id'])
            ->where('status', 'active')
            ->update(['status' => 'replaced', 'updated_at' => now()]);

        $subscription = TenantSubscription::create([
            'tenant_id' => (int) $data['tenant_id'],
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
            'period_start' => now(),
            'period_end' => now()->addDays((int) ($data['period_days'] ?? 30)),
        ]);

        return response()->json($subscription, 201);
    }

    public function showSubscription(int $tenantId): JsonResponse
    {
        $subscription = TenantSubscription::query()
            ->with('plan')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->latest('id')
            ->first();

        return response()->json(['data' => $subscription]);
    }
}
