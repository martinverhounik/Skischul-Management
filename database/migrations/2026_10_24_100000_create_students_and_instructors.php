<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Students (US-17 to US-19) and instructors (US-20, US-21).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->date('date_of_birth');
            // restrictOnDelete: a level in use cannot be deleted (US-30).
            $table->foreignId('skill_level_id')->constrained()->restrictOnDelete();
            $table->string('primary_phone', 40);
            $table->string('primary_phone_owner', 100); // e.g. "Mother", "self"
            $table->string('secondary_phone', 40)->nullable();
            $table->string('secondary_phone_owner', 100)->nullable();
            $table->string('preferred_language', 50)->nullable(); // free text: DE, EN, NL …
            $table->string('accommodation')->nullable();
            $table->text('medical_notes')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'last_name', 'first_name']);
            $table->index(['school_id', 'archived_at']);
        });

        Schema::create('instructors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->date('date_of_birth');
            $table->string('email');
            $table->string('phone', 40);
            // Financial and social security data: encrypted in the database
            // and visible to owners only (US-27).
            $table->text('iban');
            $table->text('social_security_number');
            $table->string('bic', 11)->nullable();
            $table->unsignedInteger('hourly_rate_cents')->nullable();
            $table->string('certification_level')->nullable();
            $table->json('languages')->nullable(); // ["DE", "EN"]
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['school_id', 'email']);
            $table->index(['school_id', 'is_active']);
        });

        // Plan question 13: which sports an instructor teaches, so the app
        // can warn before assigning a snowboarder to a ski course.
        Schema::create('instructor_sport', function (Blueprint $table) {
            $table->foreignId('instructor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sport_id')->constrained()->restrictOnDelete();
            $table->primary(['instructor_id', 'sport_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instructor_sport');
        Schema::dropIfExists('instructors');
        Schema::dropIfExists('students');
    }
};
