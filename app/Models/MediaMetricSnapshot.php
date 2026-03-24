<?php

namespace App\Models;

use App\Security\Contracts\EnvelopeEncryption;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class MediaMetricSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'media_item_id',
        'provider',
        'likes_count',
        'comments_count',
        'snapshot_at',
        'raw_snapshot',
    ];

    protected $hidden = [
        'raw_snapshot_encrypted',
        'dek_wrapped',
    ];

    protected $appends = [
        'raw_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function mediaItem(): BelongsTo
    {
        return $this->belongsTo(MediaItem::class);
    }

    public function getRawSnapshotAttribute(): ?array
    {
        $payload = $this->attributes['raw_snapshot_encrypted'] ?? null;

        if (! is_string($payload) || $payload === '') {
            return null;
        }

        $plaintext = $this->crypto()->decrypt($this->decodeEnvelope($payload));
        $decoded = json_decode($plaintext, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string,mixed>|null $value
     */
    public function setRawSnapshotAttribute(?array $value): void
    {
        if ($value === null) {
            $this->attributes['raw_snapshot_encrypted'] = null;

            return;
        }

        $envelope = $this->crypto()->encrypt(json_encode($value, JSON_THROW_ON_ERROR));
        $this->attributes['raw_snapshot_encrypted'] = json_encode($envelope, JSON_THROW_ON_ERROR);
        $this->attributes['kek_version'] = $envelope['version'];
        $this->attributes['dek_wrapped'] = $envelope['wrapped_dek'];
    }

    /**
     * @return array{version:string,wrapped_dek:string,wrap_nonce:string,ciphertext:string,cipher_nonce:string,aad:string|null}
     */
    private function decodeEnvelope(string $json): array
    {
        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('Invalid encrypted snapshot envelope payload.');
        }

        return $decoded;
    }

    private function crypto(): EnvelopeEncryption
    {
        return app(EnvelopeEncryption::class);
    }
}
