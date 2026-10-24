<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Grouping and instructor assignment (build step 7: US-22 to US-24).
 *
 * Students are counted per day, time, sport and age group (owner decision).
 * Inside that, the office forms groups of students who ski together, each
 * with one instructor and a meeting point. Groups exist per day, so they
 * can be merged or split during the week.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('timeslot_key', 20);
            $table->foreignId('sport_id')->constrained()->restrictOnDelete();
            $table->foreignId('age_group_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->foreignId('instructor_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('meeting_point_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'date']);
            $table->index(['instructor_id', 'date']);
        });

        // One row per student per lesson day: a student is in at most one
        // group on a given lesson day.
        Schema::create('group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_day_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->unique(['lesson_day_id', 'student_id']);
        });

        // Private lessons and guiding: the instructor and meeting point are
        // set directly on the lesson.
        Schema::table('lesson_days', function (Blueprint $table) {
            $table->foreignId('instructor_id')->nullable()->after('ends_at')->constrained()->nullOnDelete();
            $table->foreignId('meeting_point_id')->nullable()->after('instructor_id')->constrained()->nullOnDelete();
            $table->index(['instructor_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::table('lesson_days', function (Blueprint $table) {
            $table->dropConstrainedForeignId('meeting_point_id');
            $table->dropConstrainedForeignId('instructor_id');
        });
        Schema::dropIfExists('group_members');
        Schema::dropIfExists('groups');
    }
};
