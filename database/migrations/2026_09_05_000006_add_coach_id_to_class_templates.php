<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_templates', function (Blueprint $table) {
            if (! Schema::hasColumn('class_templates', 'coach_id')) {
                $table->unsignedBigInteger('coach_id')->nullable()->after('coach');
                $table->foreign('coach_id')->references('id')->on('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('class_templates', 'description')) {
                $table->text('description')->nullable()->after('coach_id');
            }
            if (! Schema::hasColumn('class_templates', 'location')) {
                $table->string('location', 100)->nullable()->after('description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('class_templates', function (Blueprint $table) {
            $columns = ['coach_id', 'description', 'location'];
            $toDrop = array_filter($columns, fn ($col) => Schema::hasColumn('class_templates', $col));
            if ($toDrop) {
                if (Schema::hasColumn('class_templates', 'coach_id')) {
                    $table->dropForeign(['coach_id']);
                }
                $table->dropColumn(array_values($toDrop));
            }
        });
    }
};
