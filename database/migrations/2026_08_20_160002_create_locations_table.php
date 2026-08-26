<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->string('raw_location', 500)->unique();
            $table->string('city', 255)->nullable();
            $table->string('state_region', 255)->nullable();
            $table->timestamps();

            $table->index('city');
            $table->index('state_region');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
