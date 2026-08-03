<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gym_classes', function (Blueprint $table) {
            if (!Schema::hasColumn('gym_classes', 'wod_config')) {
                $table->json('wod_config')->nullable()->after('wod_duration');
            }
        });
    }

    public function down(): void
    {
        Schema::table('gym_classes', function (Blueprint $table) {
            $table->dropColumn('wod_config');
        });
    }
};
