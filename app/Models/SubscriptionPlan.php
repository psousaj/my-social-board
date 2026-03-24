<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'quotas',
    ];

    protected function casts(): array
    {
        return [
            'quotas' => 'array',
        ];
    }
}
