<?php

namespace App\Providers;

use App\Billing\EdgeCounter;
use App\Billing\InternalEdgeCounterService;
use App\Security\Contracts\EnvelopeEncryption;
use App\Security\EnvelopeEncryptionService;
use App\Security\Validation\SecurityConfigValidator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(EdgeCounter::class, InternalEdgeCounterService::class);
        $this->app->singleton(SecurityConfigValidator::class, SecurityConfigValidator::class);

        $this->app->singleton(EnvelopeEncryption::class, function (): EnvelopeEncryption {
            return new EnvelopeEncryptionService(
                activeKekVersion: (string) config('security.encryption.active_kek_version'),
                kekRing: (array) config('security.encryption.kek_ring', []),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        app(SecurityConfigValidator::class)->validate(
            (array) config('security.encryption', []),
        );
    }
}
