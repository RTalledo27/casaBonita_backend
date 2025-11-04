<?php

namespace App\Mail;

use Modules\Security\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResetPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $resetUrl;
    public $token;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, string $resetUrl, string $token)
    {
        $this->user = $user;
        $this->resetUrl = $resetUrl;
        $this->token = $token;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Recuperación de Contraseña - Casa Bonita Residencial',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        // Use a full HTML Blade view for a professional email template
        return new Content(
            view: 'emails.reset-password-html',
            with: [
                'user' => $this->user,
                'resetUrl' => $this->resetUrl,
                'token' => $this->token,
            ]
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
