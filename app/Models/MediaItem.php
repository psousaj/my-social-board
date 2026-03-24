<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MediaItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'provider_account_id',
        'provider',
        'external_media_id',
        'caption',
        'media_type',
        'media_url',
        'permalink',
        'published_at',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'raw_payload' => 'array',
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

    public function metricSnapshots(): HasMany
    {
        return $this->hasMany(MediaMetricSnapshot::class);
    }
}
