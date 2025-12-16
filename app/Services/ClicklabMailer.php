<?php

namespace App\Services;

use Illuminate\Mail\Mailable;

class ClicklabMailer
{
    public function send(string $to, Mailable $mailable): array
    {
        $subject = method_exists($mailable, 'envelope') && $mailable->envelope()
            ? $mailable->envelope()->subject
            : 'Notificación';

        $html = $mailable->render();

        return app(ClicklabClient::class)->sendEmail($to, $subject, $html);
    }
}

