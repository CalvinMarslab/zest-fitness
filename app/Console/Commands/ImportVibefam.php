<?php

namespace App\Console\Commands;

use App\Models\CreditTransaction;
use App\Models\User;
use App\Models\UserSubscription;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Imports VibeFam members and subscription data into Zest.
 *
 * Dry-run (no package map needed):
 *   php artisan vibefam:import --memberships-csv=path/to/memberships.csv --dry-run
 *
 * Live import (package map required):
 *   php artisan vibefam:import --memberships-csv=... --package-map=path/to/map.json
 *
 * Package map format (JSON):
 *   { "VibeFam Package Name": <package_id>, ... }
 *   or with explicit unlimited override for unclassified packages:
 *   { "VibeFam Package Name": { "package_id": N, "is_unlimited": true }, ... }
 *
 * Unlimited subscriptions: is_unlimited=true, credits_remaining=0.
 * VibeFam pseudo-credits (999/998/etc.) are NOT imported as Zest credits.
 * Opening balance credit transactions are created ONLY for finite-credit subscriptions.
 */
class ImportVibefam extends Command
{
    protected $signature = 'vibefam:import
        {--memberships-csv= : Path to VibeFam Memberships Report CSV (all-time export)}
        {--members-csv= : Path to complete Members list CSV for zero-package members}
        {--package-map= : Path to JSON file mapping VibeFam package names to Zest package IDs}
        {--dry-run : Preview without writing to the database}';

    protected $description = 'Import VibeFam members and subscriptions into Zest (use --dry-run first)';

    /**
     * Canonical mapping: VibeFam package name → Zest canonical name + classification.
     *
     * Every entry MUST have an explicit boolean `unlimited` value — true or false.
     * Unlimited status is NEVER inferred from credit count or any other heuristic.
     * 120-credit and 200-credit packages are FINITE. Zestlethic Program is FINITE.
     *
     * canon starting with ⚠ means UNCLASSIFIED — blocks live import until resolved.
     */
    public const VIBEFAM_CANONICAL = [
        // ── Finite credit packages ─────────────────────────────────────────────
        '12 Credit Package' => ['canon' => '12 Credit Package',             'unlimited' => false, 'limited_plan' => false],
        '12 Credit Class' => ['canon' => '12 Credit Package',             'unlimited' => false, 'limited_plan' => false],
        '10 Credit Class' => ['canon' => '10 Credit Package',             'unlimited' => false, 'limited_plan' => false],
        '2026Q2 - 10 Class Pass' => ['canon' => '10 Credit Package',             'unlimited' => false, 'limited_plan' => false],
        '2026 10 Credit Class' => ['canon' => '10 Credit Package',             'unlimited' => false, 'limited_plan' => false],
        '2026Q2 - 20 Class Pass' => ['canon' => '20 Credit Package',             'unlimited' => false, 'limited_plan' => false],
        '2026 20 Credit Class' => ['canon' => '20 Credit Package',             'unlimited' => false, 'limited_plan' => false],
        '25 Credit Class' => ['canon' => '25 Credit Package',             'unlimited' => false, 'limited_plan' => false],
        '50 Credit Class' => ['canon' => '50 Credit Package',             'unlimited' => false, 'limited_plan' => false],
        '50 credit package' => ['canon' => '50 Credit Package',             'unlimited' => false, 'limited_plan' => false],
        '120 Credit Class' => ['canon' => '120 Credit Package',            'unlimited' => false, 'limited_plan' => false],
        '200 Credit Class' => ['canon' => '200 Credit Package',            'unlimited' => false, 'limited_plan' => false],
        // ── Limited plan (finite credits + weekly booking cap) ─────────────────
        '2026Q2 Limited Plan (2x a week)' => ['canon' => 'Limited Plan — 2x Per Week',   'unlimited' => false, 'limited_plan' => true],
        '2026 Limited Plan (2x per week)' => ['canon' => 'Limited Plan — 2x Per Week',   'unlimited' => false, 'limited_plan' => true],
        // ── Hyrox Unlimited ────────────────────────────────────────────────────
        '2026 Hyrox Unlimited' => ['canon' => 'Hyrox Unlimited — 1 Month',    'unlimited' => true,  'limited_plan' => false],
        'Unlimited Hyrox' => ['canon' => 'Hyrox Unlimited — 1 Month',    'unlimited' => true,  'limited_plan' => false],
        'Hyrox Unlimited' => ['canon' => 'Hyrox Unlimited — 1 Month',    'unlimited' => true,  'limited_plan' => false],
        'Hyrox Unlimited 1 Month' => ['canon' => 'Hyrox Unlimited — 1 Month',    'unlimited' => true,  'limited_plan' => false],
        '2026 3 Months Unlimited Hyrox' => ['canon' => 'Hyrox Unlimited — 3 Months',   'unlimited' => true,  'limited_plan' => false],
        'Hyrox Unlimited 3 Month' => ['canon' => 'Hyrox Unlimited — 3 Months',   'unlimited' => true,  'limited_plan' => false],
        // ── Full Unlimited ─────────────────────────────────────────────────────
        '2026 1 Month Unlimited' => ['canon' => 'Full Unlimited — 1 Month',      'unlimited' => true,  'limited_plan' => false],
        '2026 6 Month Unlimited' => ['canon' => 'Full Unlimited — 6 Months',     'unlimited' => true,  'limited_plan' => false],
        '2026 12 Month Unlimited' => ['canon' => 'Full Unlimited — 12 Months',    'unlimited' => true,  'limited_plan' => false],
        '12 Months Unlimited' => ['canon' => 'Full Unlimited — 12 Months',    'unlimited' => true,  'limited_plan' => false],
        // ── Special / Legacy ───────────────────────────────────────────────────
        'Zestlethic Program' => ['canon' => 'Zestlethic Program',            'unlimited' => false, 'limited_plan' => false],
        // ── Resolved legacy (previously unclassified) ─────────────────────────
        // Approved: treat as Full Unlimited — 12 Months (full class access, 12-month validity)
        '2026Q2 Full Unlimited 12 months' => ['canon' => 'Full Unlimited — 12 Months',    'unlimited' => true,  'limited_plan' => false],
        // Both subscriptions expired; inactive legacy package, not for public sale
        '9 Months Unlimited' => ['canon' => 'Legacy — 9 Months Unlimited',   'unlimited' => true,  'limited_plan' => false],
    ];

    private array $warnings = [];

    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $membershipsFile = $this->option('memberships-csv');
        $membersFile = $this->option('members-csv');
        $packageMapFile = $this->option('package-map');

        if (! $membershipsFile) {
            $this->error('--memberships-csv is required.');

            return self::FAILURE;
        }
        if (! file_exists($membershipsFile)) {
            $this->error("File not found: {$membershipsFile}");

            return self::FAILURE;
        }

        $mode = $isDryRun
            ? '<fg=yellow>[DRY RUN — no DB writes]</>'
            : '<fg=red;options=bold>[LIVE IMPORT]</>';
        $this->line("\n{$mode} VibeFam → Zest Migration\n");

        $memberships = $this->parseMembershipsCsv($membershipsFile);
        $membersList = ($membersFile && file_exists($membersFile))
            ? $this->parseMembersCsv($membersFile)
            : [];

        if (empty($memberships)) {
            $this->error('Memberships CSV is empty or could not be parsed.');

            return self::FAILURE;
        }

        $this->validateRows($memberships);

        $duplicates = $this->detectDuplicates($memberships);
        $byEmail = $this->groupByEmail($memberships);
        $membershipEmails = array_map('strtolower', array_keys($byEmail));
        $zeroPackageMembers = array_values(array_filter(
            $membersList,
            fn ($m) => ! in_array(strtolower(trim($m['email'] ?? '')), $membershipEmails, true)
        ));

        $packageMap = [];
        if ($packageMapFile && file_exists($packageMapFile)) {
            $packageMap = json_decode(file_get_contents($packageMapFile), true) ?? [];
        }

        $this->printReport($memberships, $byEmail, $packageMap, $zeroPackageMembers, $duplicates, $membersList);

        if ($isDryRun) {
            $this->newLine();
            $this->info('Dry run complete. No data was written.');

            return self::SUCCESS;
        }

        // ── Live import guards ─────────────────────────────────────────────────
        if (empty($packageMap)) {
            $this->error('--package-map is required for live import.');

            return self::FAILURE;
        }

        $uniquePackages = $this->extractUniquePackages($memberships);
        $unmapped = array_diff($uniquePackages, array_keys($packageMap));
        if (! empty($unmapped)) {
            $this->error('Packages in CSV have no entry in --package-map:');
            foreach ($unmapped as $pkg) {
                $this->line("  - {$pkg}");
            }

            return self::FAILURE;
        }

        // Block unclassified packages that lack an explicit is_unlimited override
        $blocked = [];
        foreach ($uniquePackages as $pkg) {
            $meta = self::VIBEFAM_CANONICAL[$pkg] ?? null;
            if ($meta && str_starts_with($meta['canon'], '⚠')) {
                $mapEntry = $packageMap[$pkg] ?? null;
                $override = is_array($mapEntry) && array_key_exists('is_unlimited', $mapEntry);
                if (! $override) {
                    $blocked[] = $pkg;
                }
            }
        }
        if (! empty($blocked)) {
            $this->error('UNCLASSIFIED packages missing is_unlimited override in --package-map:');
            foreach ($blocked as $b) {
                $this->line("  - {$b}");
            }

            return self::FAILURE;
        }

        if (! empty($this->warnings)) {
            $this->warn(count($this->warnings).' warnings — review dry-run output before proceeding.');
            if (! $this->confirm('Proceed with live import despite warnings?', false)) {
                return self::FAILURE;
            }
        } else {
            if (! $this->confirm('Run live import? This will create users and subscriptions.', false)) {
                return self::FAILURE;
            }
        }

        return $this->runLiveImport($byEmail, $zeroPackageMembers, $packageMap, $duplicates);
    }

    // ── CSV Parsers ──────────────────────────────────────────────────────────────

    private function parseMembershipsCsv(string $path): array
    {
        return $this->parseCsv($path);
    }

    private function parseMembersCsv(string $path): array
    {
        return $this->parseCsv($path);
    }

    private function parseCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if (! $handle) {
            return [];
        }

        $rawHeaders = fgetcsv($handle);
        if (! $rawHeaders) {
            fclose($handle);

            return [];
        }

        $headers = array_map([$this, 'normalizeHeader'], $rawHeaders);
        $rows = [];

        while (($data = fgetcsv($handle)) !== false) {
            if (empty(array_filter($data))) {
                continue;
            }
            $padded = array_pad(array_slice($data, 0, count($headers)), count($headers), '');
            $row = array_combine($headers, $padded);
            $rows[] = array_map('trim', $row);
        }

        fclose($handle);

        return $rows;
    }

    private function normalizeHeader(string $header): string
    {
        $map = [
            'customer name' => 'customer_name',
            'name' => 'customer_name',
            'email' => 'email',
            'date of purchase' => 'date_of_purchase',
            'time of purchase' => 'time_of_purchase',
            'expiry date' => 'expiry_date',
            'package name' => 'package_name',
            'price' => 'price',
            'cancel fee' => 'cancel_fee',
            'total revenue' => 'total_revenue',
            'total credits' => 'total_credits',
            'credits used' => 'credits_used',
            'credits left' => 'credits_left',
            'unused value' => 'unused_value',
            'remarks' => 'remarks',
            'mobile number' => 'phone',
            'mobile' => 'phone',
            'phone' => 'phone',
            'sales amount' => 'sales_amount',
            'number of packages' => 'num_packages',
            'bookings' => 'bookings',
            'attendances' => 'attendances',
        ];

        $lower = strtolower(trim($header));

        return $map[$lower] ?? Str::snake($lower);
    }

    // ── Row classification ───────────────────────────────────────────────────────

    /**
     * Returns ['canon', 'unlimited' (bool), 'limited_plan' (bool)].
     * Classification is determined solely by VIBEFAM_CANONICAL — never by credit counts.
     * Unknown packages return canon='⚠ UNCLASSIFIED' with unlimited=null.
     */
    private function classifyRow(array $row): array
    {
        $pkg = $row['package_name'] ?? '';

        return self::VIBEFAM_CANONICAL[$pkg] ?? [
            'canon' => '⚠ UNCLASSIFIED',
            'unlimited' => null,
            'limited_plan' => false,
        ];
    }

    private function isRowUnlimited(array $row): bool
    {
        return (bool) $this->classifyRow($row)['unlimited'];
    }

    // ── Validation ───────────────────────────────────────────────────────────────

    private function validateRows(array $memberships): void
    {
        foreach ($memberships as $i => $row) {
            $line = $i + 2;
            $ref = 'email='.($row['email'] ?? '?');

            if (empty($row['email'])) {
                $this->warnings[] = "Row {$line}: missing email (name={$row['customer_name']})";
            }
            if (empty($row['package_name'])) {
                $this->warnings[] = "Row {$line}: missing package_name ({$ref})";
            }

            $left = $row['credits_left'] ?? '';
            $total = $row['total_credits'] ?? '';

            if ($left !== '' && ! is_numeric($left)) {
                $this->warnings[] = "Row {$line}: non-numeric credits_left='{$left}' ({$ref})";
            } elseif ($left !== '' && (float) $left < 0) {
                $this->warnings[] = "Row {$line}: negative credits_left={$left} ({$ref})";
            } elseif ($left !== '' && $total !== '' && (float) $left > (float) $total && ! $this->isRowUnlimited($row)) {
                $this->warnings[] = "Row {$line}: credits_left > total_credits ({$left} > {$total}) ({$ref})";
            }

            foreach (['date_of_purchase', 'expiry_date'] as $field) {
                if (! empty($row[$field])) {
                    try {
                        Carbon::parse($row[$field]);
                    } catch (\Exception) {
                        $this->warnings[] = "Row {$line}: invalid date {$field}='{$row[$field]}' ({$ref})";
                    }
                }
            }
        }
    }

    // ── Analysis helpers ─────────────────────────────────────────────────────────

    private function sourceKey(array $row): string
    {
        $raw = implode('|', [
            strtolower(trim($row['email'] ?? '')),
            trim($row['package_name'] ?? ''),
            trim($row['date_of_purchase'] ?? ''),
            trim($row['total_credits'] ?? ''),
        ]);

        return hash('sha256', $raw);
    }

    private function detectDuplicates(array $memberships): array
    {
        $seen = [];
        $dupes = [];
        foreach ($memberships as $i => $row) {
            $key = $this->sourceKey($row);
            if (isset($seen[$key])) {
                $dupes[] = [
                    'row' => $i + 2,
                    'key' => $key,
                    'email' => $row['email'] ?? '',
                    'package' => $row['package_name'] ?? '',
                ];
            } else {
                $seen[$key] = $i + 2;
            }
        }

        return $dupes;
    }

    private function groupByEmail(array $memberships): array
    {
        $groups = [];
        foreach ($memberships as $row) {
            $key = strtolower(trim($row['email'] ?? ''));
            if ($key === '') {
                continue;
            }
            $groups[$key][] = $row;
        }

        return $groups;
    }

    private function extractUniquePackages(array $memberships): array
    {
        return array_values(array_unique(array_filter(array_column($memberships, 'package_name'))));
    }

    private function isExpired(string $dateStr): bool
    {
        if ($dateStr === '') {
            return false;
        }
        try {
            return Carbon::parse($dateStr)->isPast();
        } catch (\Exception) {
            return false;
        }
    }

    // ── Report ───────────────────────────────────────────────────────────────────

    private function printReport(
        array $memberships,
        array $byEmail,
        array $packageMap,
        array $zeroPackageMembers,
        array $duplicates,
        array $membersList,
    ): void {
        $dupeKeys = array_column($duplicates, 'key');

        // Bucket every non-duplicate row by type and active/expired
        $finiteActive = [];
        $finiteExpired = [];
        $unlimActive = [];  // [['canon'=>, 'row'=>], ...]
        $unlimExpired = [];
        $limitedActive = [];
        $limitedExpired = [];
        $unclassified = [];

        foreach ($memberships as $row) {
            if (in_array($this->sourceKey($row), $dupeKeys, true)) {
                continue;
            }
            $meta = $this->classifyRow($row);
            $expired = $this->isExpired($row['expiry_date'] ?? '');

            if ($meta['unlimited'] === null && str_starts_with($meta['canon'], '⚠')) {
                $unclassified[] = $row;
            } elseif ($meta['unlimited'] === true) {
                $entry = ['canon' => $meta['canon'], 'row' => $row];
                $expired ? $unlimExpired[] = $entry : $unlimActive[] = $entry;
            } elseif ($meta['limited_plan']) {
                $expired ? $limitedExpired[] = $row : $limitedActive[] = $row;
            } else {
                $expired ? $finiteExpired[] = $row : $finiteActive[] = $row;
            }
        }

        // User-level entitlement summary
        $usersWithFinite = [];
        $usersWithUnlimited = [];
        $usersNoEntitlement = [];

        foreach ($byEmail as $email => $subs) {
            $hasFinite = false;
            $hasUnlimited = false;

            foreach ($subs as $sub) {
                if ($this->isExpired($sub['expiry_date'] ?? '')) {
                    continue;
                }
                $meta = $this->classifyRow($sub);
                if ($meta['unlimited'] === true) {
                    $hasUnlimited = true;
                } elseif ($meta['unlimited'] === false) {
                    $hasFinite = true;
                }
            }

            if ($hasUnlimited) {
                $usersWithUnlimited[] = $email;
            }
            if ($hasFinite) {
                $usersWithFinite[] = $email;
            }
            if (! $hasUnlimited && ! $hasFinite) {
                $usersNoEntitlement[] = $email;
            }
        }

        $totalFiniteCredits = array_sum(
            array_map(fn ($r) => (int) ($r['credits_left'] ?? 0), $finiteActive)
        );

        // ── TOTALS ─────────────────────────────────────────────────────────────
        $this->section('MIGRATION TOTALS');
        $this->table([], [
            ['Total rows in CSV',                            count($memberships)],
            ['Exact duplicates (auto-skipped)',              count($duplicates)],
            ['Unique members (distinct emails)',             count($byEmail)],
            ['Zero-package members (members-csv only)',      count($zeroPackageMembers)],
            ['Total Zest users to create',                   count($byEmail) + count($zeroPackageMembers)],
        ]);

        // ── FINITE CREDIT PACKAGES ─────────────────────────────────────────────
        $this->section('FINITE CREDIT PACKAGES');
        $this->line('  Active  subscriptions : <fg=green>'.count($finiteActive).'</>');
        $this->line('  Expired subscriptions : '.count($finiteExpired));
        $this->line("  <fg=green;options=bold>Total finite credits remaining (active only): {$totalFiniteCredits}</>");
        $this->newLine();

        $activeByCanon = [];
        foreach ($finiteActive as $row) {
            $c = $this->classifyRow($row)['canon'];
            $activeByCanon[$c][] = $row;
        }
        ksort($activeByCanon);
        $rows = [];
        foreach ($activeByCanon as $canon => $subs) {
            $credits = array_sum(array_map(fn ($s) => (int) ($s['credits_left'] ?? 0), $subs));
            $rows[] = [$canon, count($subs), $credits];
        }
        $this->table(['Zest Package (canonical)', '# Active Subs', 'Credits Remaining'], $rows);

        $expiredByCanon = [];
        foreach ($finiteExpired as $row) {
            $c = $this->classifyRow($row)['canon'];
            $expiredByCanon[$c][] = $row;
        }
        ksort($expiredByCanon);
        $eRows = [];
        foreach ($expiredByCanon as $canon => $subs) {
            $eRows[] = [$canon, count($subs)];
        }
        $this->line('  Expired subscriptions by canonical package:');
        $this->table(['Zest Package (canonical)', '# Expired Subs'], $eRows);

        // ── UNLIMITED PACKAGES ─────────────────────────────────────────────────
        $this->section('UNLIMITED PACKAGES  (is_unlimited=true, credits_remaining=0 in Zest)');
        $this->line('  Active  subscriptions : <fg=green>'.count($unlimActive).'</>');
        $this->line('  Expired subscriptions : '.count($unlimExpired));
        $this->newLine();

        $unlimActiveByCanon = [];
        foreach ($unlimActive as $entry) {
            $unlimActiveByCanon[$entry['canon']][] = $entry['row'];
        }
        ksort($unlimActiveByCanon);
        $uRows = [];
        foreach ($unlimActiveByCanon as $canon => $subs) {
            $type = match (true) {
                str_contains($canon, 'Hyrox') => 'Hyrox-Only',
                str_contains($canon, 'Full') => 'Full Access',
                default => 'Special',
            };
            $emails = Str::limit(implode(', ', array_unique(array_map(fn ($s) => $s['email'], $subs))), 80);
            $uRows[] = [$canon, $type, count($subs), $emails];
        }
        $this->table(['Zest Package (canonical)', 'Type', '# Active', 'Members'], $uRows);

        $unlimExpiredByCanon = [];
        foreach ($unlimExpired as $entry) {
            $unlimExpiredByCanon[$entry['canon']][] = $entry['row'];
        }
        ksort($unlimExpiredByCanon);
        $ueRows = [];
        foreach ($unlimExpiredByCanon as $canon => $subs) {
            $ueRows[] = [$canon, count($subs)];
        }
        $this->line('  Expired subscriptions by canonical package:');
        $this->table(['Zest Package (canonical)', '# Expired Subs'], $ueRows);

        // ── LIMITED PLANS ──────────────────────────────────────────────────────
        $this->section('LIMITED PLANS  (finite credits + weekly booking restriction)');
        $this->line('  Active  subscriptions : <fg=green>'.count($limitedActive).'</>');
        $this->line('  Expired subscriptions : '.count($limitedExpired));
        $this->line('  <fg=red;options=bold>GO-LIVE BLOCKER: 2x/week booking cap is NOT enforced in Zest BookingService.</>');
        $this->line('  <fg=yellow>Implement weekly-cap enforcement before running the live import.</>');
        $this->newLine();

        if (! empty($limitedActive)) {
            $lpRows = [];
            foreach ($limitedActive as $row) {
                $lpRows[] = [
                    $row['customer_name'] ?? '?',
                    $row['email'],
                    $row['package_name'],
                    (int) ($row['credits_left'] ?? 0),
                    $row['expiry_date'] ?? '',
                ];
            }
            $this->table(['Name', 'Email', 'VibeFam Package', 'Credits Left', 'Expires'], $lpRows);
        }

        // ── UNCLASSIFIED ───────────────────────────────────────────────────────
        if (! empty($unclassified)) {
            $this->section('UNCLASSIFIED PACKAGES — STOP (resolve before live import)');
            $ucByPkg = [];
            foreach ($unclassified as $row) {
                $ucByPkg[$row['package_name'] ?? '?'][] = $row;
            }
            foreach ($ucByPkg as $pkg => $rows) {
                $this->warn("  {$pkg} (".count($rows).' rows)');
                foreach ($rows as $row) {
                    $this->line("    - {$row['email']}, expires={$row['expiry_date']}, credits_left={$row['credits_left']}");
                }
            }
        }

        // ── PACKAGE MAPPING STATUS ─────────────────────────────────────────────
        $this->section('PACKAGE MAPPING STATUS  (required for live import)');
        $uniquePackages = $this->extractUniquePackages($memberships);
        $countByPkg = array_count_values(array_column($memberships, 'package_name'));
        $pkgRows = [];
        foreach ($uniquePackages as $pkg) {
            $meta = self::VIBEFAM_CANONICAL[$pkg] ?? ['canon' => '⚠ UNKNOWN', 'unlimited' => null, 'limited_plan' => false];
            $mapEntry = $packageMap[$pkg] ?? null;
            $pkgId = is_array($mapEntry) ? ($mapEntry['package_id'] ?? null) : $mapEntry;
            $mapped = $pkgId ? "<fg=green>ID={$pkgId}</>" : '<fg=yellow>⚠ UNMAPPED</>';
            $typeStr = match (true) {
                $meta['unlimited'] === true => 'unlimited',
                $meta['limited_plan'] => 'limited-plan',
                $meta['unlimited'] === false => 'finite',
                default => '⚠ unclassified',
            };
            $pkgRows[] = [$pkg, $countByPkg[$pkg] ?? 0, $meta['canon'], $typeStr, $mapped];
        }
        usort($pkgRows, fn ($a, $b) => $b[1] - $a[1]);
        $this->table(['VibeFam Package Name', '#Rows', 'Canonical Zest Name', 'Type', 'Pkg ID'], $pkgRows);

        // ── USERS ──────────────────────────────────────────────────────────────
        $this->section('USERS');
        $this->table([], [
            ['VibeFam members total (members-csv)',           count($membersList) ?: '(no members CSV)'],
            ['Members with ≥1 package purchase',             count($byEmail)],
            ['Members with zero packages (members-csv only)', count($zeroPackageMembers)],
            ['Total Zest users to create',                   count($byEmail) + count($zeroPackageMembers)],
            ['Users with active finite-credit subscription', count($usersWithFinite)],
            ['Users with active unlimited subscription',     count($usersWithUnlimited)],
            ['Users with BOTH finite + unlimited (active)',  count(array_intersect($usersWithFinite, $usersWithUnlimited))],
            ['Users with NO active entitlement (all expired)', count($usersNoEntitlement)],
        ]);

        $this->newLine();
        $this->line('  <fg=yellow>Duplicate identity cases requiring manual review (DO NOT auto-merge):</>');
        $this->table(['Person', 'Email 1', 'Email 2', 'Action required'], [
            ['Mok Kim Yua',   'kimyua@hotmail.com',    'kimyua@gmail.com',      'Confirm same person → merge before import'],
            ['Yoke Chin Ong', 'ongjjj-8313@yahoo.com', 'ongjjj_8313@yahoo.com', 'Confirm same person → merge (hyphen vs underscore)'],
        ]);

        // ── PER-MEMBER RECONCILIATION ──────────────────────────────────────────
        $this->section('PER-MEMBER RECONCILIATION');
        $reconRows = [];
        foreach ($byEmail as $email => $subs) {
            $name = $subs[0]['customer_name'] ?? '?';
            $activeSubs = array_filter($subs, fn ($s) => ! $this->isExpired($s['expiry_date'] ?? ''));
            $finiteLeft = 0;
            $hasUnlim = false;
            $warnFlags = [];

            foreach ($activeSubs as $sub) {
                $meta = $this->classifyRow($sub);
                if ($meta['unlimited'] === true) {
                    $hasUnlim = true;
                } elseif ($meta['unlimited'] === false) {
                    $finiteLeft += (int) ($sub['credits_left'] ?? 0);
                }
                if (str_starts_with($meta['canon'], '⚠')) {
                    $warnFlags[] = 'UNCLASSIFIED:'.$sub['package_name'];
                }
            }

            $entitlement = match (true) {
                $hasUnlim && $finiteLeft > 0 => 'UNLIM + FINITE',
                $hasUnlim => 'UNLIMITED',
                $finiteLeft > 0 => 'FINITE',
                default => 'none',
            };

            $reconRows[] = [
                Str::limit($name, 22),
                $email,
                count($subs),
                count($activeSubs),
                $finiteLeft > 0 ? $finiteLeft : '-',
                $entitlement,
                ! empty($warnFlags) ? implode('; ', $warnFlags) : 'OK',
            ];
        }
        usort($reconRows, fn ($a, $b) => strcmp($a[0], $b[0]));
        $this->table(['Name', 'Email', '#Subs', '#Active', 'Credits', 'Entitlement', 'Status'], $reconRows);

        // ── ZERO-PACKAGE MEMBERS ───────────────────────────────────────────────
        if (! empty($zeroPackageMembers)) {
            $this->section('ZERO-PACKAGE MEMBERS  (user record only)');
            $this->table(['Name', 'Email'], array_map(fn ($m) => [
                $m['customer_name'] ?? $m['name'] ?? '?',
                $m['email'] ?? '?',
            ], $zeroPackageMembers));
        } elseif (empty($membersList)) {
            $this->line('  (No --members-csv provided; zero-package members cannot be identified.)');
        }

        // ── DUPLICATES ─────────────────────────────────────────────────────────
        if (! empty($duplicates)) {
            $this->section('EXACT DUPLICATES (auto-skipped in live import)');
            $this->table(
                ['Row', 'Email', 'Package'],
                array_map(fn ($d) => [$d['row'], $d['email'], $d['package']], $duplicates)
            );
        }

        // ── WARNINGS ───────────────────────────────────────────────────────────
        if (! empty($this->warnings)) {
            $this->section('WARNINGS ('.count($this->warnings).')');
            foreach ($this->warnings as $w) {
                $this->warn("  ⚠  {$w}");
            }
        }

        // ── EXISTING ZEST PACKAGE BUG ──────────────────────────────────────────
        $this->section('BLOCKER: EXISTING ZEST PACKAGES HAVE WRONG is_unlimited FLAG');
        $this->warn('  Zest packages IDs 2–7 ("HYROX*", "Full Unlimited*") have is_unlimited=false with credits=999.');
        $this->warn('  They must be set to is_unlimited=true before the live import so bookings');
        $this->warn('  do NOT deduct credits from unlimited subscribers.');
        $this->warn('  Fix: update packages table (IDs 2–7) to set is_unlimited=true, credits=0.');
    }

    private function section(string $title): void
    {
        $this->newLine();
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->line(" {$title}");
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    }

    // ── Live import ──────────────────────────────────────────────────────────────

    private function runLiveImport(
        array $byEmail,
        array $zeroPackageMembers,
        array $packageMap,
        array $duplicates,
    ): int {
        $dupeKeys = array_column($duplicates, 'key');
        $bar = $this->output->createProgressBar(count($byEmail) + count($zeroPackageMembers));
        $bar->start();

        try {
            DB::transaction(function () use ($byEmail, $zeroPackageMembers, $packageMap, $dupeKeys, $bar) {
                foreach ($byEmail as $subs) {
                    $user = $this->upsertUser($subs[0]);
                    $runningBalance = 0;

                    foreach ($subs as $sub) {
                        $key = $this->sourceKey($sub);
                        if (in_array($key, $dupeKeys, true)) {
                            continue;
                        }
                        $mapEntry = $packageMap[$sub['package_name']] ?? null;
                        $pkgId = is_array($mapEntry) ? ($mapEntry['package_id'] ?? null) : $mapEntry;
                        $isUnlimited = $this->resolveIsUnlimited($sub, $mapEntry);

                        $subscription = $this->upsertSubscription($user, $sub, (int) $pkgId, $isUnlimited, $key);

                        if ($subscription && ! $isUnlimited) {
                            $credLeft = (int) ($sub['credits_left'] ?? 0);
                            if ($credLeft > 0) {
                                $runningBalance += $credLeft;
                                $this->upsertOpeningBalance($user, $subscription, $credLeft, $runningBalance, $key);
                            }
                        }
                    }
                    $user->syncCreditSummary();
                    $bar->advance();
                }

                foreach ($zeroPackageMembers as $member) {
                    $this->upsertUserFromMember($member);
                    $bar->advance();
                }
            });
        } catch (\Throwable $e) {
            $bar->finish();
            $this->newLine(2);
            $this->error('Import failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $bar->finish();
        $this->newLine(2);
        $this->info('Live import complete.');

        return self::SUCCESS;
    }

    private function resolveIsUnlimited(array $row, mixed $mapEntry): bool
    {
        // Explicit override in package map takes precedence
        if (is_array($mapEntry) && array_key_exists('is_unlimited', $mapEntry)) {
            return (bool) $mapEntry['is_unlimited'];
        }

        return $this->isRowUnlimited($row);
    }

    private function upsertUser(array $row): User
    {
        $email = strtolower(trim($row['email']));

        return User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $row['customer_name'] ?? $email,
                'phone' => null,
                'password' => Hash::make(Str::random(32)),
                'credits' => 0,
                'role' => 'member',
                'status' => 'active',
            ]
        );
    }

    private function upsertUserFromMember(array $member): ?User
    {
        $email = strtolower(trim($member['email'] ?? ''));
        if (! $email) {
            return null;
        }

        return User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $member['customer_name'] ?? $member['name'] ?? $email,
                'phone' => null,
                'password' => Hash::make(Str::random(32)),
                'credits' => 0,
                'role' => 'member',
                'status' => 'active',
            ]
        );
    }

    private function upsertSubscription(
        User $user,
        array $row,
        int $packageId,
        bool $isUnlimited,
        string $sourceKey,
    ): ?UserSubscription {
        if ($this->alreadyImported($sourceKey, 'user_subscription')) {
            return null;
        }

        $expiresAt = Carbon::parse($row['expiry_date']);

        $sub = UserSubscription::create([
            'user_id' => $user->id,
            'package_id' => $packageId,
            'credits_granted' => $isUnlimited ? 0 : (int) ($row['total_credits'] ?? 0),
            'credits_remaining' => $isUnlimited ? 0 : (int) ($row['credits_left'] ?? 0),
            'is_unlimited' => $isUnlimited,
            'started_at' => Carbon::parse($row['date_of_purchase']),
            'expires_at' => $expiresAt,
            'status' => $expiresAt->isFuture() ? 'active' : 'expired',
        ]);

        $this->recordImport($sourceKey, 'user_subscription', $sub->id);

        return $sub;
    }

    private function upsertOpeningBalance(
        User $user,
        UserSubscription $sub,
        int $creditsLeft,
        int $balanceAfter,
        string $subSourceKey,
    ): void {
        $txKey = hash('sha256', 'credit_tx|'.$subSourceKey);
        if ($this->alreadyImported($txKey, 'credit_transaction')) {
            return;
        }

        $tx = CreditTransaction::create([
            'user_id' => $user->id,
            'user_subscription_id' => $sub->id,
            'class_booking_id' => null,
            'type' => 'migration',
            'amount' => $creditsLeft,
            'balance_after' => $balanceAfter,
            'reason' => 'VibeFam migration opening balance',
            'actor_user_id' => null,
        ]);

        $this->recordImport($txKey, 'credit_transaction', $tx->id);
    }

    private function alreadyImported(string $key, string $modelType): bool
    {
        return DB::table('vibefam_import_map')
            ->where('source_system', 'vibefam')
            ->where('source_key', $key)
            ->where('model_type', $modelType)
            ->exists();
    }

    private function recordImport(string $key, string $modelType, int $modelId): void
    {
        DB::table('vibefam_import_map')->insert([
            'source_system' => 'vibefam',
            'source_key' => $key,
            'model_type' => $modelType,
            'model_id' => $modelId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
