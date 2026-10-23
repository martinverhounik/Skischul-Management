<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Global school settings (build step 2):
 * season and closed days (US-01), sports (US-02), skill levels (US-03),
 * age groups (US-04), meeting points (US-05), course times (US-06),
 * add-ons (US-08) and vouchers (US-28).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->string('currency', 3)->default('EUR')->after('week_starts_on');
            $table->date('season_starts_on')->nullable()->after('currency');
            $table->date('season_ends_on')->nullable()->after('season_starts_on');
            // Plan question 9: each school decides whether a full day must
            // cost more than the more expensive half day (price tables).
            $table->boolean('enforce_full_day_price')->default(true)->after('season_ends_on');
        });

        Schema::create('closed_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'date']);
        });

        Schema::create('sports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['school_id', 'name']);
        });

        Schema::create('skill_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // Optional colour dot, e.g. green / blue / red / black slopes.
            $table->string('color', 7)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['school_id', 'name']);
        });

        Schema::create('age_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedTinyInteger('min_age');
            $table->unsignedTinyInteger('max_age');
            $table->timestamps();

            $table->unique(['school_id', 'name']);
        });

        Schema::create('meeting_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('map_url', 2048)->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'name']);
        });

        Schema::create('timeslots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            // full_day | morning | afternoon | lunch | private_day
            $table->string('key', 20);
            $table->time('starts_at');
            $table->time('ends_at');
            $table->timestamps();

            $table->unique(['school_id', 'key']);
        });

        Schema::create('add_ons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('price_cents');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Plan question 7: lunch supervision is charged with this add-on.
        Schema::table('schools', function (Blueprint $table) {
            $table->foreignId('lunch_add_on_id')->nullable()->after('enforce_full_day_price')
                ->constrained('add_ons')->nullOnDelete();
        });

        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('discount_type', 10); // fixed | percent
            // Cents for "fixed", hundredths of a percent for "percent"
            // (1250 = 12.50 %), so both stay exact integers.
            $table->unsignedInteger('discount_value');
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('times_used')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropConstrainedForeignId('lunch_add_on_id');
            $table->dropColumn(['currency', 'season_starts_on', 'season_ends_on', 'enforce_full_day_price']);
        });

        Schema::dropIfExists('vouchers');
        Schema::dropIfExists('add_ons');
        Schema::dropIfExists('timeslots');
        Schema::dropIfExists('meeting_points');
        Schema::dropIfExists('age_groups');
        Schema::dropIfExists('skill_levels');
        Schema::dropIfExists('sports');
        Schema::dropIfExists('closed_days');
    }
};
