<?php

namespace App\Mail;

use App\Notifications\AlertMessage;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class AlertMail extends Mailable
{
    public function __construct(public AlertMessage $alert) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->alert->title);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.alert');
    }
}
