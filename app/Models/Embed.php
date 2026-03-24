<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Embed extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'embed_uid',
        'name',
        'widget_type',
        'config',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
