<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('slug', 100)->unique();
            $table->string('type', 50)->default('public'); // 'public' | 'system'
            $table->string('color', 20)->default('#64748b'); // Hex color badge
            $table->string('description', 255)->nullable();
            $table->timestamps();

            $table->index('type');
            $table->index('slug');
        });

        Schema::create('lead_tag', function (Blueprint $table) {
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['lead_id', 'tag_id']);
            $table->index('tag_id');
            $table->index('lead_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_tag');
        Schema::dropIfExists('tags');
    }
};
