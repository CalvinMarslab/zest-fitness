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
            if (!Schema::hasColumn('gym_classes', 'template_id')) {
                $table->foreignId('template_id')->nullable()->after('id')
                      ->constrained('class_templates')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('gym_classes', function (Blueprint $table) {
            $table->dropForeign(['template_id']);
            $table->dropColumn('template_id');
        });
    }
};
