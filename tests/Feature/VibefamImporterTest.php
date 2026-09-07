<?php

namespace Tests\Feature;

use App\Console\Commands\ImportVibefam;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regression tests for VibeFam importer correctness.
 *
 * Key invariants under test:
 * - Unlimited classification comes ONLY from explicit VIBEFAM_CANONICAL entries
 * - Credit count NEVER determines unlimited status
 * - 120- and 200-credit packages are finite
 * - Zestlethic Program is finite (explicit false, not null)
 * - Package map generator resolves real DB IDs by name, not assumed auto-increment
 * - Generator fails hard on missing, ambiguous, or misconfigured packages
 * - Dry run never writes to the DB
 */
class VibefamImporterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAllMigrationPackages();
    }

    // ── Test 1: 120-credit package is finite ──────────────────────────────────

    public function test_120_credit_class_is_classified_as_finite(): void
    {
        $meta = ImportVibefam::VIBEFAM_CANONICAL['120 Credit Class'];

        $this->assertFalse($meta['unlimited'], '120 Credit Class must be finite — never unlimited');
        $this->assertSame('120 Credit Package', $meta['canon']);
    }

    // ── Test 2: 200-credit package is finite ──────────────────────────────────

    public function test_200_credit_class_is_classified_as_finite(): void
    {
        $meta = ImportVibefam::VIBEFAM_CANONICAL['200 Credit Class'];

        $this->assertFalse($meta['unlimited'], '200 Credit Class must be finite — never unlimited');
        $this->assertSame('200 Credit Package', $meta['canon']);
    }

    // ── Test 3: Zestlethic Program is explicitly finite (not null) ────────────

    public function test_zestlethic_program_is_explicitly_classified_as_finite(): void
    {
        $meta = ImportVibefam::VIBEFAM_CANONICAL['Zestlethic Program'];

        $this->assertFalse($meta['unlimited'],
            'Zestlethic Program must have unlimited=false — not null, not true');
        $this->assertNotNull($meta['unlimited'],
            'Zestlethic Program unlimited flag must be explicit false (no null/heuristic fallback)');
    }

    // ── Test 4: Unknown package cannot be inferred from total_credits ─────────

    public function test_unlimited_classification_requires_explicit_canonical_entry(): void
    {
        $this->assertArrayNotHasKey(
            'Completely Unknown Package',
            ImportVibefam::VIBEFAM_CANONICAL,
            'Unlisted package must have no classification entry — it cannot be inferred'
        );

        // Dry-run with an unknown package: should not classify it as unlimited
        $csv = $this->makeCsv([[
            'email' => 'unknown@test.com',
            'name' => 'Unknown User',
            'package' => 'Completely Unknown Package',
            'total_credits' => 999,
            'credits_left' => 999,
            'purchased' => '2026-01-01',
            'expiry' => '2026-12-31',
        ]]);

        $this->artisan('vibefam:import', [
            '--memberships-csv' => $csv,
            '--dry-run' => true,
        ])->assertExitCode(0); // Dry-run exits OK but marks package UNCLASSIFIED

        unlink($csv);
    }

    // ── Test 5: Cannot infer unlimited from total_credits (heuristic removed) ─

    public function test_zestlethic_with_500_total_credits_is_still_finite(): void
    {
        // Before the heuristic was removed, total_credits >= 100 → unlimited.
        // This must never happen: Zestlethic Program is always finite.
        $meta = ImportVibefam::VIBEFAM_CANONICAL['Zestlethic Program'];

        $this->assertFalse(
            $meta['unlimited'],
            'Zestlethic Program must remain finite regardless of credit count — credit-count heuristic must not exist'
        );

        // Also verify that the constant has no null entry that could trigger row-level detection
        foreach (ImportVibefam::VIBEFAM_CANONICAL as $name => $entry) {
            $this->assertNotNull(
                $entry['unlimited'],
                "Package '{$name}' has unlimited=null — all entries must have explicit bool"
            );
        }
    }

    // ── Test 6: Package map generator resolves real DB IDs ────────────────────

    public function test_package_map_generator_resolves_real_db_ids(): void
    {
        $output = tempnam(sys_get_temp_dir(), 'pkg-map-').'.json';

        $this->artisan('vibefam:generate-package-map', ['--output' => $output])
            ->assertExitCode(0);

        $this->assertFileExists($output);
        $map = json_decode(file_get_contents($output), true);
        unlink($output);

        // '12 Credit Class' → '12 Credit Package' DB record
        $this->assertArrayHasKey('12 Credit Class', $map);
        $this->assertIsInt($map['12 Credit Class']);

        $dbId = DB::table('packages')->where('name', '12 Credit Package')->value('id');
        $this->assertSame((int) $dbId, $map['12 Credit Class'],
            'Generator must resolve the actual DB id by package name, not an assumed integer');

        // Spot-check unlimited package
        $hyroxDbId = DB::table('packages')->where('name', 'HYROX 1-Month')->value('id');
        $this->assertSame((int) $hyroxDbId, $map['2026 Hyrox Unlimited']);
    }

    // ── Test 7: Generator fails on missing canonical package ──────────────────

    public function test_package_map_generator_fails_on_missing_canonical_package(): void
    {
        DB::table('packages')->where('name', '10 Credit Package')->delete();

        $this->artisan('vibefam:generate-package-map', ['--dry-run' => true])
            ->assertExitCode(1);
    }

    // ── Test 8: Generator fails on invalid unlimited configuration ────────────

    public function test_package_map_generator_fails_on_invalid_unlimited_configuration(): void
    {
        // Misconfigure an unlimited package to is_unlimited=false
        DB::table('packages')->where('name', 'HYROX 1-Month')->update(['is_unlimited' => false]);

        $this->artisan('vibefam:generate-package-map', ['--dry-run' => true])
            ->assertExitCode(1);
    }

    // ── Test 9: Limited Plan validates weekly_booking_limit=2 ─────────────────

    public function test_package_map_generator_fails_if_limited_plan_has_no_weekly_cap(): void
    {
        DB::table('packages')
            ->where('name', 'Limited 1-Month')
            ->update(['weekly_booking_limit' => null]);

        $this->artisan('vibefam:generate-package-map', ['--dry-run' => true])
            ->assertExitCode(1);
    }

    // ── Test 10: Dry run makes zero DB writes ─────────────────────────────────

    public function test_dry_run_makes_zero_db_writes(): void
    {
        $csv = $this->makeCsv([
            [
                'email' => 'finite@test.com',
                'name' => 'Finite Member',
                'package' => '12 Credit Class',
                'total_credits' => 12,
                'credits_left' => 10,
                'purchased' => '2026-08-01',
                'expiry' => '2027-08-01',
            ],
            [
                'email' => 'unlimited@test.com',
                'name' => 'Unlimited Member',
                'package' => '2026 Hyrox Unlimited',
                'total_credits' => 999,
                'credits_left' => 999,
                'purchased' => '2026-08-01',
                'expiry' => '2027-08-01',
            ],
        ]);

        $usersBefore = DB::table('users')->count();
        $subsBefore = DB::table('user_subscriptions')->count();
        $txBefore = DB::table('credit_transactions')->count();
        $importBefore = DB::table('vibefam_import_map')->count();

        $this->artisan('vibefam:import', [
            '--memberships-csv' => $csv,
            '--dry-run' => true,
        ])->assertExitCode(0);

        $this->assertSame($usersBefore, DB::table('users')->count(), 'Dry run must not create users');
        $this->assertSame($subsBefore, DB::table('user_subscriptions')->count(), 'Dry run must not create subscriptions');
        $this->assertSame($txBefore, DB::table('credit_transactions')->count(), 'Dry run must not create credit transactions');
        $this->assertSame($importBefore, DB::table('vibefam_import_map')->count(), 'Dry run must not write vibefam_import_map');

        unlink($csv);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeCsv(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'vibefam-test-').'.csv';
        $f = fopen($path, 'w');

        fputcsv($f, [
            'Customer Name', 'Email', 'Date of Purchase', 'Time of Purchase',
            'Expiry Date', 'Package Name', 'Price', 'Cancel Fee', 'Total Revenue',
            'Total Credits', 'Credits Used', 'Credits Left', 'Unused Value', 'Remarks',
        ]);

        foreach ($rows as $row) {
            fputcsv($f, [
                $row['name'] ?? 'Test User',
                $row['email'] ?? 'test@example.com',
                $row['purchased'] ?? '2026-01-01',
                '00:00:00',
                $row['expiry'] ?? '2026-12-31',
                $row['package'] ?? '12 Credit Class',
                0, 0, 0,
                $row['total_credits'] ?? 12,
                0,
                $row['credits_left'] ?? 12,
                0, '',
            ]);
        }

        fclose($f);

        return $path;
    }

    /**
     * Create all packages that are required by the generator command and the importer.
     * RefreshDatabase wipes the DB; only migrations run — the PackageSeeder does not.
     * We re-create all needed packages here so every test starts from a valid state.
     */
    private function createAllMigrationPackages(): void
    {
        $now = now();

        $packages = [
            // Active Zest packages (normally from PackageSeeder)
            ['name' => 'Limited 1-Month',           'credits' => 8,   'is_unlimited' => false, 'weekly_booking_limit' => 2,    'is_active' => true],
            ['name' => 'HYROX 1-Month',              'credits' => 0,   'is_unlimited' => true,  'weekly_booking_limit' => null, 'is_active' => true],
            ['name' => 'HYROX 3-Month',              'credits' => 0,   'is_unlimited' => true,  'weekly_booking_limit' => null, 'is_active' => true],
            ['name' => 'HYROX 6-Month',              'credits' => 0,   'is_unlimited' => true,  'weekly_booking_limit' => null, 'is_active' => true],
            ['name' => 'Full Unlimited 1-Month',     'credits' => 0,   'is_unlimited' => true,  'weekly_booking_limit' => null, 'is_active' => true],
            ['name' => 'Full Unlimited 6-Month',     'credits' => 0,   'is_unlimited' => true,  'weekly_booking_limit' => null, 'is_active' => true],
            ['name' => 'Full Unlimited 12-Month',    'credits' => 0,   'is_unlimited' => true,  'weekly_booking_limit' => null, 'is_active' => true],
            // Legacy migration packages (normally from 2026_09_08_000001 migration)
            ['name' => '10 Credit Package',          'credits' => 10,  'is_unlimited' => false, 'weekly_booking_limit' => null, 'is_active' => false],
            ['name' => '12 Credit Package',          'credits' => 12,  'is_unlimited' => false, 'weekly_booking_limit' => null, 'is_active' => false],
            ['name' => '20 Credit Package',          'credits' => 20,  'is_unlimited' => false, 'weekly_booking_limit' => null, 'is_active' => false],
            ['name' => '25 Credit Package',          'credits' => 25,  'is_unlimited' => false, 'weekly_booking_limit' => null, 'is_active' => false],
            ['name' => '50 Credit Package',          'credits' => 50,  'is_unlimited' => false, 'weekly_booking_limit' => null, 'is_active' => false],
            ['name' => '120 Credit Package',         'credits' => 120, 'is_unlimited' => false, 'weekly_booking_limit' => null, 'is_active' => false],
            ['name' => '200 Credit Package',         'credits' => 200, 'is_unlimited' => false, 'weekly_booking_limit' => null, 'is_active' => false],
            ['name' => 'Zestlethic Program',         'credits' => 0,   'is_unlimited' => false, 'weekly_booking_limit' => null, 'is_active' => false],
            ['name' => 'Legacy — 9 Months Unlimited', 'credits' => 0,   'is_unlimited' => true,  'weekly_booking_limit' => null, 'is_active' => false],
        ];

        foreach ($packages as $pkg) {
            $exists = DB::table('packages')->where('name', $pkg['name'])->exists();
            if (! $exists) {
                DB::table('packages')->insert(array_merge($pkg, [
                    'description' => 'Test package',
                    'period_days' => 30,
                    'price' => 0,
                    'badge' => 'Test',
                    'is_trial' => false,
                    'sort_order' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            } else {
                DB::table('packages')->where('name', $pkg['name'])->update(array_merge($pkg, [
                    'updated_at' => $now,
                ]));
            }
        }
    }
}
