<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A school is one ski school (one tenant). Every tenant-owned table carries
 * a school_id that points here, which is what lets the app run as SaaS later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schools', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('locale', 5)->default('de');
            $table->string('timezone')->default('Europe/Vienna');
            // 0 = Sunday ... 6 = Saturday (Carbon convention). Weeks in the
            // dashboard, grouping and calendar start on this day.
            $table->unsignedTinyInteger('week_starts_on')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schools');
    }
};
