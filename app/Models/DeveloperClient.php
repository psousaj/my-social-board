<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeveloperClient extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'client_id',
        'primary_secret_hash',
        'secondary_secret_hash',
        'secondary_expires_at',
        'revoked_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'secondary_expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
