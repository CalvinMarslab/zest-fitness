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
        Schema::create('class_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('coach');
            $table->unsignedTinyInteger('day_of_week'); // 0=Sun … 6=Sat
            $table->string('start_time');               // "HH:MM"
            $table->unsignedInteger('capacity')->default(20);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('class_templates');
    }
};
