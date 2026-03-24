<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use App\Security\Exceptions\InvalidSecurityConfigurationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SecurityBootstrapValidationTest extends TestCase
{
    #[Test]
    public function it_fails_fast_when_required_security_secret_is_missing(): void
    {
        $this->app['config']->set('security.encryption.active_kek_version', 'v1');
        $this->app['config']->set('security.encryption.kek_ring', ['v1' => '']);

        $provider = new AppServiceProvider($this->app);

        $this->expectException(InvalidSecurityConfigurationException::class);
        $provider->boot();
    }

    #[Test]
    public function it_boots_when_security_configuration_is_valid(): void
    {
        $this->app['config']->set('security.encryption.active_kek_version', 'v1');
        $this->app['config']->set('security.encryption.kek_ring', ['v1' => 'base64:'.base64_encode(random_bytes(32))]);

        $provider = new AppServiceProvider($this->app);
        $provider->boot();

        $this->assertTrue(true);
    }
}
