<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendAccountActivationEmail;
use App\Mail\AccountSetupMail;
use App\Models\AccountActivationRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AdminAccountActivationController extends Controller
{
    const MAX_BATCH_SIZE = 500;

    private function eligibleQuery()
    {
        return User::where('role', 'member')
            ->where(fn ($q) => $q->whereNull('is_admin')->orWhere('is_admin', false));
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

        return back()->with('success', "Queued activation emails for {$members->count()} member(s). Batch: {$batchId}");
    }

    public function retryFailed(): RedirectResponse
    {
        $batchId = Str::uuid()->toString();

        $failed = $this->eligibleQuery()
            ->whereHas('activationRequest', fn ($q) => $q->where('status', 'failed'))
            ->get(['id']);

        if ($failed->isEmpty()) {
            return back()->with('error', 'No failed activations to retry.');
        }

        foreach ($failed as $user) {
            AccountActivationRequest::where('user_id', $user->id)
                ->update(['status' => 'pending', 'batch_id' => $batchId]);

            SendAccountActivationEmail::dispatch($user, $batchId);
        }

        return back()->with('success', "Re-queued {$failed->count()} failed activation(s). Batch: {$batchId}");
    }
}
