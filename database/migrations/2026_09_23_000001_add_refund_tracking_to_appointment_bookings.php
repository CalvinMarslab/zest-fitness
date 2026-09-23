<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('appointment_bookings', function (Blueprint $table) {
            $table->timestamp('credit_refunded_at')->nullable()->after('credits_charged');
        });
    }

    public function down(): void
    {
        Schema::table('appointment_bookings', function (Blueprint $table) {
            $table->dropColumn('credit_refunded_at');
        });
    }
};
