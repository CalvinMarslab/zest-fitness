<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_booking_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type'); // e.g. 'run', 'cycle', 'hiit'
            $table->unsignedInteger('duration');
            $table->unsignedInteger('distance')->nullable();
            $table->text('polyline_data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
