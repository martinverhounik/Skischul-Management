<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Standalone calendar entries (build step 8, US-26): day off, sick leave,
 * meeting, blocked slot. Not courses, so they never appear in Grouping.
 * An entry linked to an instructor blocks that instructor (US-30).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('title', 150);
            // Several days in a row (e.g. a week of holiday) are one entry.
            $table->date('starts_on');
            $table->date('ends_on');
            // Both null = all day.
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->foreignId('instructor_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['school_id', 'starts_on', 'ends_on']);
            $table->index(['instructor_id', 'starts_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_events');
    }
};
