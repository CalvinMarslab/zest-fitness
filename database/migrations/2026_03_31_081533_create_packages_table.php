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
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');                          // e.g. "Starter", "Pro"
            $table->text('description')->nullable();
            $table->unsignedInteger('credits');              // credits granted on activation
            $table->unsignedInteger('period_days');          // 30 = 1 month, 90 = 3 months
            $table->decimal('price', 8, 2)->default(0);     // display price (MYR)
            $table->string('badge')->nullable();             // e.g. "Most Popular"
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};
