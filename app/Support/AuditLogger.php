<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuditLogger
{
    /**
     * @param array<string,mixed>|null $metadata
     */
    public function log(int $tenantId, string $eventType, ?string $resourceType, ?string $resourceId, ?array $metadata = null, ?Request $request = null): void
    {
        DB::table('audit_events')->insert([
            'tenant_id' => $tenantId,
            'actor_user_id' => null,
            'event_type' => $eventType,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'metadata' => $metadata !== null ? json_encode($metadata, JSON_THROW_ON_ERROR) : null,
            'ip_address' => $request?->ip(),
            'trace_id' => (string) ($request?->attributes->get('trace_id') ?? ''),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
