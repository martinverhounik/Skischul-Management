<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Price tables (US-07). One price table = one course offer in the booking
 * screen: its type, who it is for, its rules and its price grid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('course_type', 10); // group | private | guiding
            // Optional; when empty the app shows "Sport + course type".
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            // Informational only, used for instructor planning (US-10, US-11).
            $table->unsignedSmallInteger('min_participants')->default(1);
            $table->unsignedSmallInteger('max_participants')->default(1);
            // Group courses
            $table->unsignedTinyInteger('min_days')->nullable();
            $table->unsignedTinyInteger('max_days')->nullable();
            $table->json('start_weekdays')->nullable(); // [0..6], 0 = Sunday
            $table->unsignedTinyInteger('end_weekday')->nullable(); // latest weekday the course may run
            // Private / guiding: the longest bookable lesson, i.e. grid rows.
            $table->unsignedTinyInteger('max_hours')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        foreach (['sport' => 'sports', 'age_group' => 'age_groups', 'skill_level' => 'skill_levels'] as $single => $table) {
            Schema::create("price_table_{$single}", function (Blueprint $t) use ($single, $table) {
                $t->foreignId('price_table_id')->constrained()->cascadeOnDelete();
                // restrictOnDelete: the database itself refuses to delete a
                // sport / age group / level that a price table uses (US-30).
                $t->foreignId("{$single}_id")->constrained($table)->restrictOnDelete();
                $t->primary(['price_table_id', "{$single}_id"]);
            });
        }

        // Activated time options; they point at the school's timeslot by key,
        // so editing the slot's times changes every price table (US-06).
        Schema::create('price_table_time_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_table_id')->constrained()->cascadeOnDelete();
            $table->string('timeslot_key', 20); // full_day | morning | afternoon
            $table->unsignedTinyInteger('min_hours')->nullable(); // private / guiding only
            $table->unique(['price_table_id', 'timeslot_key']);
        });

        // One cell of the grid: days (group) or hours (private) × time option.
        Schema::create('price_table_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_table_id')->constrained()->cascadeOnDelete();
            $table->string('timeslot_key', 20);
            $table->unsignedTinyInteger('units'); // days or hours
            $table->unsignedInteger('price_cents');
            // Private / guiding: fee for every participant after the first.
            $table->unsignedInteger('extra_participant_cents')->nullable();
            $table->unique(['price_table_id', 'timeslot_key', 'units']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_table_prices');
        Schema::dropIfExists('price_table_time_options');
        Schema::dropIfExists('price_table_skill_level');
        Schema::dropIfExists('price_table_age_group');
        Schema::dropIfExists('price_table_sport');
        Schema::dropIfExists('price_tables');
    }
};
