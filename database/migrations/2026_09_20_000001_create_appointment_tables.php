<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('appointment_services', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('color', 20)->default('#f97316');
            $table->unsignedSmallInteger('duration_minutes');
            $table->unsignedSmallInteger('interval_minutes')->default(30);
            $table->unsignedSmallInteger('credits_required')->default(1);
            $table->unsignedSmallInteger('release_days')->default(30);
            $table->unsignedSmallInteger('booking_deadline_hours')->default(0);
            $table->unsignedSmallInteger('cancellation_cutoff_hours')->nullable();
            $table->boolean('is_public')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('appointment_service_package', function (Blueprint $table) {
            $table->foreignId('appointment_service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            $table->primary(['appointment_service_id', 'package_id']);
        });

        Schema::create('appointment_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('coach_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('start_time');
            $table->timestamp('end_time');
            $table->string('location')->nullable();
            $table->string('status')->default('available');
            $table->timestamps();
            $table->unique(['coach_id', 'start_time']);
        });

        Schema::create('appointment_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_slot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('booked');
            $table->unsignedSmallInteger('credits_charged')->default(0);
            $table->timestamp('booked_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['appointment_slot_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_bookings');
        Schema::dropIfExists('appointment_slots');
        Schema::dropIfExists('appointment_service_package');
        Schema::dropIfExists('appointment_services');
    }
};
