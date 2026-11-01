<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Upgrade the api_keys table for the new developer-first API architecture.
     *
     * Adds:
     * - user_id:      Nullable FK to users table (hybrid ownership)
     * - key_type:     'public' or 'secret' (lb_pub_ vs lb_sec_)
     * - scopes:       JSON array of granted scopes (e.g. ["leads:read", "stats:read"])
     * - tier:         Account tier for rate limiting (guest, free, pro, enterprise)
     * - allowed_ips:  Optional IP whitelist for enhanced security
     * - metadata:     Flexible JSON for client name, environment, notes, etc.
     */
    public function up(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->nullOnDelete();

            $table->string('key_type', 16)
                ->default('secret')
                ->after('name');

            $table->json('scopes')
                ->nullable()
                ->after('key');

            $table->string('tier', 32)
                ->default('free')
                ->after('rate_limit_per_minute');

            $table->json('allowed_ips')
                ->nullable()
                ->after('tier');

            $table->json('metadata')
                ->nullable()
                ->after('allowed_ips');

            // Indexes for common queries
            $table->index('user_id');
            $table->index('key_type');
            $table->index('tier');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropIndex(['user_id']);
            $table->dropIndex(['key_type']);
            $table->dropIndex(['tier']);

            $table->dropColumn([
                'user_id',
                'key_type',
                'scopes',
                'tier',
                'allowed_ips',
                'metadata',
            ]);
        });
    }
};
