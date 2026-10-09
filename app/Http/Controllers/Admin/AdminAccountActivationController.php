<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendAccountActivationEmail;
use App\Mail\AccountSetupMail;
use App\Models\AccountActivationRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Account Activation — staged rollout safety guide
 *
 * PHASE 1 — Send activation emails (this UI):
 *   Use "Send Batch" to email pending members. Each email contains a
 *   single-use 24-hour link that lets the member set their own password.
 *   Members keep their current credentials until they click the link;
 *   sending the email changes nothing.
 *
 * PHASE 2 — Enforce on next login (optional, after a safe window):
 *   After ~14 days, any member who has NOT activated can be prompted to
 *   set a new password on their next login by setting must_change_password
 *   = true via the admin User panel. This uses the existing
 *   ForcePasswordChangeController flow and requires no email token.
 *
 * CRITICAL: Only apply Phase 2 to members with activation status ≠ "activated".
 *   Never set must_change_password = true on already-activated members.
 */
class AdminAccountActivationController extends Controller
{
    const MAX_BATCH_SIZE = 500;

    private const DISPATCH_LOCK = 'activation-batch-dispatch';

    private function eligibleQuery()
    {
        return User::where('role', 'member')
            ->where(fn ($q) => $q->whereNull('is_admin')->orWhere('is_admin', false));
    }

    private function queueIsSynchronous(): bool
    {
        return config('queue.default') === 'sync';
    }

    public function index(): Response
    {
        $eligible = $this->eligibleQuery();

        $stats = [
            'total_eligible' => (clone $eligible)->count(),
            'pending' => (clone $eligible)
                ->where(fn ($q) => $q
                    ->whereDoesntHave('activationRequest')
                    ->orWhereHas('activationRequest', fn ($q2) => $q2->where('status', 'pending'))
                )
                ->count(),
            'sent' => (clone $eligible)
                ->whereHas('activationRequest', fn ($q) => $q->where('status', 'sent'))
                ->count(),
            'failed' => (clone $eligible)
                ->whereHas('activationRequest', fn ($q) => $q->where('status', 'failed'))
                ->count(),
            'activated' => (clone $eligible)
                ->whereHas('activationRequest', fn ($q) => $q->where('status', 'activated'))
                ->count(),
        ];

        $members = (clone $eligible)
            ->with('activationRequest')
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'status' => $u->activationRequest?->status ?? 'pending',
                'sent_at' => $u->activationRequest?->sent_at?->toDateTimeString(),
                'activated_at' => $u->activationRequest?->activated_at?->toDateTimeString(),
                'error_message' => $u->activationRequest?->error_message,
            ]);

        return Inertia::render('Admin/AccountActivation', [
            'stats' => $stats,
            'members' => $members,
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
    }

    public function sendTest(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $approvedEmails = array_filter([
            config('mail.from.address'),
            config('app.owner_email'),
        ]);

        $isAdminEmail = User::where('email', $data['email'])
            ->where(fn ($q) => $q->where('role', 'admin')->orWhere('is_admin', true))
            ->exists();

        if (! $isAdminEmail && ! in_array($data['email'], $approvedEmails)) {
            return back()->with('error', 'Test emails may only be sent to admin accounts or the app owner address.');
        }

        $key = 'activation-test:'.auth()->id();
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);

            return back()->with('error', "Too many test sends. Try again in {$seconds}s.");
        }

        RateLimiter::hit($key, 300);

        // Build an inert URL — the token is not stored in any table and will never validate.
        // This previews the email template without creating any real credential.
        $inertToken = hash('sha256', Str::random(40).'preview-inert');
        $previewUrl = route('activation.complete', [
            'token' => $inertToken,
            'email' => $data['email'],
        ]);

        $previewUser = User::make(['name' => 'Test Member', 'email' => $data['email']]);

        Mail::to($data['email'])->send(new AccountSetupMail($previewUser, null, $previewUrl));

        return back()->with('success', "Test email sent to {$data['email']}.");
    }

    public function sendBatch(Request $request): RedirectResponse
    {
        $request->validate([
            'confirmed' => ['required', 'accepted'],
        ]);

        if ($this->queueIsSynchronous()) {
            return back()->with('error', 'Queue driver is "sync" — batch activation would send all emails synchronously during this request. Configure an async queue driver (database, redis) first.');
        }

        $rateKey = 'activation-batch:'.auth()->id();
        if (RateLimiter::tooManyAttempts($rateKey, 1)) {
            $seconds = RateLimiter::availableIn($rateKey);

            return back()->with('error', "Batch already queued. Try again in {$seconds}s.");
        }

        $lock = Cache::lock(self::DISPATCH_LOCK, 30);
        if (! $lock->get()) {
            return back()->with('error', 'A batch dispatch is already in progress. Try again in a moment.');
        }

        try {
            $batchId = Str::uuid()->toString();

            $members = $this->eligibleQuery()
                ->where(fn ($q) => $q
                    ->whereDoesntHave('activationRequest')
                    ->orWhereHas('activationRequest', fn ($q2) => $q2->whereIn('status', ['pending', 'failed']))
                )
                ->limit(self::MAX_BATCH_SIZE)
                ->get(['id']);

            if ($members->isEmpty()) {
                return back()->with('error', 'No eligible members to send to.');
            }

            foreach ($members as $user) {
                AccountActivationRequest::updateOrCreate(
                    ['user_id' => $user->id],
                    ['status' => 'pending', 'batch_id' => $batchId]
                );

                SendAccountActivationEmail::dispatch($user, $batchId);
            }

            RateLimiter::hit($rateKey, 600);

            return back()->with('success', "Queued activation emails for {$members->count()} member(s). Batch: {$batchId}");
        } finally {
            $lock->release();
        }
    }

    public function retryFailed(Request $request): RedirectResponse
    {
        if ($this->queueIsSynchronous()) {
            return back()->with('error', 'Queue driver is "sync" — batch retry would send all emails synchronously during this request. Configure an async queue driver (database, redis) first.');
        }

        $rateKey = 'activation-retry:'.auth()->id();
        if (RateLimiter::tooManyAttempts($rateKey, 3)) {
            $seconds = RateLimiter::availableIn($rateKey);

            return back()->with('error', "Too many retry attempts. Try again in {$seconds}s.");
        }

        $lock = Cache::lock(self::DISPATCH_LOCK, 30);
        if (! $lock->get()) {
            return back()->with('error', 'A batch dispatch is already in progress. Try again in a moment.');
        }

        try {
            $batchId = Str::uuid()->toString();

            $failed = $this->eligibleQuery()
                ->whereHas('activationRequest', fn ($q) => $q->where('status', 'failed'))
                ->limit(self::MAX_BATCH_SIZE)
                ->get(['id']);

            if ($failed->isEmpty()) {
                return back()->with('error', 'No failed activations to retry.');
            }

            foreach ($failed as $user) {
                AccountActivationRequest::where('user_id', $user->id)
                    ->update(['status' => 'pending', 'batch_id' => $batchId]);

                SendAccountActivationEmail::dispatch($user, $batchId);
            }

            RateLimiter::hit($rateKey, 300);

            return back()->with('success', "Re-queued {$failed->count()} failed activation(s). Batch: {$batchId}");
        } finally {
            $lock->release();
        }
    }
}
