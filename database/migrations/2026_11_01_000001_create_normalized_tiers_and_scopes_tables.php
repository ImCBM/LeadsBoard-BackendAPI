<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the normalized 3NF tables for tiers, scopes, and subscriptions.
 *
 * Tables created:
 *   - tiers: Master lookup for account tiers (free, pro, enterprise)
 *   - api_scopes: Master lookup for granular API scopes (leads:read, etc.)
 *   - tier_scopes: Pivot mapping tiers → allowed scopes
 *   - tier_field_restrictions: Per-tier field masking rules (e.g. mask contact_number for free)
 *   - subscriptions: User subscription records linking users to tiers
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Tiers ────────────────────────────────────────────────────
        Schema::create('tiers', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 32)->unique();
            $table->string('name', 100);
            $table->unsignedInteger('rate_limit_per_minute')->default(60);
            $table->unsignedInteger('daily_request_limit')->default(5000);
            $table->unsignedSmallInteger('max_keys')->default(3);
            $table->boolean('can_export')->default(false);
            $table->boolean('can_bulk_operations')->default(false);
            $table->timestamps();
        });

        // ── API Scopes ───────────────────────────────────────────────
        Schema::create('api_scopes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();       // e.g. "leads:read"
            $table->string('resource', 30);              // e.g. "leads"
            $table->string('action', 30);                // e.g. "read"
            $table->string('description', 255)->nullable();
            $table->boolean('is_mutation')->default(false);
            $table->timestamps();
        });

        // ── Tier ↔ Scope pivot ───────────────────────────────────────
        Schema::create('tier_scopes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tier_id')->constrained('tiers')->cascadeOnDelete();
            $table->foreignId('api_scope_id')->constrained('api_scopes')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['tier_id', 'api_scope_id']);
        });

        // ── Tier Field Restrictions ──────────────────────────────────
        Schema::create('tier_field_restrictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tier_id')->constrained('tiers')->cascadeOnDelete();
            $table->string('resource', 30);              // e.g. "leads"
            $table->string('field_name', 50);            // e.g. "contact_number"
            $table->string('restriction_type', 30)->default('mask_null');
            $table->timestamps();

            $table->unique(['tier_id', 'resource', 'field_name']);
        });

        // ── Subscriptions ────────────────────────────────────────────
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('tier_id')->constrained('tiers')->restrictOnDelete();
            $table->string('status', 20)->default('active'); // active, cancelled, expired
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('tier_field_restrictions');
        Schema::dropIfExists('tier_scopes');
        Schema::dropIfExists('api_scopes');
        Schema::dropIfExists('tiers');
    }
};
