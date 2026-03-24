<?php

namespace App\Models;

use App\Security\Contracts\EnvelopeEncryption;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class ProviderToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'provider_account_id',
        'provider',
        'token_type',
        'access_token',
        'refresh_token',
        'expires_at',
        'revoked_at',
    ];

    protected $hidden = [
        'access_token_encrypted',
        'refresh_token_encrypted',
        'dek_wrapped',
    ];

    protected $appends = [
        'access_token',
        'refresh_token',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function providerAccount(): BelongsTo
    {
        return $this->belongsTo(ProviderAccount::class);
    }

    public function getAccessTokenAttribute(): ?string
    {
        $payload = $this->attributes['access_token_encrypted'] ?? null;

        if (! is_string($payload) || $payload === '') {
            return null;
        }

        return $this->crypto()->decrypt($this->decodeEnvelope($payload));
    }

    public function setAccessTokenAttribute(?string $value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['access_token_encrypted'] = null;

            return;
        }

        $envelope = $this->crypto()->encrypt($value);
        $this->attributes['access_token_encrypted'] = json_encode($envelope, JSON_THROW_ON_ERROR);
        $this->attributes['kek_version'] = $envelope['version'];
        $this->attributes['dek_wrapped'] = $envelope['wrapped_dek'];
    }

    public function getRefreshTokenAttribute(): ?string
    {
        $payload = $this->attributes['refresh_token_encrypted'] ?? null;

        if (! is_string($payload) || $payload === '') {
            return null;
        }

        return $this->crypto()->decrypt($this->decodeEnvelope($payload));
    }

    public function setRefreshTokenAttribute(?string $value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['refresh_token_encrypted'] = null;

            return;
        }

        $envelope = $this->crypto()->encrypt($value);
        $this->attributes['refresh_token_encrypted'] = json_encode($envelope, JSON_THROW_ON_ERROR);
    }

    /**
     * @return array{version:string,wrapped_dek:string,wrap_nonce:string,ciphertext:string,cipher_nonce:string,aad:string|null}
     */
    private function decodeEnvelope(string $json): array
    {
        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('Invalid encrypted envelope payload.');
        }

        return $decoded;
    }

    private function crypto(): EnvelopeEncryption
    {
        return app(EnvelopeEncryption::class);
    }
}
