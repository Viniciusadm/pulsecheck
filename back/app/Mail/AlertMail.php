<?php

namespace App\Mail;

use App\Models\InstanceSettings;
use App\Notifications\AlertMessage;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class AlertMail extends Mailable
{
    public string $instanceName;

    public function __construct(public AlertMessage $alert)
    {
        $this->instanceName = InstanceSettings::currentName();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "[{$this->instanceName}] {$this->alert->title}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.alert');
    }
}
