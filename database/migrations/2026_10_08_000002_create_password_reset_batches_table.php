<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('password_reset_batches', function (Blueprint $table) {
            $table->id();
            $table->uuid('batch_uuid')->unique();
            $table->json('member_ids');
            $table->unsignedInteger('member_count');
            $table->boolean('is_dry_run')->default(false);
            $table->timestamp('executed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_batches');
    }
};
