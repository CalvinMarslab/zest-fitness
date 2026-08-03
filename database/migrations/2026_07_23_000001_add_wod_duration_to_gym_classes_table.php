<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gym_classes', function (Blueprint $table) {
            if (!Schema::hasColumn('gym_classes', 'wod_duration')) {
                $table->string('wod_duration')->nullable()->after('wod_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('gym_classes', function (Blueprint $table) {
            $table->dropColumn('wod_duration');
        });
    }
};
