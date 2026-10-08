<?php

namespace App\Jobs;

use App\Mail\AccountSetupMail;
use App\Models\AccountActivationRequest;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

class SendAccountActivationEmail implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [30, 60, 120];

    public function __construct(
        public readonly User $user,
        public readonly ?string $batchId = null,
    ) {
        $this->onQueue('email');
    }

    public function uniqueId(): string
    {
        return (string) $this->user->id;
    }

    public function handle(): void
    {
        $record = AccountActivationRequest::where('user_id', $this->user->id)->first();

        if ($record && $record->status === 'activated') {
            return;
        }

        $token = Password::broker()->createToken($this->user);

        Mail::to($this->user->email)->send(new AccountSetupMail($this->user, $token));

        AccountActivationRequest::updateOrCreate(
            ['user_id' => $this->user->id],
            [
                'status' => 'sent',
                'sent_at' => now(),
                'batch_id' => $this->batchId,
                'error_message' => null,
            ]
        );
    }

    public function failed(\Throwable $e): void
    {
        AccountActivationRequest::updateOrCreate(
            ['user_id' => $this->user->id],
            [
                'status' => 'failed',
                'error_message' => substr($e->getMessage(), 0, 1000),
            ]
        );
    }
}
