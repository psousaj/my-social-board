<?php

namespace Tests\Feature\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantCoreSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_required_core_tables_exist(): void
    {
        $this->assertTrue(Schema::hasTable('tenants'));
        $this->assertTrue(Schema::hasTable('users'));
        $this->assertTrue(Schema::hasTable('provider_accounts'));
        $this->assertTrue(Schema::hasTable('oauth_consents'));
        $this->assertTrue(Schema::hasTable('provider_tokens'));
        $this->assertTrue(Schema::hasTable('audit_events'));
    }

    public function test_user_email_can_repeat_across_tenants_but_not_within_same_tenant(): void
    {
        $tenantA = DB::table('tenants')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'Tenant A',
            'slug' => 'tenant-a',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tenantB = DB::table('tenants')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'Tenant B',
            'slug' => 'tenant-b',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('users')->insert([
            'tenant_id' => $tenantA,
            'name' => 'User A',
            'email' => 'same@example.com',
            'password' => bcrypt('secret'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('users')->insert([
            'tenant_id' => $tenantB,
            'name' => 'User B',
            'email' => 'same@example.com',
            'password' => bcrypt('secret'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('users')->insert([
            'tenant_id' => $tenantA,
            'name' => 'User C',
            'email' => 'same@example.com',
            'password' => bcrypt('secret'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
