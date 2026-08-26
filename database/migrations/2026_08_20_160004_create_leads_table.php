<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('full_name', 255);
            $table->string('job_title', 255)->nullable();
            $table->string('title_tier', 50)->default('Other');
            $table->string('corporate_email', 255)->unique();
            $table->string('email_status', 100)->nullable();
            $table->string('executive_linkedin_url', 500)->nullable();
            $table->string('ingestion_channel', 50)->default('n8n');
            $table->string('status', 20)->default('new');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('full_name');
            $table->index('title_tier');
            $table->index('status');
            $table->index('ingestion_channel');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
