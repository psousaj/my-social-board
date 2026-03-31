<?php

namespace App\Http\Controllers;

use App\Models\DeveloperClient;
use App\Models\Embed;
use App\Models\IngestionJob;
use App\Models\ProviderAccount;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $stats = [
            'tenants' => Tenant::query()->count(),
            'provider_accounts_connected' => ProviderAccount::query()->where('status', 'active')->count(),
            'ingestions_success' => IngestionJob::query()->where('state', 'succeeded')->count(),
            'ingestions_failed' => IngestionJob::query()->where('state', 'failed')->count(),
            'embeds_active' => Embed::query()->where('status', 'active')->count(),
            'developer_clients_active' => DeveloperClient::query()->where('status', 'active')->count(),
        ];

        $recentJobs = IngestionJob::query()
            ->select(['id', 'provider', 'state', 'attempts', 'created_at', 'completed_at'])
            ->latest()
            ->limit(6)
            ->get();

        $recentEmbeds = Embed::query()
            ->select(['id', 'embed_uid', 'name', 'widget_type', 'status', 'updated_at'])
            ->latest('updated_at')
            ->limit(6)
            ->get();

        $recentClients = DeveloperClient::query()
            ->select(['id', 'name', 'client_id', 'status', 'updated_at'])
            ->latest('updated_at')
            ->limit(6)
            ->get();

        $subscriptionBreakdown = TenantSubscription::query()
            ->join('subscription_plans', 'subscription_plans.id', '=', 'tenant_subscriptions.subscription_plan_id')
            ->selectRaw('subscription_plans.name as plan_name, count(*) as total')
            ->groupBy('subscription_plans.name')
            ->orderByDesc('total')
            ->get();

        $providerBreakdown = ProviderAccount::query()
            ->selectRaw('provider, status, count(*) as total')
            ->groupBy('provider', 'status')
            ->orderBy('provider')
            ->get();

        $recentAudit = DB::table('audit_events')
            ->select(['event_type', 'resource_type', 'resource_id', 'trace_id', 'created_at'])
            ->latest('created_at')
            ->limit(8)
            ->get();

        return view('admin.dashboard', [
            'stats' => $stats,
            'recentJobs' => $recentJobs,
            'recentEmbeds' => $recentEmbeds,
            'recentClients' => $recentClients,
            'subscriptionBreakdown' => $subscriptionBreakdown,
            'providerBreakdown' => $providerBreakdown,
            'recentAudit' => $recentAudit,
        ]);
    }
}
