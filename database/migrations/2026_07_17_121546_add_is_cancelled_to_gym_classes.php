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
            if (!Schema::hasColumn('gym_classes', 'is_cancelled')) {
                $table->boolean('is_cancelled')->default(false)->after('exercises');
            }
        });
    }

    public function down(): void
    {
        Schema::table('gym_classes', function (Blueprint $table) {
            $table->dropColumn('is_cancelled');
        });
    }
};
