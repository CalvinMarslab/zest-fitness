<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('credit_transactions')) {
            return;
        }

        Schema::create('credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('class_booking_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type'); // package_assigned, booking_deduction, booking_refund, class_cancel_refund, admin_adjustment, migration, other
            $table->integer('amount');      // signed: +10 assigned, -1 deduction, +1 refund
            $table->integer('balance_after');
            $table->string('reason')->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_transactions');
    }
};
