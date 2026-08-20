<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * API keys table for fine-grained rate limiting and access control.
     * Each key can have its own rate limit or be unlimited.
     */
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100); // Human-readable label, e.g. "n8n-production", "frontend-staging"
            $table->string('key', 64)->unique(); // The actual API key (hashed)
            $table->string('plain_text_prefix', 8)->nullable(); // First 8 chars for identification (e.g. "jb_live_")
            $table->unsignedInteger('rate_limit_per_minute')->nullable(); // null = use default, 0 = unlimited
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
};
