<?php

namespace Tests\Feature;

use App\Jobs\SendAccountActivationEmail;
use App\Mail\AccountSetupMail;
use App\Models\AccountActivationRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AccountActivationTest extends TestCase
{
    use RefreshDatabase;

    // ─── helpers ──────────────────────────────────────────────────────────────

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_admin' => true]);
    }

    private function member(): User
    {
        return User::factory()->create(['role' => 'member', 'is_admin' => false]);
    }

    // ─── eligibility ──────────────────────────────────────────────────────────

    public function test_index_only_counts_member_role(): void
    {
        $admin = $this->admin();
        User::factory()->create(['role' => 'coach', 'is_admin' => false]);
        User::factory()->create(['role' => 'member', 'is_admin' => false]);

        $this->actingAs($admin)
            ->get(route('admin.activation.index'))
            ->assertInertia(fn ($page) => $page
                ->component('Admin/AccountActivation')
                ->where('stats.total_eligible', 1)
            );
    }

    public function test_legacy_admin_flag_excluded_from_eligible(): void
    {
        $admin = $this->admin();
        User::factory()->create(['role' => 'member', 'is_admin' => true]);
        User::factory()->create(['role' => 'member', 'is_admin' => false]);

        $this->actingAs($admin)
            ->get(route('admin.activation.index'))
            ->assertInertia(fn ($page) => $page
                ->where('stats.total_eligible', 1)
            );
    }

    public function test_non_admin_cannot_access_activation(): void
    {
        $member = $this->member();

        $this->actingAs($member)
            ->get(route('admin.activation.index'))
            ->assertForbidden();
    }

    public function test_guest_cannot_access_activation(): void
    {
        $this->get(route('admin.activation.index'))
            ->assertRedirect(route('login'));
    }

    // ─── stats ────────────────────────────────────────────────────────────────

    public function test_stats_reflect_activation_statuses(): void
    {
        $admin = $this->admin();

        $sent = $this->member();
        AccountActivationRequest::create(['user_id' => $sent->id, 'status' => 'sent', 'sent_at' => now()]);

        $activated = $this->member();
        AccountActivationRequest::create(['user_id' => $activated->id, 'status' => 'activated', 'activated_at' => now()]);

        $failed = $this->member();
        AccountActivationRequest::create(['user_id' => $failed->id, 'status' => 'failed', 'error_message' => 'SMTP timeout']);

        $pending = $this->member();

        $this->actingAs($admin)
            ->get(route('admin.activation.index'))
            ->assertInertia(fn ($page) => $page
                ->where('stats.total_eligible', 4)
                ->where('stats.sent', 1)
                ->where('stats.activated', 1)
                ->where('stats.failed', 1)
                ->where('stats.pending', 1)
            );
    }

    // ─── batch send ──────────────────────────────────────────────────────────

    public function test_batch_send_dispatches_job_for_each_pending_member(): void
    {
        Queue::fake();

        $admin = $this->admin();
        $m1 = $this->member();
        $m2 = $this->member();
        $activated = $this->member();
        AccountActivationRequest::create(['user_id' => $activated->id, 'status' => 'activated']);

        $this->actingAs($admin)
            ->post(route('admin.activation.batch'))
            ->assertRedirect();

        Queue::assertPushed(SendAccountActivationEmail::class, 2);
        Queue::assertPushed(SendAccountActivationEmail::class, fn ($job) => $job->user->id === $m1->id);
        Queue::assertPushed(SendAccountActivationEmail::class, fn ($job) => $job->user->id === $m2->id);
        Queue::assertNotPushed(SendAccountActivationEmail::class, fn ($job) => $job->user->id === $activated->id);
    }

    public function test_batch_send_creates_pending_records(): void
    {
        Queue::fake();

        $admin = $this->admin();
        $member = $this->member();

        $this->actingAs($admin)->post(route('admin.activation.batch'));

        $this->assertDatabaseHas('account_activation_requests', [
            'user_id' => $member->id,
            'status' => 'pending',
        ]);
    }

    public function test_batch_send_skips_already_activated(): void
    {
        Queue::fake();

        $admin = $this->admin();
        $activated = $this->member();
        AccountActivationRequest::create(['user_id' => $activated->id, 'status' => 'activated']);

        $this->actingAs($admin)
            ->post(route('admin.activation.batch'))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_batch_send_includes_failed_for_retry(): void
    {
        Queue::fake();

        $admin = $this->admin();
        $failed = $this->member();
        AccountActivationRequest::create(['user_id' => $failed->id, 'status' => 'failed']);

        $this->actingAs($admin)->post(route('admin.activation.batch'));

        Queue::assertPushed(SendAccountActivationEmail::class, fn ($job) => $job->user->id === $failed->id);
    }

    // ─── retry ────────────────────────────────────────────────────────────────

    public function test_retry_only_targets_failed_records(): void
    {
        Queue::fake();

        $admin = $this->admin();
        $failed = $this->member();
        AccountActivationRequest::create(['user_id' => $failed->id, 'status' => 'failed']);

        $pending = $this->member();

        $this->actingAs($admin)->post(route('admin.activation.retry'));

        Queue::assertPushed(SendAccountActivationEmail::class, 1);
        Queue::assertPushed(SendAccountActivationEmail::class, fn ($job) => $job->user->id === $failed->id);
        Queue::assertNotPushed(SendAccountActivationEmail::class, fn ($job) => $job->user->id === $pending->id);
    }

    public function test_retry_returns_error_when_nothing_to_retry(): void
    {
        Queue::fake();

        $admin = $this->admin();
        $this->member();

        $this->actingAs($admin)
            ->post(route('admin.activation.retry'))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    // ─── job: token, mail, status ─────────────────────────────────────────────

    public function test_job_sends_mail_and_marks_sent(): void
    {
        Mail::fake();

        $member = $this->member();

        (new SendAccountActivationEmail($member))->handle();

        Mail::assertSent(AccountSetupMail::class, fn ($mail) => $mail->hasTo($member->email));

        $this->assertDatabaseHas('account_activation_requests', [
            'user_id' => $member->id,
            'status' => 'sent',
        ]);
    }

    public function test_job_token_is_stored_hashed_not_plaintext(): void
    {
        Mail::fake();

        $member = $this->member();

        (new SendAccountActivationEmail($member))->handle();

        // Job now uses account_setup broker — token is in account_setup_tokens, not password_reset_tokens
        $row = \DB::table('account_setup_tokens')->where('email', $member->email)->first();
        $this->assertNotNull($row, 'Token row should exist in account_setup_tokens');
        // Laravel stores password reset tokens as bcrypt hashes (starts with $2y$)
        $this->assertStringStartsWith('$2', $row->token, 'Token should be stored as a bcrypt hash, not plaintext');
    }

    public function test_job_skips_already_activated_member(): void
    {
        Mail::fake();

        $member = $this->member();
        AccountActivationRequest::create(['user_id' => $member->id, 'status' => 'activated']);

        (new SendAccountActivationEmail($member))->handle();

        Mail::assertNothingSent();
    }

    public function test_job_marks_failed_on_exception(): void
    {
        $member = $this->member();

        $job = new SendAccountActivationEmail($member);
        $job->failed(new \RuntimeException('SMTP connection refused'));

        $this->assertDatabaseHas('account_activation_requests', [
            'user_id' => $member->id,
            'status' => 'failed',
        ]);

        $record = AccountActivationRequest::where('user_id', $member->id)->first();
        $this->assertStringContainsString('SMTP connection refused', $record->error_message);
    }

    public function test_job_has_unique_id_per_user(): void
    {
        $member = $this->member();
        $job1 = new SendAccountActivationEmail($member);
        $job2 = new SendAccountActivationEmail($member);

        $this->assertSame($job1->uniqueId(), $job2->uniqueId());
        $this->assertEquals((string) $member->id, $job1->uniqueId());
    }

    // ─── token: single-use, 24h expiry ───────────────────────────────────────

    public function test_activation_token_is_consumed_on_completion(): void
    {
        Mail::fake();

        $member = $this->member();
        $token = Password::broker('account_setup')->createToken($member);

        $this->post(route('activation.complete.store'), [
            'token' => $token,
            'email' => $member->email,
            'password' => 'NewPass123!',
            'password_confirmation' => 'NewPass123!',
        ])->assertRedirect(route('schedule'));

        $this->assertFalse(
            Password::broker('account_setup')->tokenExists($member->fresh(), $token),
            'Activation token should be consumed after use'
        );
    }

    public function test_account_setup_token_expiry_config_is_24_hours(): void
    {
        $this->assertEquals(1440, config('auth.passwords.account_setup.expire'));
    }

    // ─── password reset integration ──────────────────────────────────────────

    public function test_password_reset_clears_must_change_password(): void
    {
        $member = $this->member();
        $member->update(['must_change_password' => true]);

        $token = Password::broker()->createToken($member);

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => $member->email,
            'password' => 'NewPass123!',
            'password_confirmation' => 'NewPass123!',
        ]);

        $this->assertFalse($member->fresh()->must_change_password);
    }

    public function test_activation_complete_marks_activation_as_activated(): void
    {
        $member = $this->member();
        AccountActivationRequest::create(['user_id' => $member->id, 'status' => 'sent']);

        $token = Password::broker('account_setup')->createToken($member);

        $this->post(route('activation.complete.store'), [
            'token' => $token,
            'email' => $member->email,
            'password' => 'NewPass123!',
            'password_confirmation' => 'NewPass123!',
        ])->assertRedirect(route('schedule'));

        $this->assertDatabaseHas('account_activation_requests', [
            'user_id' => $member->id,
            'status' => 'activated',
        ]);

        $record = AccountActivationRequest::where('user_id', $member->id)->first();
        $this->assertNotNull($record->activated_at);
    }

    public function test_normal_password_reset_also_marks_activation_as_activated(): void
    {
        $member = $this->member();
        AccountActivationRequest::create(['user_id' => $member->id, 'status' => 'sent']);

        $token = Password::broker()->createToken($member);

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => $member->email,
            'password' => 'NewPass123!',
            'password_confirmation' => 'NewPass123!',
        ]);

        $this->assertDatabaseHas('account_activation_requests', [
            'user_id' => $member->id,
            'status' => 'activated',
        ]);
    }

    // ─── test email ──────────────────────────────────────────────────────────

    public function test_test_email_requires_admin_or_owner_address(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.activation.test'), ['email' => 'stranger@example.com'])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_test_email_allowed_to_admin_account(): void
    {
        Mail::fake();

        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.activation.test'), ['email' => $admin->email])
            ->assertRedirect()
            ->assertSessionHas('success');

        Mail::assertSent(AccountSetupMail::class, fn ($mail) => $mail->hasTo($admin->email));
    }

    // ─── regression: hotfix ───────────────────────────────────────────────────

    public function test_test_email_does_not_create_token_in_any_table(): void
    {
        Mail::fake();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.activation.test'), ['email' => $admin->email]);

        $this->assertDatabaseCount('password_reset_tokens', 0);
        $this->assertDatabaseCount('account_setup_tokens', 0);
    }

    public function test_users_broker_expiry_is_60_minutes(): void
    {
        $this->assertEquals(60, config('auth.passwords.users.expire'));
    }

    public function test_account_setup_broker_is_isolated_from_users_broker(): void
    {
        $member = $this->member();

        // Token created via account_setup broker lands in account_setup_tokens, not password_reset_tokens
        Password::broker('account_setup')->createToken($member);

        $this->assertDatabaseCount('account_setup_tokens', 1);
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_activation_complete_sets_password_and_logs_in(): void
    {
        $member = $this->member();
        AccountActivationRequest::create(['user_id' => $member->id, 'status' => 'sent']);

        $token = Password::broker('account_setup')->createToken($member);

        $this->post(route('activation.complete.store'), [
            'token' => $token,
            'email' => $member->email,
            'password' => 'NewPass123!',
            'password_confirmation' => 'NewPass123!',
        ])->assertRedirect(route('schedule'));

        $this->assertFalse($member->fresh()->must_change_password);
        $this->assertAuthenticatedAs($member->fresh());
    }

    public function test_activation_complete_token_is_single_use(): void
    {
        $member = $this->member();
        $token = Password::broker('account_setup')->createToken($member);

        $this->post(route('activation.complete.store'), [
            'token' => $token,
            'email' => $member->email,
            'password' => 'NewPass123!',
            'password_confirmation' => 'NewPass123!',
        ]);

        // Token must be consumed — no longer in account_setup_tokens
        $this->assertDatabaseCount('account_setup_tokens', 0);
        $this->assertFalse(Password::broker('account_setup')->tokenExists($member->fresh(), $token));
    }

    public function test_batch_send_capped_at_max_batch_size(): void
    {
        Queue::fake();
        $admin = $this->admin();

        User::factory()->count(505)->create(['role' => 'member', 'is_admin' => false]);

        $this->actingAs($admin)->post(route('admin.activation.batch'));

        Queue::assertPushed(SendAccountActivationEmail::class, 500);
    }
}
