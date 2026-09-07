<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds 9 legacy/historical packages required as FK anchors for VibeFam-migrated subscriptions.
 * These packages are inactive (not for new purchase) but must exist so imported user_subscriptions
 * have valid package_id references.
 *
 * Also sets weekly_booking_limit=2 on "Limited 1-Month" (ID=1) to enforce the VibeFam
 * "Limited Plan (2x a week)" cap that was agreed at migration planning.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        // Enforce weekly cap on the existing Limited 1-Month package
        DB::table('packages')
            ->where('name', 'Limited 1-Month')
            ->update(['weekly_booking_limit' => 2, 'updated_at' => $now]);

        // Legacy finite-credit packages — not for new purchase (is_active=false)
        $legacyFinite = [
            ['name' => '10 Credit Package',  'credits' => 10,  'period_days' => 60,  'price' => 100,  'badge' => 'Legacy'],
            ['name' => '12 Credit Package',  'credits' => 12,  'period_days' => 60,  'price' => 120,  'badge' => 'Legacy'],
            ['name' => '20 Credit Package',  'credits' => 20,  'period_days' => 90,  'price' => 180,  'badge' => 'Legacy'],
            ['name' => '25 Credit Package',  'credits' => 25,  'period_days' => 90,  'price' => 220,  'badge' => 'Legacy'],
            ['name' => '50 Credit Package',  'credits' => 50,  'period_days' => 120, 'price' => 420,  'badge' => 'Legacy'],
            ['name' => '120 Credit Package', 'credits' => 120, 'period_days' => 365, 'price' => 960,  'badge' => 'Legacy'],
            ['name' => '200 Credit Package', 'credits' => 200, 'period_days' => 365, 'price' => 1500, 'badge' => 'Legacy'],
            ['name' => 'Zestlethic Program', 'credits' => 0,   'period_days' => 365, 'price' => 0,    'badge' => 'Legacy'],
        ];

        foreach ($legacyFinite as $pkg) {
            $exists = DB::table('packages')->where('name', $pkg['name'])->exists();
            if (! $exists) {
                DB::table('packages')->insert(array_merge($pkg, [
                    'description' => 'Legacy VibeFam package (historical reference only).',
                    'is_active' => false,
                    'is_trial' => false,
                    'is_unlimited' => false,
                    'weekly_booking_limit' => null,
                    'sort_order' => 100,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
        }

        // Legacy unlimited package — all subscribers expired, inactive
        $exists = DB::table('packages')->where('name', 'Legacy — 9 Months Unlimited')->exists();
        if (! $exists) {
            DB::table('packages')->insert([
                'name' => 'Legacy — 9 Months Unlimited',
                'description' => 'Legacy VibeFam 9-month unlimited package (historical reference only).',
                'credits' => 0,
                'is_active' => false,
                'is_trial' => false,
                'is_unlimited' => true,
                'weekly_booking_limit' => null,
                'period_days' => 270,
                'price' => 0,
                'badge' => 'Legacy',
                'sort_order' => 100,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        $legacyNames = [
            '10 Credit Package',
            '12 Credit Package',
            '20 Credit Package',
            '25 Credit Package',
            '50 Credit Package',
            '120 Credit Package',
            '200 Credit Package',
            'Zestlethic Program',
            'Legacy — 9 Months Unlimited',
        ];

        DB::table('packages')->whereIn('name', $legacyNames)->delete();

        DB::table('packages')
            ->where('name', 'Limited 1-Month')
            ->update(['weekly_booking_limit' => null]);
    }
};
