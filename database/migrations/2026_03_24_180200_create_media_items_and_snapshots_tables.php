<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        Schema::create('media_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('provider_account_id')->constrained('provider_accounts')->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('external_media_id', 191);
            $table->string('media_type', 32)->default('UNKNOWN');
            $table->text('caption')->nullable();
            $table->text('media_url')->nullable();
            $table->text('permalink')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'provider', 'external_media_id'], 'media_items_unique_per_tenant');
            $table->index(['tenant_id', 'provider', 'published_at']);
        });

        Schema::create('media_metric_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('media_item_id')->constrained('media_items')->cascadeOnDelete();
            $table->string('provider', 32);
            $table->unsignedBigInteger('likes_count')->default(0);
            $table->unsignedBigInteger('comments_count')->default(0);
            $table->text('raw_snapshot_encrypted')->nullable();
            $table->string('kek_version', 32)->nullable();
            $table->string('dek_wrapped', 512)->nullable();
            $table->timestamp('snapshot_at');
            $table->timestamps();

            $table->index(['tenant_id', 'media_item_id', 'snapshot_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_metric_snapshots');
        Schema::dropIfExists('media_items');
    }
};
