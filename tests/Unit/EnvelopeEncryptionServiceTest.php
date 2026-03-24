<?php

namespace Tests\Unit;

use App\Security\EnvelopeEncryptionService;
use App\Security\Exceptions\UnknownKeyVersionException;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class EnvelopeEncryptionServiceTest extends TestCase
{
    #[Test]
    public function it_encrypts_and_decrypts_deterministically_when_random_source_is_fixed(): void
    {
        $keyMaterial = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES);
        $service = new EnvelopeEncryptionService(
            activeKekVersion: 'v1',
            kekRing: ['v1' => base64_encode($keyMaterial)],
            randomBytes: function (int $length): string {
                return str_repeat('a', $length);
            },
        );

        $first = $service->encrypt('my-secret-token');
        $second = $service->encrypt('my-secret-token');

        $this->assertSame($first, $second);
        $this->assertSame('my-secret-token', $service->decrypt($first));
        $this->assertSame('my-secret-token', $service->decrypt($second));
    }

    #[Test]
    public function it_fails_when_envelope_references_unknown_key_version(): void
    {
        $keyMaterial = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES);
        $service = new EnvelopeEncryptionService(
            activeKekVersion: 'v1',
            kekRing: ['v1' => base64_encode($keyMaterial)],
        );

        $envelope = $service->encrypt('my-secret-token');
        $envelope['version'] = 'v999';

        $this->expectException(UnknownKeyVersionException::class);
        $service->decrypt($envelope);
    }

    #[Test]
    public function it_fails_when_key_size_is_invalid(): void
    {
        $this->expectException(RuntimeException::class);

        new EnvelopeEncryptionService(
            activeKekVersion: 'v1',
            kekRing: ['v1' => 'too-short'],
        );
    }
}
