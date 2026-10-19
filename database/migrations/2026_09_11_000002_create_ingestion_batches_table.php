<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingestion_batches', function (Blueprint $table) {
            $table->id();
            $table->string('source', 50)->default('n8n'); // n8n, csv_upload, api, manual, seeder
            $table->string('filename', 255)->nullable();
            $table->string('batch_id', 100)->nullable();
            $table->unsignedInteger('total_records')->default(0);
            $table->unsignedInteger('inserted_count')->default(0);
            $table->unsignedInteger('duplicates_count')->default(0);
            $table->unsignedInteger('errors_count')->default(0);
            $table->json('error_details')->nullable();
            $table->timestamps();

            $table->index('source');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingestion_batches');
    }
};
