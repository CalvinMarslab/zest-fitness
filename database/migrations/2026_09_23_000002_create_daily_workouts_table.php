<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('daily_workouts', function (Blueprint $table) {
            $table->id();
            $table->date('workout_date');
            $table->string('program', 20);
            $table->string('title')->nullable();
            $table->text('workout');
            $table->text('coach_notes')->nullable();
            $table->boolean('is_published')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['workout_date', 'program']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_workouts');
    }
};
