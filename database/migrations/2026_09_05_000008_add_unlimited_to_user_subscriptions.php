<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('user_subscriptions', 'is_unlimited')) {
            Schema::table('user_subscriptions', function (Blueprint $table) {
                $table->boolean('is_unlimited')->default(false)->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('user_subscriptions', 'is_unlimited')) {
            Schema::table('user_subscriptions', function (Blueprint $table) {
                $table->dropColumn('is_unlimited');
            });
        }
    }
};
