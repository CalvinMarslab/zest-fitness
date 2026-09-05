<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('class_bookings', 'status')) {
                $table->string('status', 20)->default('booked')->after('gym_class_id');
            }
            if (! Schema::hasColumn('class_bookings', 'queue_position')) {
                $table->unsignedInteger('queue_position')->nullable()->after('status');
            }
            if (! Schema::hasColumn('class_bookings', 'cancelled_at')) {
                $table->dateTime('cancelled_at')->nullable()->after('queue_position');
            }
            if (! Schema::hasColumn('class_bookings', 'checked_in_at')) {
                $table->dateTime('checked_in_at')->nullable()->after('cancelled_at');
            }
        });

        // Note: we keep the unique constraint on (user_id, gym_class_id).
        // The BookingController handles this by reusing (updating) an existing
        // cancelled row rather than inserting a new one for the same user+class.
        // This means one row per (user, class) pair — which is the desired behavior.
    }

    public function down(): void
    {
        Schema::table('class_bookings', function (Blueprint $table) {
            $columns = array_filter(
                ['status', 'queue_position', 'cancelled_at', 'checked_in_at'],
                fn ($col) => Schema::hasColumn('class_bookings', $col)
            );
            if ($columns) {
                $table->dropColumn(array_values($columns));
            }
        });
    }
};
