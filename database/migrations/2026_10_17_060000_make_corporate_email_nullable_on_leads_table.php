<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Make corporate_email nullable on the leads table.
 *
 * Real-world lead data from N8N scraping is often incomplete — not every
 * company or lead has a publicly available corporate email. The system
 * should still accept these leads and deduplicate using whatever
 * identifiers are available (name, phone, company, etc.).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('corporate_email', 255)->nullable()->change();
        });

        // Drop and recreate the unique index to allow multiple NULLs
        // (most databases already allow multiple NULLs in unique indexes,
        //  but this ensures clean state after the nullable change)
        Schema::table('leads', function (Blueprint $table) {
            $table->dropUnique(['corporate_email']);
            $table->unique('corporate_email');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('corporate_email', 255)->nullable(false)->change();
        });
    }
};
