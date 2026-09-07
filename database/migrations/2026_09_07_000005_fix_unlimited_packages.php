<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Fix packages that were created with credits=999 / is_unlimited=false.
     * Any finite package with 999 credits is misconfigured — correct all of them.
     */
    public function up(): void
    {
        DB::table('packages')
            ->where('credits', 999)
            ->where('is_unlimited', false)
            ->update(['is_unlimited' => true, 'credits' => 0]);
    }

    public function down(): void
    {
        // Not reversible without knowing which packages were originally 999-credit.
    }
};
