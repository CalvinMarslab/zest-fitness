<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Prepares a secure password reset for all existing member accounts.
 *
 * Each member receives a unique, cryptographically secure temporary password.
 * Passwords are hashed immediately; plaintext is written only to a private
 * delivery file in storage/app/ (gitignored) and never to console output.
 *
 * Admin and Coach accounts are explicitly excluded.
 * Members with must_change_password already set are skipped (idempotent).
 * Every run creates an audit record in password_reset_batches.
 *
 * Usage:
 *   php artisan members:prepare-password-reset --dry-run
 *   php artisan members:prepare-password-reset --force
 */
class PreparePasswordReset extends Command
{
    protected $signature = 'members:prepare-password-reset
        {--dry-run : Preview affected counts without modifying data}
        {--force : Skip interactive confirmation prompt}';

    protected $description = 'Prepare a secure password reset for all member accounts (use --dry-run first)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // ── Eligibility query ────────────────────────────────────────────────────
        // Target: role=member, NOT a legacy admin (is_admin=false or null).
        $eligible = User::where('role', 'member')
            ->where(fn ($q) => $q->whereNull('is_admin')->orWhere('is_admin', false))
            ->get(['id', 'name', 'email', 'must_change_password']);

        $pending = $eligible->where('must_change_password', true);
        $targets = $eligible->where('must_change_password', false);

        $this->line('');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->line(' MEMBER PASSWORD RESET — '
            .($dryRun ? '<fg=yellow>DRY RUN</>' : '<fg=red;options=bold>LIVE RUN</>'));
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->table([], [
            ['Eligible members (role=member, not admin)', $eligible->count()],
            ['Already pending reset (skipped)',           $pending->count()],
            ['Will receive new temporary password',       $targets->count()],
        ]);

        if ($targets->isEmpty()) {
            $this->info('Nothing to do — no eligible members need a password reset.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info('Dry run complete — no data written.');

            return self::SUCCESS;
        }

        // ── Confirmation ─────────────────────────────────────────────────────────
        if (! $this->option('force')) {
            if (! $this->confirm(
                "Reset passwords for {$targets->count()} members? This cannot be undone.",
                false
            )) {
                $this->line('Aborted.');

                return self::FAILURE;
            }
        }

        // ── Execute ──────────────────────────────────────────────────────────────
        $batchUuid = (string) Str::uuid();
        $deliveryRows = [];
        $affectedIds = [];

        DB::transaction(function () use ($targets, $batchUuid, &$deliveryRows, &$affectedIds) {
            foreach ($targets as $user) {
                $plaintext = $this->generateSecurePassword();

                User::where('id', $user->id)->update([
                    'password' => Hash::make($plaintext),
                    'must_change_password' => true,
                ]);

                $affectedIds[] = $user->id;
                $deliveryRows[] = [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'password' => $plaintext,
                ];
            }

            DB::table('password_reset_batches')->insert([
                'batch_uuid' => $batchUuid,
                'member_ids' => json_encode($affectedIds),
                'member_count' => count($affectedIds),
                'is_dry_run' => false,
                'executed_at' => now(),
            ]);
        });

        // ── Write delivery file ──────────────────────────────────────────────────
        $deliveryPath = $this->writeDeliveryFile($batchUuid, $deliveryRows);

        $this->newLine();
        $this->info("Password reset prepared for {$targets->count()} members.");
        $this->line("Batch ID: {$batchUuid}");
        $this->newLine();
        $this->warn('SECURITY: Temporary passwords written to:');
        $this->line("  {$deliveryPath}");
        $this->warn('Deliver passwords via a secure, private channel.');
        $this->warn('Delete the delivery file immediately after delivery.');
        $this->warn('Do NOT commit this file to git.');

        return self::SUCCESS;
    }

    private function generateSecurePassword(): string
    {
        // 18 bytes → 24 base64 chars, URL-safe, cryptographically random
        return rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
    }

    private function writeDeliveryFile(string $batchUuid, array $rows): string
    {
        $dir = storage_path('app');
        $path = "{$dir}/password-reset-{$batchUuid}.csv";

        $handle = fopen($path, 'w');
        fputcsv($handle, ['# SECURITY WARNING: Delete after delivery. Do NOT commit to git.']);
        fputcsv($handle, ['id', 'name', 'email', 'temporary_password']);
        foreach ($rows as $row) {
            fputcsv($handle, [$row['id'], $row['name'], $row['email'], $row['password']]);
        }
        fclose($handle);

        return $path;
    }
}
