<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();

            // Contact info (from n8n payload)
            $table->string('full_name', 255);
            $table->string('job_title', 255)->nullable();
            $table->string('title_tier', 50)->default('Other'); // C-Level, VP-Level, Director-Level, Other
            $table->string('corporate_email', 255)->unique(); // Primary dedup key
            $table->string('email_status', 100)->nullable();

            // Company info
            $table->string('company_name', 255);
            $table->string('clean_root_domain', 255)->nullable();
            $table->string('website_status', 100)->nullable();

            // LinkedIn
            $table->string('executive_linkedin_url', 500)->nullable();
            $table->string('company_linkedin_page', 500)->nullable();

            // Classification
            $table->string('industry_classification', 255)->nullable();
            $table->unsignedInteger('employee_headcount')->nullable();

            // Location
            $table->string('hq_location', 500)->nullable();
            $table->string('country', 255)->nullable(); // Resolved from hq_location via 3rd party API

            // Metadata
            $table->string('ingestion_channel', 50)->default('n8n'); // How the lead arrived: n8n, manual, api, csv_import
            $table->string('status', 20)->default('new'); // new, reviewed, qualified, rejected
            $table->text('notes')->nullable();

            $table->timestamps();

            // Indexes for filtering and search performance
            $table->index('company_name');
            $table->index('industry_classification');
            $table->index('title_tier');
            $table->index('status');
            $table->index('country');
            $table->index('ingestion_channel');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
