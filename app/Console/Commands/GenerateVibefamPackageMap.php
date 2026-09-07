<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Generates vibefam-package-map.json from actual DB package records.
 *
 * Resolves every canonical Zest migration package by its DB name, validates its
 * configuration (is_unlimited, credits, weekly_booking_limit), then emits a JSON
 * file mapping every VibeFam source package name to the real package_id.
 *
 * This command must be re-run after every production deploy so the map reflects
 * actual production IDs. Never hand-edit the generated JSON.
 *
 * Usage:
 *   php artisan vibefam:generate-package-map --output=vibefam-package-map.json
 *   php artisan vibefam:generate-package-map --dry-run
 */
class GenerateVibefamPackageMap extends Command
{
    protected $signature = 'vibefam:generate-package-map
        {--output= : Output file path (default: vibefam-package-map.json)}
        {--dry-run : Print the map to stdout without writing a file}';

    protected $description = 'Generate vibefam-package-map.json from actual DB records. Validates all canonical packages exist and are correctly configured.';

    /**
     * Maps each canonical Zest migration name to the actual DB package name
     * and the expected configuration that must be verified before the map is emitted.
     *
     * unlimited=true  → DB package must have is_unlimited=true AND credits=0
     * unlimited=false → DB package must have is_unlimited=false
     * weekly_cap=N    → DB package must have weekly_booking_limit=N
     */
    private const CANONICAL_PACKAGES = [
        '10 Credit Package' => ['db' => '10 Credit Package',           'unlimited' => false, 'weekly_cap' => null],
        '12 Credit Package' => ['db' => '12 Credit Package',           'unlimited' => false, 'weekly_cap' => null],
        '20 Credit Package' => ['db' => '20 Credit Package',           'unlimited' => false, 'weekly_cap' => null],
        '25 Credit Package' => ['db' => '25 Credit Package',           'unlimited' => false, 'weekly_cap' => null],
        '50 Credit Package' => ['db' => '50 Credit Package',           'unlimited' => false, 'weekly_cap' => null],
        '120 Credit Package' => ['db' => '120 Credit Package',          'unlimited' => false, 'weekly_cap' => null],
        '200 Credit Package' => ['db' => '200 Credit Package',          'unlimited' => false, 'weekly_cap' => null],
        'Zestlethic Program' => ['db' => 'Zestlethic Program',          'unlimited' => false, 'weekly_cap' => null],
        'Legacy — 9 Months Unlimited' => ['db' => 'Legacy — 9 Months Unlimited', 'unlimited' => true,  'weekly_cap' => null],
        'Limited Plan — 2x Per Week' => ['db' => 'Limited 1-Month',            'unlimited' => false, 'weekly_cap' => 2],
        'Hyrox Unlimited — 1 Month' => ['db' => 'HYROX 1-Month',              'unlimited' => true,  'weekly_cap' => null],
        'Hyrox Unlimited — 3 Months' => ['db' => 'HYROX 3-Month',              'unlimited' => true,  'weekly_cap' => null],
        'Full Unlimited — 1 Month' => ['db' => 'Full Unlimited 1-Month',      'unlimited' => true,  'weekly_cap' => null],
        'Full Unlimited — 6 Months' => ['db' => 'Full Unlimited 6-Month',      'unlimited' => true,  'weekly_cap' => null],
        'Full Unlimited — 12 Months' => ['db' => 'Full Unlimited 12-Month',     'unlimited' => true,  'weekly_cap' => null],
    ];

    public function handle(): int
    {
        $errors = [];

        // Step 1: resolve canonical name → real DB package_id, with validation
        $canonicalToId = [];
        foreach (self::CANONICAL_PACKAGES as $canonicalName => $spec) {
            $matches = DB::table('packages')
                ->where('name', $spec['db'])
                ->get(['id', 'name', 'credits', 'is_unlimited', 'weekly_booking_limit']);

            if ($matches->isEmpty()) {
                $errors[] = "MISSING: No package named '{$spec['db']}' (canonical: '{$canonicalName}')";

                continue;
            }

            if ($matches->count() > 1) {
                $ids = $matches->pluck('id')->implode(', ');
                $errors[] = "AMBIGUOUS: Multiple packages match '{$spec['db']}' (IDs: {$ids})";

                continue;
            }

            $pkg = $matches->first();
            $pkgErrors = $this->validatePackageConfig($pkg, $canonicalName, $spec);
            foreach ($pkgErrors as $err) {
                $errors[] = $err;
            }

            if (empty($pkgErrors)) {
                $canonicalToId[$canonicalName] = (int) $pkg->id;
            }
        }

        if (! empty($errors)) {
            $this->error('Package map generation FAILED — fix the following before live import:');
            foreach ($errors as $err) {
                $this->line("  ✗ {$err}");
            }

            return self::FAILURE;
        }

        // Step 2: map every VibeFam source package name → package_id via the canonical bridge
        $packageMap = [];
        foreach (ImportVibefam::VIBEFAM_CANONICAL as $vibefamName => $meta) {
            if (str_starts_with($meta['canon'], '⚠')) {
                $errors[] = "UNCLASSIFIED: VibeFam package '{$vibefamName}' maps to '{$meta['canon']}' — resolve before importing";

                continue;
            }

            $canonicalName = $meta['canon'];
            if (! isset($canonicalToId[$canonicalName])) {
                $errors[] = "NO_ID: VibeFam '{$vibefamName}' → canonical '{$canonicalName}' has no resolved package ID";

                continue;
            }

            $packageMap[$vibefamName] = $canonicalToId[$canonicalName];
        }

        if (! empty($errors)) {
            $this->error('Package map generation FAILED:');
            foreach ($errors as $err) {
                $this->line("  ✗ {$err}");
            }

            return self::FAILURE;
        }

        // Step 3: emit the JSON
        ksort($packageMap);

        $output = [
            '_generated_at' => now()->toIso8601String(),
            '_note' => 'Generated by vibefam:generate-package-map. Contains no PII. Re-run after every deploy to reflect real production IDs.',
        ] + $packageMap;

        $json = json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if ($this->option('dry-run')) {
            $this->line($json);
            $this->newLine();
            $this->info('Dry run — no file written.');

            return self::SUCCESS;
        }

        $outputPath = $this->option('output') ?? 'vibefam-package-map.json';
        file_put_contents($outputPath, $json);

        $this->info("Package map written to: {$outputPath}");
        $this->line('SHA-256: '.hash('sha256', $json));

        return self::SUCCESS;
    }

    private function validatePackageConfig(object $pkg, string $canonicalName, array $spec): array
    {
        $errors = [];
        $ref = "ID={$pkg->id} '{$pkg->name}' (canonical: '{$canonicalName}')";

        if ($spec['unlimited']) {
            if (! $pkg->is_unlimited) {
                $errors[] = "INVALID: {$ref} — expected is_unlimited=true, got false";
            }
            if ((int) $pkg->credits !== 0) {
                $errors[] = "INVALID: {$ref} — unlimited package must have credits=0, got {$pkg->credits}";
            }
        } else {
            if ($pkg->is_unlimited) {
                $errors[] = "INVALID: {$ref} — expected is_unlimited=false, got true";
            }
        }

        if ($spec['weekly_cap'] !== null) {
            $actual = $pkg->weekly_booking_limit !== null ? (int) $pkg->weekly_booking_limit : null;
            if ($actual !== $spec['weekly_cap']) {
                $errors[] = "INVALID: {$ref} — expected weekly_booking_limit={$spec['weekly_cap']}, got ".($actual ?? 'null');
            }
        }

        return $errors;
    }
}
