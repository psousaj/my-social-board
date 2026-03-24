<?php

namespace App\Security\Validation;

use App\Security\Exceptions\InvalidSecurityConfigurationException;

class SecurityConfigValidator
{
    /**
     * @param array{active_kek_version?:mixed,kek_ring?:mixed} $config
     */
    public function validate(array $config): void
    {
        $activeVersion = $config['active_kek_version'] ?? null;
        $kekRing = $config['kek_ring'] ?? null;

        if (! is_string($activeVersion) || $activeVersion === '') {
            throw new InvalidSecurityConfigurationException('Missing SECURITY_ACTIVE_KEK_VERSION.');
        }

        if (! is_array($kekRing) || $kekRing === []) {
            throw new InvalidSecurityConfigurationException('Missing SECURITY_KEK ring configuration.');
        }

        $activeKey = $kekRing[$activeVersion] ?? null;

        if (! is_string($activeKey) || trim($activeKey) === '') {
            throw new InvalidSecurityConfigurationException(
                "Missing KEK material for active version [{$activeVersion}]."
            );
        }
    }
}
