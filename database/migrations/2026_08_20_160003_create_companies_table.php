<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('clean_root_domain', 255)->nullable()->unique();
            $table->string('website_status', 100)->nullable();
            $table->string('company_linkedin_page', 500)->nullable();
            $table->foreignId('industry_id')->nullable()->constrained('industries')->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->unsignedInteger('employee_headcount')->nullable();
            $table->timestamps();

            $table->index('name');
            $table->index('employee_headcount');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
