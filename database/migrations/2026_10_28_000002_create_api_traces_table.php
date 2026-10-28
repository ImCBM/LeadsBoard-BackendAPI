<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the api_traces table for non-blocking audit logging.
     *
     * Each API request generates a trace record via terminable middleware,
     * written after the response is sent so it has zero latency impact.
     */
    public function up(): void
    {
        Schema::create('api_traces', function (Blueprint $table) {
            $table->id();
            $table->uuid('trace_id')->unique();

            // Who made the request
            $table->foreignId('api_key_id')
                ->nullable()
                ->constrained('api_keys')
                ->nullOnDelete();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('key_prefix', 16)->nullable();

            // What was requested
            $table->string('method', 10);
            $table->string('path', 255);
            $table->string('route_name', 100)->nullable();

            // Response info
            $table->unsignedSmallInteger('status_code');
            $table->unsignedInteger('duration_ms');

            // Where from
            $table->string('ip_address', 45);
            $table->text('user_agent')->nullable();

            // Request details (optional, sanitized)
            $table->json('query_params')->nullable();
            $table->json('request_payload')->nullable();

            $table->timestamp('created_at')->useCurrent();

            // Indexes for querying
            $table->index('api_key_id');
            $table->index('user_id');
            $table->index('created_at');
            $table->index(['method', 'path']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_traces');
    }
};
