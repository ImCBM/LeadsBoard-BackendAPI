<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop and recreate api_keys and api_traces with normalized 3NF structure.
 *
 * Changes from old schema:
 *   - Replaces string 'tier' column with foreign key 'tier_id' → tiers table
 *   - Removes JSON 'scopes' column (now in api_key_scopes pivot)
 *   - Removes JSON 'metadata' column (YAGNI — not needed in current architecture)
 *   - Creates api_key_scopes pivot table
 *   - Recreates api_traces with proper foreign keys
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Drop old unnormalized tables ─────────────────────────────
        Schema::dropIfExists('api_traces');
        Schema::dropIfExists('api_keys');

        // ── Recreate normalized api_keys ─────────────────────────────
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('tier_id')
                ->constrained('tiers')
                ->restrictOnDelete();
            $table->string('name', 100);
            $table->string('key_type', 16)->default('secret'); // secret, public
            $table->string('key', 64)->unique();               // SHA-256 hash
            $table->string('plain_text_prefix', 16)->nullable();
            $table->unsignedInteger('rate_limit_per_minute')->nullable();
            $table->json('allowed_ips')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('key_type');
            $table->index('tier_id');
            $table->index('is_active');
        });

        // ── API Key ↔ Scope pivot ────────────────────────────────────
        Schema::create('api_key_scopes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_key_id')
                ->constrained('api_keys')
                ->cascadeOnDelete();
            $table->foreignId('api_scope_id')
                ->constrained('api_scopes')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['api_key_id', 'api_scope_id']);
        });

        // ── Recreate normalized api_traces ───────────────────────────
        Schema::create('api_traces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_key_id')
                ->nullable()
                ->constrained('api_keys')
                ->nullOnDelete();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('request_id', 36)->nullable();
            $table->string('method', 10);
            $table->string('path', 500);
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('api_key_id');
            $table->index('user_id');
            $table->index('created_at');
            $table->index('status_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_traces');
        Schema::dropIfExists('api_key_scopes');
        Schema::dropIfExists('api_keys');
    }
};
