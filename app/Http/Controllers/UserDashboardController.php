<?php

namespace App\Http\Controllers;

use App\Models\Embed;
use App\Models\ProviderAccount;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\View\View;

class UserDashboardController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();

        if (! $user) {
            $userId = (int) request()->query('user_id', 0);
            $user = $userId > 0 ? User::query()->find($userId) : null;
        }

        $tenant = null;
        $providers = [
            ['key' => 'instagram', 'label' => 'Instagram'],
        ];

        $connectedProviders = collect();
        $embeds = collect();

        if ($user) {
            $tenant = Tenant::query()->find($user->tenant_id);
            $connectedProviders = ProviderAccount::query()
                ->where('tenant_id', $user->tenant_id)
                ->where('user_id', $user->id)
                ->orderBy('provider')
                ->get(['provider', 'display_name', 'status', 'external_account_id']);

            $embeds = Embed::query()
                ->where('tenant_id', $user->tenant_id)
                ->orderByDesc('updated_at')
                ->get(['embed_uid', 'name', 'widget_type', 'status']);
        }

        return view('dashboard.index', [
            'user' => $user,
            'tenant' => $tenant,
            'providers' => $providers,
            'connectedProviders' => $connectedProviders,
            'embeds' => $embeds,
            'templates' => [
                ['key' => 'clean-grid', 'name' => 'Clean Grid'],
                ['key' => 'magazine-cards', 'name' => 'Magazine Cards'],
                ['key' => 'minimal-carousel', 'name' => 'Minimal Carousel'],
            ],
        ]);
    }
}
