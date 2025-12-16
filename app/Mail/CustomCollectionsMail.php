<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CustomCollectionsMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $subjectLine;
    public string $bodyHtml;

    public function __construct(string $subjectLine, string $html)
    {
        $this->subjectLine = $subjectLine;
        $this->bodyHtml = $html;
    }

    public function envelope(): \Illuminate\Mail\Mailables\Envelope
    {
        return new \Illuminate\Mail\Mailables\Envelope(subject: $this->subjectLine);
    }

    public function content(): \Illuminate\Mail\Mailables\Content
    {
        return new \Illuminate\Mail\Mailables\Content(view: 'emails.custom-collections', with: [
            'html' => $this->bodyHtml,
            'subject' => $this->subjectLine,
        ]);
    }
}
