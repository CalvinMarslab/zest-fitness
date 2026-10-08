<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccountSetupMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $resetUrl;

    public function __construct(
        public readonly User $user,
        private ?string $token = null,
        private ?string $overrideUrl = null,
    ) {
        $this->resetUrl = $overrideUrl ?? route('activation.complete', [
            'token' => $this->token,
            'email' => $user->email,
        ]);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Set up your Zest Athletic account',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.account-setup',
        );
    }
}
