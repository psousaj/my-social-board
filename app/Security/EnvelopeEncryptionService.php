<?php

namespace App\Security;

use App\Security\Contracts\EnvelopeEncryption;
use App\Security\Exceptions\UnknownKeyVersionException;
use RuntimeException;

class EnvelopeEncryptionService implements EnvelopeEncryption
{
    /** @var array<string, string> */
    private array $kekRing;

    /** @var callable */
    private $randomBytes;

    /**
     * @param array<string, string|null> $kekRing
     * @param callable|null $randomBytes function (int $length): string
     */
    public function __construct(
        private readonly string $activeKekVersion,
        array $kekRing,
        ?callable $randomBytes = null,
    ) {
        $this->kekRing = $this->normalizeKekRing($kekRing);
        $this->randomBytes = $randomBytes ?? fn (int $length): string => random_bytes($length);

        if (! isset($this->kekRing[$this->activeKekVersion])) {
            throw new UnknownKeyVersionException("Unknown active KEK version [{$this->activeKekVersion}].");
        }
    }

    public function encrypt(string $plaintext, ?string $aad = null): array
    {
        $kek = $this->resolveKek($this->activeKekVersion);
        $dek = $this->random(self::keyBytes());

        $cipherNonce = $this->random(self::nonceBytes());
        $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
            $plaintext,
            $aad ?? '',
            $cipherNonce,
            $dek,
        );

        $wrapNonce = $this->random(self::nonceBytes());
        $wrappedDek = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
            $dek,
            '',
            $wrapNonce,
            $kek,
        );

        return [
            'version' => $this->activeKekVersion,
            'wrapped_dek' => base64_encode($wrappedDek),
            'wrap_nonce' => base64_encode($wrapNonce),
            'ciphertext' => base64_encode($ciphertext),
            'cipher_nonce' => base64_encode($cipherNonce),
            'aad' => $aad,
        ];
    }

    public function decrypt(array $envelope, ?string $aad = null): string
    {
        $version = $envelope['version'] ?? null;

        if (! is_string($version) || $version === '') {
            throw new UnknownKeyVersionException('Envelope is missing a KEK version.');
        }

        $kek = $this->resolveKek($version);

        $dek = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
            $this->decode64($envelope['wrapped_dek'] ?? null, 'wrapped_dek'),
            '',
            $this->decode64($envelope['wrap_nonce'] ?? null, 'wrap_nonce'),
            $kek,
        );

        if ($dek === false) {
            throw new RuntimeException('Unable to unwrap DEK with provided KEK version.');
        }

        $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
            $this->decode64($envelope['ciphertext'] ?? null, 'ciphertext'),
            $aad ?? (is_string($envelope['aad'] ?? null) ? $envelope['aad'] : ''),
            $this->decode64($envelope['cipher_nonce'] ?? null, 'cipher_nonce'),
            $dek,
        );

        if ($plaintext === false) {
            throw new RuntimeException('Unable to decrypt envelope payload.');
        }

        return $plaintext;
    }

    /**
     * @param array<string, string|null> $ring
     * @return array<string, string>
     */
    private function normalizeKekRing(array $ring): array
    {
        $normalized = [];

        foreach ($ring as $version => $rawKey) {
            if (! is_string($version) || $version === '' || ! is_string($rawKey) || $rawKey === '') {
                continue;
            }

            $normalized[$version] = $this->decodeKey($rawKey);
        }

        return $normalized;
    }

    private function decodeKey(string $rawKey): string
    {
        $value = str_starts_with($rawKey, 'base64:')
            ? substr($rawKey, 7)
            : $rawKey;

        $decoded = base64_decode($value, true);

        if ($decoded !== false && strlen($decoded) === self::keyBytes()) {
            return $decoded;
        }

        if (strlen($rawKey) === self::keyBytes()) {
            return $rawKey;
        }

        if (strlen($value) === self::keyBytes()) {
            return $value;
        }

        throw new RuntimeException('Invalid KEK length. Expected 32 bytes key material.');
    }

    private function resolveKek(string $version): string
    {
        if (! isset($this->kekRing[$version])) {
            throw new UnknownKeyVersionException("Unknown KEK version [{$version}].");
        }

        return $this->kekRing[$version];
    }

    private function decode64(mixed $value, string $field): string
    {
        if (! is_string($value) || $value === '') {
            throw new RuntimeException("Envelope field [{$field}] is required.");
        }

        $decoded = base64_decode($value, true);

        if ($decoded === false) {
            throw new RuntimeException("Envelope field [{$field}] must be base64.");
        }

        return $decoded;
    }

    private static function keyBytes(): int
    {
        return SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES;
    }

    private static function nonceBytes(): int
    {
        return SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
    }

    private function random(int $length): string
    {
        $generator = $this->randomBytes;
        $bytes = $generator($length);

        if (! is_string($bytes) || strlen($bytes) !== $length) {
            throw new RuntimeException('Random byte generator returned invalid length.');
        }

        return $bytes;
    }
}
