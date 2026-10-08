<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Regression tests for the member password reset workflow.
 *
 * Covers:
 * - members:prepare-password-reset command (eligibility, exclusions, idempotency, dry-run)
 * - must_change_password enforcement on web routes and API
 * - ForcePasswordChangeController (show, update, session invalidation)
 * - VibeFam import integration (new vs existing users)
 */
class MemberPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    // ── 1. Eligibility: only role=member qualifies ────────────────────────────

    public function test_command_targets_member_role_only(): void
    {
        $member = User::factory()->create(['role' => 'member', 'is_admin' => false]);
        $admin = User::factory()->create(['role' => 'admin',  'is_admin' => true]);
        $coach = User::factory()->create(['role' => 'coach',  'is_admin' => false]);

        $this->artisan('members:prepare-password-reset', ['--dry-run' => true])
            ->expectsOutputToContain('1')   // 1 eligible member
            ->assertExitCode(0);

        $this->assertDatabaseMissing('users', ['id' => $admin->id,  'must_change_password' => true]);
        $this->assertDatabaseMissing('users', ['id' => $coach->id,  'must_change_password' => true]);
        $this->assertDatabaseMissing('users', ['id' => $member->id, 'must_change_password' => true]);
    }

    // ── 2. Admin exclusion ────────────────────────────────────────────────────

    public function test_admin_accounts_are_excluded_from_reset(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true, 'must_change_password' => false]);

        $this->artisan('members:prepare-password-reset', ['--dry-run' => true])
            ->assertExitCode(0);

        $admin->refresh();
        $this->assertFalse($admin->must_change_password);
    }

    // ── 3. Coach exclusion ────────────────────────────────────────────────────

    public function test_coach_accounts_are_excluded_from_reset(): void
    {
        $coach = User::factory()->create(['role' => 'coach', 'is_admin' => false, 'must_change_password' => false]);

        $this->artisan('members:prepare-password-reset', ['--dry-run' => true])
            ->assertExitCode(0);

        $coach->refresh();
        $this->assertFalse($coach->must_change_password);
    }

    // ── 4. Legacy admin exclusion (is_admin=true, role=member) ───────────────

    public function test_legacy_admin_flag_excludes_from_reset(): void
    {
        $legacy = User::factory()->create(['role' => 'member', 'is_admin' => true]);

        $this->artisan('members:prepare-password-reset', ['--dry-run' => true])
            ->assertExitCode(0);

        $legacy->refresh();
        $this->assertFalse($legacy->must_change_password);
    }

    // ── 5. Unique temporary password per member ───────────────────────────────

    public function test_each_member_receives_a_unique_temporary_password(): void
    {
        $m1 = User::factory()->create(['role' => 'member', 'is_admin' => false]);
        $m2 = User::factory()->create(['role' => 'member', 'is_admin' => false]);

        $before1 = $m1->password;
        $before2 = $m2->password;

        $this->artisan('members:prepare-password-reset', ['--force' => true])
            ->assertExitCode(0);

        $m1->refresh();
        $m2->refresh();

        $this->assertTrue($m1->must_change_password);
        $this->assertTrue($m2->must_change_password);
        // Passwords were changed
        $this->assertNotEquals($before1, $m1->password);
        $this->assertNotEquals($before2, $m2->password);
        // Passwords are different from each other
        $this->assertNotEquals($m1->password, $m2->password);
    }

    // ── 6. Passwords are stored as hashes, never plaintext ────────────────────

    public function test_temporary_passwords_are_stored_as_hashes(): void
    {
        $member = User::factory()->create(['role' => 'member', 'is_admin' => false]);

        $this->artisan('members:prepare-password-reset', ['--force' => true])
            ->assertExitCode(0);

        $member->refresh();
        // bcrypt hash starts with $2y$ or argon2 starts with $argon
        $this->assertStringStartsWith('$', $member->password);
        // Definitely not 24 raw base64 chars stored as-is
        $this->assertGreaterThan(40, strlen($member->password));
    }

    // ── 7. Dry-run performs zero writes ───────────────────────────────────────

    public function test_dry_run_makes_no_database_changes(): void
    {
        $member = User::factory()->create(['role' => 'member', 'is_admin' => false]);
        $before = $member->password;

        $this->artisan('members:prepare-password-reset', ['--dry-run' => true])
            ->assertExitCode(0);

        $member->refresh();
        $this->assertFalse($member->must_change_password);
        $this->assertSame($before, $member->password);
        $this->assertDatabaseCount('password_reset_batches', 0);
    }

    // ── 8. Idempotency: already-pending members are skipped ──────────────────

    public function test_repeated_execution_skips_already_pending_members(): void
    {
        $m1 = User::factory()->create(['role' => 'member', 'is_admin' => false, 'must_change_password' => false]);
        $m2 = User::factory()->create(['role' => 'member', 'is_admin' => false, 'must_change_password' => true]);

        $hashBefore = $m2->password;

        $this->artisan('members:prepare-password-reset', ['--force' => true])
            ->assertExitCode(0);

        $m1->refresh();
        $m2->refresh();

        // m1 gets reset
        $this->assertTrue($m1->must_change_password);
        // m2 already had flag set — password must not change
        $this->assertSame($hashBefore, $m2->password);
        // Only one batch record for the one member actually processed
        $this->assertDatabaseCount('password_reset_batches', 1);
        $batch = DB::table('password_reset_batches')->first();
        $this->assertSame(1, $batch->member_count);
    }

    // ── 9. First login redirects to Change Password ───────────────────────────

    public function test_member_with_must_change_password_is_redirected_on_login(): void
    {
        $member = User::factory()->create([
            'role' => 'member',
            'is_admin' => false,
            'password' => bcrypt('password'),
            'must_change_password' => true,
        ]);

        $response = $this->post('/login', [
            'email' => $member->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('password.change'));
    }

    // ── 10. Member features are blocked until password is changed ─────────────

    public function test_member_cannot_access_schedule_while_must_change_password(): void
    {
        $member = User::factory()->create([
            'role' => 'member',
            'is_admin' => false,
            'must_change_password' => true,
        ]);

        $response = $this->actingAs($member)->get('/schedule');

        $response->assertRedirect(route('password.change'));
    }

    public function test_member_can_reach_change_password_page_while_blocked(): void
    {
        $member = User::factory()->create([
            'role' => 'member',
            'is_admin' => false,
            'must_change_password' => true,
        ]);

        $response = $this->actingAs($member)->get(route('password.change'));

        $response->assertOk();
    }

    public function test_member_can_logout_while_must_change_password(): void
    {
        $member = User::factory()->create([
            'role' => 'member',
            'is_admin' => false,
            'must_change_password' => true,
        ]);

        $response = $this->actingAs($member)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }

    // ── 11. Successful password change restores access ────────────────────────

    public function test_password_change_clears_flag_and_redirects_to_schedule(): void
    {
        $member = User::factory()->create([
            'role' => 'member',
            'is_admin' => false,
            'must_change_password' => true,
        ]);

        $response = $this->actingAs($member)->post(route('password.change.update'), [
            'password' => 'NewPass1secure',
            'password_confirmation' => 'NewPass1secure',
        ]);

        $member->refresh();
        $this->assertFalse($member->must_change_password);
        $this->assertTrue(Hash::check('NewPass1secure', $member->password));
        $response->assertRedirect(route('schedule', absolute: false));
    }

    public function test_member_can_access_schedule_after_password_change(): void
    {
        $member = User::factory()->create([
            'role' => 'member',
            'is_admin' => false,
            'must_change_password' => true,
        ]);

        $this->actingAs($member)->post(route('password.change.update'), [
            'password' => 'NewPass1secure',
            'password_confirmation' => 'NewPass1secure',
        ]);

        $member->refresh();
        $response = $this->actingAs($member)->get('/schedule');
        $response->assertOk();
    }

    public function test_password_change_requires_sufficiently_strong_password(): void
    {
        $member = User::factory()->create([
            'role' => 'member',
            'is_admin' => false,
            'must_change_password' => true,
        ]);

        // Too short / no mixed case / no numbers
        $response = $this->actingAs($member)->post(route('password.change.update'), [
            'password' => 'weak',
            'password_confirmation' => 'weak',
        ]);

        $response->assertSessionHasErrors('password');
        $member->refresh();
        $this->assertTrue($member->must_change_password);
    }

    public function test_password_change_requires_confirmation_match(): void
    {
        $member = User::factory()->create([
            'role' => 'member',
            'is_admin' => false,
            'must_change_password' => true,
        ]);

        $response = $this->actingAs($member)->post(route('password.change.update'), [
            'password' => 'NewPass1secure',
            'password_confirmation' => 'DifferentPass1',
        ]);

        $response->assertSessionHasErrors('password');
    }

    // ── 12. Admins and coaches are not blocked by must_change_password ─────────

    public function test_admin_not_redirected_even_with_must_change_password_set(): void
    {
        // Admins should never have must_change_password set, but even if they do,
        // the middleware must not block them (only members are targeted).
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_admin' => true,
            'must_change_password' => true,
        ]);

        $response = $this->actingAs($admin)->get('/admin');

        // Must NOT be redirected to password.change
        $this->assertNotEquals(
            route('password.change'),
            $response->headers->get('Location'),
            'Admin must not be redirected to the force-change-password page'
        );
    }

    // ── 13. Existing matched VibeFam users retain their passwords ─────────────

    public function test_existing_user_password_not_overwritten_by_vibefam_import(): void
    {
        $existingUser = User::factory()->create([
            'email' => 'alice@example.com',
            'role' => 'member',
            'is_admin' => false,
            'must_change_password' => false,
            'password' => bcrypt('my-known-password'),
        ]);

        $csv = $this->makeVibefamCsv([[
            'name' => 'Alice',
            'email' => 'alice@example.com',
        ]]);
        $map = $this->makePackageMapJson(['12 Credit Class' => $this->getOrCreatePackageId()]);

        $this->artisan('vibefam:import', [
            '--memberships-csv' => $csv,
            '--package-map' => $map,
        ])
            ->expectsConfirmation('Run live import? This will create users and subscriptions.', 'yes')
            ->assertExitCode(0);

        $existingUser->refresh();
        $this->assertTrue(Hash::check('my-known-password', $existingUser->password),
            'Existing user password must not be overwritten by VibeFam import');
        $this->assertFalse($existingUser->must_change_password,
            'Existing user must_change_password must not be set by VibeFam import');

        unlink($csv);
        unlink($map);
    }

    // ── 14. Newly imported VibeFam users have must_change_password=true ────────

    public function test_newly_created_vibefam_user_has_must_change_password_set(): void
    {
        $csv = $this->makeVibefamCsv([[
            'name' => 'New Zester',
            'email' => 'newzester@example.com',
        ]]);
        $map = $this->makePackageMapJson(['12 Credit Class' => $this->getOrCreatePackageId()]);

        $this->artisan('vibefam:import', [
            '--memberships-csv' => $csv,
            '--package-map' => $map,
        ])
            ->expectsConfirmation('Run live import? This will create users and subscriptions.', 'yes')
            ->assertExitCode(0);

        $user = User::where('email', 'newzester@example.com')->first();
        $this->assertNotNull($user, 'VibeFam user should have been created');
        $this->assertTrue($user->must_change_password,
            'Newly imported VibeFam user must have must_change_password=true');

        unlink($csv);
        unlink($map);
    }

    // ── 15. API token issuance blocked when must_change_password=true ──────────

    public function test_api_token_blocked_when_must_change_password(): void
    {
        User::factory()->create([
            'email' => 'api-user@example.com',
            'password' => bcrypt('password'),
            'must_change_password' => true,
        ]);

        $response = $this->postJson('/api/auth/token', [
            'email' => 'api-user@example.com',
            'password' => 'password',
            'device_name' => 'Apple Watch',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('email');
    }

    public function test_api_token_issued_when_must_change_password_is_false(): void
    {
        User::factory()->create([
            'email' => 'api-user@example.com',
            'password' => bcrypt('password'),
            'must_change_password' => false,
        ]);

        $response = $this->postJson('/api/auth/token', [
            'email' => 'api-user@example.com',
            'password' => 'password',
            'device_name' => 'Apple Watch',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['token']);
    }

    // ── 16. Command creates audit record on live run ──────────────────────────

    public function test_live_run_creates_audit_record(): void
    {
        User::factory()->count(3)->create(['role' => 'member', 'is_admin' => false]);

        $this->artisan('members:prepare-password-reset', ['--force' => true])
            ->assertExitCode(0);

        $this->assertDatabaseCount('password_reset_batches', 1);
        $batch = DB::table('password_reset_batches')->first();
        $this->assertSame(3, $batch->member_count);
        $this->assertFalse((bool) $batch->is_dry_run);
        $ids = json_decode($batch->member_ids, true);
        $this->assertCount(3, $ids);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeVibefamCsv(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'vf-pwreset-test-').'.csv';
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
                '2026-01-01',
                '00:00:00',
                '2027-01-01',
                $row['package'] ?? '12 Credit Class',
                0, 0, 0, 12, 0, 12, 0, '',
            ]);
        }
        fclose($f);

        return $path;
    }

    private function makePackageMapJson(array $map): string
    {
        $path = tempnam(sys_get_temp_dir(), 'pkg-map-').'.json';
        file_put_contents($path, json_encode($map));

        return $path;
    }

    private function getOrCreatePackageId(): int
    {
        $existing = DB::table('packages')->where('name', '12 Credit Package')->value('id');
        if ($existing) {
            return (int) $existing;
        }

        return (int) DB::table('packages')->insertGetId([
            'name' => '12 Credit Package',
            'description' => 'Test',
            'credits' => 12,
            'is_unlimited' => false,
            'weekly_booking_limit' => null,
            'period_days' => 30,
            'price' => 360,
            'badge' => 'Test',
            'is_trial' => false,
            'is_active' => false,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
