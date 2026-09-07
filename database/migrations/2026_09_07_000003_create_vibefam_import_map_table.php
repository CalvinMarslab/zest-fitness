<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vibefam_import_map', function (Blueprint $table) {
            $table->id();
            $table->string('source_system', 20)->default('vibefam');
            $table->string('source_key', 64);
            $table->string('model_type', 50);
            $table->unsignedBigInteger('model_id')->nullable();
            $table->timestamps();

            $table->unique(['source_system', 'source_key', 'model_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vibefam_import_map');
    }
};
