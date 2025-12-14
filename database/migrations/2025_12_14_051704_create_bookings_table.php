<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Schema inspired by Calendly, Square Appointments, and Salesforce Service Cloud
     */
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            // Primary key (UUID)
            $table->uuid('id')->primary();

            // Relations
            $table->foreignUuid('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // Client
            $table->foreignId('assigned_to')->nullable()->constrained('users'); // Staff member

            // Booking details
            $table->string('status')->default('created'); // BookingStatus enum
            $table->dateTime('scheduled_at'); // When service is scheduled
            $table->dateTime('started_at')->nullable(); // Actual start (check-in)
            $table->dateTime('completed_at')->nullable(); // Actual end (check-out)
            $table->integer('duration_minutes'); // Planned duration (from service)
            $table->integer('actual_duration_minutes')->nullable(); // Real duration

            // Payment info (Square Appointments pattern)
            $table->integer('amount_cents'); // Price at booking time
            $table->string('currency', 3)->default('USD');
            $table->integer('deposit_cents')->nullable(); // Upfront deposit
            $table->boolean('is_paid')->default(false);
            $table->string('payment_status')->default('pending'); // pending, partial, paid, refunded
            $table->string('payment_method')->nullable(); // webpay, stripe, etc
            $table->string('payment_intent_id')->nullable(); // External payment ID

            // Client info (snapshot at booking time - Salesforce pattern)
            $table->string('client_name');
            $table->string('client_email');
            $table->string('client_phone')->nullable();
            $table->text('client_notes')->nullable(); // Special requests

            // Cancellation/Rescheduling (Calendly pattern)
            $table->string('cancellation_reason')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users');
            $table->dateTime('cancelled_at')->nullable();
            $table->uuid('rescheduled_from')->nullable(); // If rescheduled
            $table->dateTime('rescheduled_at')->nullable();

            // Reminders (automated communication)
            $table->boolean('reminder_sent')->default(false);
            $table->dateTime('reminder_sent_at')->nullable();
            $table->boolean('confirmation_sent')->default(false);

            // Internal notes (staff only - Salesforce pattern)
            $table->text('staff_notes')->nullable();

            // Tracking
            $table->string('source')->default('web'); // web, api, admin, phone
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent')->nullable();

            $table->timestamps();
            $table->softDeletes(); // Soft delete for audit trail

            // Indexes for performance (based on common queries)
            $table->index('scheduled_at');
            $table->index('status');
            $table->index(['user_id', 'status']);
            $table->index(['service_id', 'scheduled_at']);
            $table->index(['assigned_to', 'scheduled_at']);
            $table->index('payment_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
