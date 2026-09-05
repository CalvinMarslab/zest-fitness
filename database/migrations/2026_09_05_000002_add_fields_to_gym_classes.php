<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gym_classes', function (Blueprint $table) {
            if (! Schema::hasColumn('gym_classes', 'coach_id')) {
                $table->unsignedBigInteger('coach_id')->nullable()->after('coach');
                $table->foreign('coach_id')->references('id')->on('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('gym_classes', 'end_time')) {
                $table->dateTime('end_time')->nullable()->after('start_time');
            }
            if (! Schema::hasColumn('gym_classes', 'location')) {
                $table->string('location', 100)->nullable()->after('end_time');
            }
            if (! Schema::hasColumn('gym_classes', 'status')) {
                $table->string('status', 20)->default('scheduled')->after('location');
            }
            if (! Schema::hasColumn('gym_classes', 'booking_opens_at')) {
                $table->dateTime('booking_opens_at')->nullable()->after('status');
            }
            if (! Schema::hasColumn('gym_classes', 'booking_closes_at')) {
                $table->dateTime('booking_closes_at')->nullable()->after('booking_opens_at');
            }
            if (! Schema::hasColumn('gym_classes', 'cancellation_cutoff_hours')) {
                $table->unsignedInteger('cancellation_cutoff_hours')->default(2)->after('booking_closes_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('gym_classes', function (Blueprint $table) {
            $columns = ['coach_id', 'end_time', 'location', 'status', 'booking_opens_at', 'booking_closes_at', 'cancellation_cutoff_hours'];
            $toDrop = array_filter($columns, fn ($col) => Schema::hasColumn('gym_classes', $col));
            if ($toDrop) {
                if (Schema::hasColumn('gym_classes', 'coach_id')) {
                    $table->dropForeign(['coach_id']);
                }
                $table->dropColumn(array_values($toDrop));
            }
        });
    }
};
