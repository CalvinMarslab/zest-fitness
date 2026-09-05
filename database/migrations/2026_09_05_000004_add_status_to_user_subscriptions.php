<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('user_subscriptions', 'credits_remaining')) {
                $table->unsignedInteger('credits_remaining')->nullable()->after('credits_granted');
            }
            if (! Schema::hasColumn('user_subscriptions', 'status')) {
                $table->string('status', 20)->default('active')->after('credits_remaining');
            }
            if (! Schema::hasColumn('user_subscriptions', 'assigned_by')) {
                $table->unsignedBigInteger('assigned_by')->nullable()->after('status');
                $table->foreign('assigned_by')->references('id')->on('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('user_subscriptions', function (Blueprint $table) {
            $columns = ['credits_remaining', 'status', 'assigned_by'];
            $toDrop = array_filter($columns, fn ($col) => Schema::hasColumn('user_subscriptions', $col));
            if ($toDrop) {
                if (Schema::hasColumn('user_subscriptions', 'assigned_by')) {
                    $table->dropForeign(['assigned_by']);
                }
                $table->dropColumn(array_values($toDrop));
            }
        });
    }
};
