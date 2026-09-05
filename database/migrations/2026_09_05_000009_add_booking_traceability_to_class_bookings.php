<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('class_bookings', 'user_subscription_id')) {
                $table->unsignedBigInteger('user_subscription_id')->nullable()->after('gym_class_id');
                $table->foreign('user_subscription_id')->references('id')->on('user_subscriptions')->nullOnDelete();
            }
            if (! Schema::hasColumn('class_bookings', 'credit_charged')) {
                $table->boolean('credit_charged')->default(false)->after('user_subscription_id');
            }
            if (! Schema::hasColumn('class_bookings', 'credit_refunded_at')) {
                $table->datetime('credit_refunded_at')->nullable()->after('credit_charged');
            }
            if (! Schema::hasColumn('class_bookings', 'booked_at')) {
                $table->datetime('booked_at')->nullable()->after('credit_refunded_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('class_bookings', function (Blueprint $table) {
            if (Schema::hasColumn('class_bookings', 'user_subscription_id')) {
                $table->dropForeign(['user_subscription_id']);
                $table->dropColumn('user_subscription_id');
            }
            if (Schema::hasColumn('class_bookings', 'credit_charged')) {
                $table->dropColumn('credit_charged');
            }
            if (Schema::hasColumn('class_bookings', 'credit_refunded_at')) {
                $table->dropColumn('credit_refunded_at');
            }
            if (Schema::hasColumn('class_bookings', 'booked_at')) {
                $table->dropColumn('booked_at');
            }
        });
    }
};
