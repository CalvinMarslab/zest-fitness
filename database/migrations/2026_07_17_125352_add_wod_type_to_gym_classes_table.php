<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('gym_classes', function (Blueprint $table) {
            if (!Schema::hasColumn('gym_classes', 'wod_type')) {
                $table->string('wod_type')->nullable()->after('exercises');
            }
        });
    }

    public function down(): void
    {
        Schema::table('gym_classes', function (Blueprint $table) {
            $table->dropColumn('wod_type');
        });
    }
};
