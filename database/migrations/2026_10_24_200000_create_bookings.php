<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bookings (build step 5: US-10 to US-13, US-29).
 *
 * booking            one order by one booker (may contain several courses)
 * booking_lines      one course in the order
 * booking_line_student  the participants of that course
 * lesson_days        every actual lesson day / private lesson of a line
 *                    (grouping and instructor planning hang off these)
 * booking_add_ons    extras with quantity
 * booking_events     history: who did what, when
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('number', 20); // e.g. 2026-0042, per school
            // Where the booking came from; external_id is the booking number
            // on that platform, so later imports never create duplicates.
            $table->string('source', 20)->default('office'); // office | book2ski | checkyeti
            $table->string('external_id', 100)->nullable();

            $table->string('booker_name');
            $table->string('email');
            $table->string('phone', 40)->nullable();
            $table->string('street');
            $table->string('house_number', 20);
            $table->string('postal_code', 20);
            $table->string('city', 100);
            $table->string('country', 2);

            $table->string('payment_method', 10); // cash | card
            $table->string('payment_status', 10)->default('unpaid'); // unpaid | paid
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('handled_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->foreignId('voucher_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('voucher_code', 40)->nullable();
            $table->unsignedInteger('subtotal_cents');
            $table->unsignedInteger('discount_cents')->default(0);
            $table->unsignedInteger('total_cents');

            $table->text('notes')->nullable();
            $table->timestamp('confirmation_sent_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['school_id', 'number']);
            $table->unique(['school_id', 'source', 'external_id']);
            $table->index(['school_id', 'handled_at', 'cancelled_at']);
        });

        Schema::create('booking_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            // restrictOnDelete: a price table that was booked can't be deleted.
            $table->foreignId('price_table_id')->constrained()->restrictOnDelete();
            $table->foreignId('sport_id')->constrained()->restrictOnDelete();
            $table->string('course_type', 10);
            $table->string('timeslot_key', 20);
            $table->date('start_date');
            $table->date('end_date');
            // Group: booked days. Private / guiding: booked hours.
            $table->unsignedTinyInteger('units');
            $table->unsignedTinyInteger('lesson_count');
            // Private / guiding only: the fixed start time (plan question 4).
            $table->time('start_time')->nullable();
            $table->unsignedInteger('price_cents');
            $table->timestamps();

            $table->index(['school_id', 'start_date']);
        });

        Schema::create('booking_line_student', function (Blueprint $table) {
            $table->foreignId('booking_line_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('age_at_start');
            // The office booked the student although their age is outside the
            // course's age groups (skill level matters more, plan decision).
            $table->boolean('age_override')->default(false);
            $table->primary(['booking_line_id', 'student_id']);
        });

        Schema::create('lesson_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_line_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            // Group lessons follow the school's timeslot (edits propagate);
            // private lessons have their own times.
            $table->string('timeslot_key', 20);
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'date']);
        });

        Schema::create('booking_add_ons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('add_on_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('quantity');
            $table->unsignedInteger('unit_price_cents');
            $table->unsignedInteger('total_cents');
        });

        Schema::create('booking_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 40); // created, paid, unpaid, handled, cancelled, edited, email_sent, email_failed
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_events');
        Schema::dropIfExists('booking_add_ons');
        Schema::dropIfExists('lesson_days');
        Schema::dropIfExists('booking_line_student');
        Schema::dropIfExists('booking_lines');
        Schema::dropIfExists('bookings');
    }
};
