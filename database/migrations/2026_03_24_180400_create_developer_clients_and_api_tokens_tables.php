<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        Schema::create('developer_clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->string('client_id', 64)->unique();
            $table->string('primary_secret_hash', 255);
            $table->string('secondary_secret_hash', 255)->nullable();
            $table->timestamp('secondary_expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('status', 32)->default('active');
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('api_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('developer_client_id')->constrained('developer_clients')->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'expires_at']);
        });

        Schema::create('api_refresh_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('developer_client_id')->constrained('developer_clients')->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_refresh_tokens');
        Schema::dropIfExists('api_access_tokens');
        Schema::dropIfExists('developer_clients');
    }
};
